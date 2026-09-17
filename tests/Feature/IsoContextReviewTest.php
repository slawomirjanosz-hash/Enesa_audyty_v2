<?php

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\IsoContextReview;
use App\Models\IsoSectionDocument;
use App\Models\User;
use App\Services\IsoContextLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function isoReviewFixture(): array
{
    $company = Company::create(['name' => 'Test kontekstu', 'status' => 'active', 'company_type' => 'client']);
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->companies()->attach($company);
    $staff = User::factory()->create();
    $staff->assignRole(Role::findOrCreate('superadmin'));
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'LIB/1', 'title' => 'ISO biblioteka', 'status' => 'draft']);
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'ISO', 'status' => 'draft']);

    return [$audit, $client, $staff];
}

test('ISO library imports all source records and normalizes option values', function () {
    $library = app(IsoContextLibrary::class);
    expect($library->questions())->toHaveCount(44)
        ->and($library->data()['czynniki_kontekstowe_4_1'])->toHaveCount(69)
        ->and($library->data()['strony_zainteresowane_4_2'])->toHaveCount(28)
        ->and($library->data()['mapowanie_4_1_na_4_2'])->toHaveCount(21);
    $questions = collect($library->questions())->keyBy('kod');
    expect($questions['FAKT_PODLICZNIKI']['options'])->toHaveKey('czesciowo')
        ->and($questions['FAKT_ZMIANY']['options'])->toHaveKey(4);
});

test('ISO condition parser treats unknowns as false and handles all operators safely', function () {
    $library = app(IsoContextLibrary::class);
    expect($library->matches('FAKT_X != tak', []))->toBeFalse()
        ->and($library->matches('FAKT_X != tak', ['FAKT_X' => 'nie wiem']))->toBeFalse()
        ->and($library->matches('ZUZYCIE_TJ >= 10 AND ZUZYCIE_TJ <= 85', ['ZUZYCIE_TJ' => 10]))->toBeTrue()
        ->and($library->matches('ZUZYCIE_TJ > 85', ['ZUZYCIE_TJ' => 85]))->toBeFalse()
        ->and($library->matches('ZUZYCIE_TJ < 10', ['ZUZYCIE_TJ' => 'nie wiem']))->toBeFalse()
        ->and($library->matches('FAKT_X IN (tak, mieszane)', ['FAKT_X' => 'mieszane']))->toBeTrue()
        ->and($library->matches('FAKT_X = nie OR FAKT_Y = tak AND FAKT_Z = tak', ['FAKT_X' => 'nie']))->toBeTrue()
        ->and($library->matches('ZAWSZE OR system("whoami")', []))->toBeFalse();
});

test('ISO library retains stale manual factors and recomputes automatic climate factors', function () {
    $library = app(IsoContextLibrary::class);
    $answers = ['facts' => ['FAKT_KLIMAT_ISTOTNY' => 'nie', 'FAKT_SCADA' => 'nie'],
        'factors' => ['KTX-WT-10' => ['selected' => true, 'text' => 'Własny opis']]];
    $factors = collect($library->factors($answers))->keyBy('kod');
    expect($factors['KTX-WT-10']['stale'])->toBeTrue()
        ->and($factors['KTX-WT-10']['text'])->toBe('Własny opis')
        ->and($factors['KTX-ZK-02']['selected'])->toBeTrue();
    expect($library->mode(['FAKT_SYSTEM_BAZOWY' => 'iso_9001', 'FAKT_ZGODA_INTEGRACJA' => 'tak']))->toBe('nadbudowa');
});

test('ISO review client and consultant screens render and foreign clients cannot access them', function () {
    [$audit, $client, $staff] = isoReviewFixture();
    $this->actingAs($client)->get(route('client.audits.iso-review.show', $audit))->assertOk()
        ->assertSee('Dane o zakładzie')->assertSee('Strony zainteresowane')
        ->assertDontSee('name="answers[swot][strengths]"', false);
    $this->actingAs($staff)->get(route('audits.iso-review.show', $audit))->assertOk()
        ->assertSee('name="answers[swot][strengths]"', false);
    $other = User::factory()->create();
    $other->assignRole(Role::findOrCreate('client_user'));
    $this->actingAs($other)->get(route('client.audits.iso-review.show', $audit))->assertNotFound();
    $this->post(route('client.audits.iso-review.update', $audit), ['year' => 2026, 'revision' => 0, 'operation' => 'save', 'answers' => []])->assertNotFound();
    $this->post(route('client.audits.iso-review.export', $audit), ['year' => 2026, 'format' => 'pdf'])->assertNotFound();
});

test('ISO review snapshots every save and prevents stale or forged client updates', function () {
    [$audit, $client] = isoReviewFixture();
    $url = route('client.audits.iso-review.update', $audit);
    $payload = ['year' => 2026, 'revision' => 0, 'operation' => 'save', 'answers' => [
        'facts' => ['FAKT_SCADA' => 'tak', 'ZUZYCIE_TJ' => 999], 'energy' => [['name' => 'Prąd', 'tj' => 2.5], ['name' => 'Gaz', 'tj' => 3]],
        'swot' => ['strengths' => 'Klient próbuje zmienić pole konsultanta'], 'status' => 'approved']];
    $this->actingAs($client)->post($url, $payload)->assertRedirect();
    $review = IsoContextReview::firstOrFail();
    expect($review->answers['facts']['ZUZYCIE_TJ'])->toBe('5.5')->and($review->answers['swot'])->toBe([])
        ->and($review->status)->toBe('draft')->and($review->revision)->toBe(1);
    expect(DB::table('iso_context_revisions')->count())->toBe(1);
    $this->post($url, $payload)->assertStatus(409);
    $this->post($url, ['year' => 2026, 'revision' => 1, 'operation' => 'approve'])->assertForbidden();
    $this->post($url, ['year' => 2026, 'revision' => 1, 'operation' => 'submit', 'answers' => ['facts' => ['FAKT_SCADA' => 'tak']]])->assertSessionHasErrors();
});

test('ISO review submission review and return protect the client editing window', function () {
    [$audit, $client, $staff] = isoReviewFixture();
    $answers = ['facts' => array_fill_keys(array_column(app(IsoContextLibrary::class)->questions(), 'kod'), 'nie wiem'), 'energy_unknown' => 1];
    $clientUrl = route('client.audits.iso-review.update', $audit);
    $staffUrl = route('audits.iso-review.update', $audit);
    $this->actingAs($client)->post($clientUrl, ['year' => 2026, 'revision' => 0, 'operation' => 'submit', 'answers' => $answers])->assertRedirect();
    expect(IsoContextReview::first()->status)->toBe('submitted');
    $this->actingAs($staff)->post($staffUrl, ['year' => 2026, 'revision' => 1, 'operation' => 'review'])->assertRedirect();
    $this->actingAs($client)->post($clientUrl, ['year' => 2026, 'revision' => 2, 'operation' => 'withdraw'])->assertForbidden();
    $this->post($clientUrl, ['year' => 2026, 'revision' => 2, 'operation' => 'save', 'answers' => $answers])->assertForbidden();
    $this->actingAs($staff)->post($staffUrl, ['year' => 2026, 'revision' => 2, 'operation' => 'approve'])->assertSessionHasErrors();
    $this->post($staffUrl, ['year' => 2026, 'revision' => 2, 'operation' => 'return', 'note' => 'Proszę uzupełnić pomiary.'])->assertRedirect();
    expect(IsoContextReview::first()->status)->toBe('returned');
});

test('ISO review copying next year preserves prior year and resets approval', function () {
    [$audit, $client] = isoReviewFixture();
    IsoContextReview::create(['audit_id' => $audit->id, 'year' => 2026, 'status' => 'approved', 'revision' => 7, 'answers' => ['facts' => ['FAKT_SCADA' => 'tak']]]);
    $this->actingAs($client)->post(route('client.audits.iso-review.update', $audit), ['year' => 2027, 'revision' => 0, 'operation' => 'copy'])->assertRedirect();
    expect(IsoContextReview::where('year', 2026)->first()->status)->toBe('approved')
        ->and(IsoContextReview::where('year', 2027)->first()->status)->toBe('draft')
        ->and(IsoContextReview::where('year', 2027)->first()->answers['facts']['FAKT_SCADA'])->toBe('tak');
});

test('ISO review draft PDF and true DOCX are saved in client documentation', function () {
    Storage::fake('local');
    [$audit, $client] = isoReviewFixture();
    IsoContextReview::create(['audit_id' => $audit->id, 'year' => 2026, 'revision' => 1, 'answers' => ['facts' => ['FAKT_SCADA' => 'tak']]]);
    $this->actingAs($client)->post(route('client.audits.iso-review.export', $audit), ['year' => 2026, 'format' => 'pdf', 'preview' => 1])
        ->assertOk()->assertHeader('Content-Disposition', 'inline; filename="ISO_4_1_4_2_ROBOCZY_2026_r1.pdf"');
    expect(IsoSectionDocument::count())->toBe(0);
    foreach (['pdf', 'docx'] as $format) {
        $response = $this->actingAs($client)->post(route('client.audits.iso-review.export', $audit), ['year' => 2026, 'format' => $format])->assertOk();
        expect(substr($response->getContent(), 0, $format === 'pdf' ? 4 : 2))->toBe($format === 'pdf' ? '%PDF' : 'PK');
    }
    expect(IsoSectionDocument::where('audit_id', $audit->id)->count())->toBe(2);
    expect(IsoSectionDocument::first()->title)->toStartWith('ROBOCZY');
});

test('ISO consultant can approve complete data and reopen it without allowing client approval', function () {
    [$audit, $client, $staff] = isoReviewFixture();
    $library = app(IsoContextLibrary::class);
    $answers = ['facts' => array_fill_keys(array_column($library->questions(), 'kod'), 'nie wiem'),
        'scope' => 'Zakład A', 'climate_reason' => 'Udokumentowana analiza zmian zapotrzebowania na chłód.',
        'swot' => array_fill_keys(['strengths', 'weaknesses', 'opportunities', 'threats'], 'Opis i uzasadnienie'),
        'conclusions' => array_fill(0, 4, ['finding' => 'Wniosek', 'decision' => 'Decyzja', 'document' => 'Plan działań'])];
    $answers['facts']['FAKT_KLIMAT_ISTOTNY'] = 'tak';
    foreach ($library->stakeholders($answers) as $party) {
        $answers['stakeholders'][$party['kod']] = ['selected' => true, 'compliance' => 'no', 'reason' => 'Ocena konsultanta: oczekiwanie, nie przyjęty obowiązek.'];
    }
    $review = IsoContextReview::create(['audit_id' => $audit->id, 'year' => 2026, 'status' => 'reviewing', 'revision' => 1, 'answers' => $answers]);
    $url = route('audits.iso-review.update', $audit);
    $this->actingAs($staff)->post($url, ['year' => 2026, 'revision' => 1, 'operation' => 'approve'])->assertRedirect();
    expect($review->fresh()->status)->toBe('approved');
    $this->actingAs($client)->post(route('client.audits.iso-review.update', $audit), ['year' => 2026, 'revision' => 2, 'operation' => 'request_reopen', 'note' => 'Zmiana zakresu.'])->assertRedirect();
    expect($review->fresh()->status)->toBe('approved');
    $this->actingAs($staff)->post($url, ['year' => 2026, 'revision' => 3, 'operation' => 'reopen', 'note' => 'Ponowna ocena zakresu.'])->assertRedirect();
    expect($review->fresh()->status)->toBe('returned')->and(DB::table('iso_context_revisions')->count())->toBe(3);
});

test('ISO new library preview is separate from client answers', function () {
    [$audit, $client, $staff] = isoReviewFixture();
    $type = AuditType::where('slug', 'iso50001')->first();
    $this->actingAs($staff)->get(route('audit-types.iso50001.library', $type))->assertOk()
        ->assertSee('44 pytania')->assertSee('69 czynników')->assertSee('28 stron')->assertDontSee('name="answers', false);
    expect(IsoContextReview::count())->toBe(0);
});
