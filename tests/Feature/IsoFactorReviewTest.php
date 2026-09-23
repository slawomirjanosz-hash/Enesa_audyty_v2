<?php

use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoPlantQuestionnaire;
use Illuminate\Support\Facades\DB;

function factorFixture(): array
{
    [$audit, $client, $staff] = plantFixture();
    $site = DB::table('iso_plant_sites')->insertGetId(['company_id' => $audit->company_id, 'name' => 'Zakład', 'created_at' => now(), 'updated_at' => now()]);
    $profile = IsoPlantProfile::create(['audit_id' => $audit->id, 'site_id' => $site, 'revision' => 1, 'lock_version' => 0, 'status' => 'approved', 'as_of_date' => today(), 'definition' => app(IsoPlantQuestionnaire::class)->definition(), 'answers' => ['site.name' => ['value' => 'Zakład'], 'FAKT_PODLICZNIKI' => ['value' => 'brak']], 'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id]]);

    return [$audit, $client, $staff, $profile];
}

function factorInput(IsoPlantProfile $profile): array
{
    $service = app(IsoFactorQuestionnaire::class);
    $answers = ['FAKT_KLIMAT_ISTOTNY' => 'nie', 'climate_reason' => 'Przeprowadzono ocenę warunków zakładu.'];
    foreach ($service->factors($service->facts($profile), $answers) as $factor) {
        if ($factor['visible'] && $factor['rodzaj'] !== 'AUTO') {
            $answers['factors'][$factor['kod']] = ['decyzja' => 'potwierdzony', 'tresc' => $factor['tresc']];
        }
    }

    return ['lock_version' => 0, 'source_hash' => $service->hash($profile), 'operation' => 'submit', 'complete_form' => 1, 'answers' => $answers];
}

test('factor schema preserves 69 codes seven groups and references plant facts safely', function () {
    $s = app(IsoFactorQuestionnaire::class);
    expect($s->schema()['czynniki'])->toHaveCount(69)->and($s->schema()['sekcje'])->toHaveCount(7);
    $factors = collect($s->factors(['FAKT_PODLICZNIKI' => 'brak'], []))->keyBy('kod');
    expect($factors['KTX-WT-01']['visible'])->toBeTrue()->and($factors['KTX-WT-02']['visible'])->toBeFalse();
    expect(collect($s->factors([], []))->where('visible', true)->where('kod', 'KTX-WT-01'))->toHaveCount(0);
    expect($factors['KTX-ZR-01']['rodzaj'])->toBe('PROPOZYCJA');
});

test('factor review completes two approvals PDF and same record correction exchange', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $route = fn ($clientSide, $action = 'update') => route(($clientSide ? 'client.' : '').'audits.factors.'.$action, [$audit, $profile]);
    $this->actingAs($client)->get($route(true, 'show'))->assertOk()->assertSee('KTX-WT-01')->assertSee('Własne czynniki');
    $input = factorInput($profile);
    $this->post($route(true), $input)->assertSessionHasNoErrors()->assertRedirect();
    $review = IsoFactorReview::firstOrFail();
    expect($review->status)->toBe('submitted')->and($review->client_changes)->toBeNull();
    $this->actingAs($staff)->post($route(false), ['operation' => 'approve', 'lock_version' => 1, 'source_hash' => $input['source_hash'], 'note' => 'Zweryfikowano czynniki oraz nieznane warunki.'])->assertRedirect();
    expect($review->fresh()->status)->toBe('approved');
    $this->post($route(false, 'pdf'), ['lock_version' => 2])->assertRedirect();
    expect(IsoSectionDocument::firstOrFail()->section_id)->toBe('4-1');
    $input['operation'] = 'save';
    $input['lock_version'] = 2;
    $input['answers']['factors']['KTX-WT-01']['tresc'] = 'Korekta audytora';
    $this->post($route(false), $input)->assertSessionHasNoErrors()->assertRedirect();
    expect($review->fresh()->status)->toBe('auditor_corrected')->and($review->fresh()->client_approval)->toBeNull();
    $this->actingAs($client)->get($route(true, 'show'))->assertOk()->assertSee('Korekta audytora')->assertSee('Zmienione przez audytora');
    $input['operation'] = 'submit';
    $input['lock_version'] = 3;
    $input['answers']['factors']['KTX-WT-01']['tresc'] = 'Korekta klienta';
    $this->post($route(true), $input)->assertSessionHasNoErrors()->assertRedirect();
    expect($review->fresh()->client_changes)->toHaveKey('KTX-WT-01')->and(IsoFactorReview::count())->toBe(1);
    $input['operation'] = 'save';
    $input['lock_version'] = 4;
    $this->actingAs($staff)->post($route(false), $input)->assertRedirect();
    expect($review->fresh()->lock_version)->toBe(4)->and($review->fresh()->status)->toBe('submitted');
    $this->post($route(false), ['operation' => 'approve', 'lock_version' => 4, 'source_hash' => $input['source_hash'], 'note' => 'Zaakceptowano poprawki klienta.'])->assertRedirect();
    expect($review->fresh()->status)->toBe('approved')->and($review->fresh()->client_changes)->toBeNull()->and(IsoFactorReview::count())->toBe(1);
});

test('climate automatic wording uses actual reason and does not invent a justification', function () {
    $s = app(IsoFactorQuestionnaire::class);
    $rows = collect($s->factors([], ['FAKT_KLIMAT_ISTOTNY' => 'nie', 'climate_reason' => 'Uzasadnienie rzeczywiste']))->keyBy('kod');
    expect($rows['KTX-ZK-02']['visible'])->toBeTrue()->and($rows['KTX-ZK-02']['tresc'])->toContain('Uzasadnienie rzeczywiste');
    $form = $s->formAnswers(['FAKT_KLIMAT_ISTOTNY' => ['malformed'], 'factors' => ['KTX-WT-01' => ['tresc' => ['malformed']]]]);
    expect($form['FAKT_KLIMAT_ISTOTNY'])->toBe('')->and($form['factors']['KTX-WT-01']['tresc'])->toBe('');
});

test('factor review rejects invalid rejection stale edits unapproved source and cross company access', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $url = route('client.audits.factors.update', [$audit, $profile]);
    $input = factorInput($profile);
    $input['answers']['factors']['KTX-WT-01']['decyzja'] = 'odrzucony';
    $this->actingAs($client)->post($url, $input)->assertSessionHasErrors('answers.factors.KTX-WT-01.powod');
    $input = factorInput($profile);
    $profile->increment('lock_version');
    $this->post($url, $input)->assertStatus(409);
    $input = factorInput($profile->fresh());
    $profile->update(['status' => 'editing']);
    $this->post($url, $input)->assertStatus(409);
    [$otherAudit] = plantFixture();
    $this->get(route('client.audits.factors.show', [$otherAudit, $profile]))->assertNotFound();
    expect(IsoFactorReview::count())->toBe(0);
});

test('source change invalidates only dependent decisions and blocks old PDF', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $s = app(IsoFactorQuestionnaire::class);
    $input = factorInput($profile);
    $this->actingAs($client)->post(route('client.audits.factors.update', [$audit, $profile]), $input)->assertRedirect();
    $review = IsoFactorReview::firstOrFail();
    $facts = $s->facts($profile);
    $facts['FAKT_PODLICZNIKI'] = 'czesciowo';
    $effective = $s->currentAnswers($review->answers, $review->basis, $facts);
    expect($effective['factors']['KTX-WT-01']['decyzja'] ?? null)->toBeNull();
    foreach ($s->factors($facts, $review->answers) as $factor) {
        if ($factor['pokaz_gdy'] === 'ZAWSZE' && $factor['rodzaj'] !== 'AUTO') {
            expect($effective['factors'][$factor['kod']]['decyzja'])->toBe('potwierdzony');
        }
    }
    $profile->increment('lock_version');
    $this->actingAs($staff)->post(route('audits.factors.pdf', [$audit, $profile]), ['lock_version' => 1])->assertStatus(409);
});
