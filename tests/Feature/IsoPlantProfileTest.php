<?php

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Models\User;
use App\Services\IsoPlantQuestionnaire;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function plantFixture(): array
{
    $company = Company::create(['name' => 'Zakłady testowe', 'company_type' => 'client', 'status' => 'active']);
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_admin'));
    $client->companies()->attach($company);
    $staff = User::factory()->create();
    $staff->assignRole(Role::findOrCreate('superadmin'));
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'PLANT/'.$company->id, 'title' => 'ISO', 'status' => 'draft']);
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'ISO', 'status' => 'draft']);

    return [$audit, $client, $staff];
}

function plantAnswers(): array
{
    $answers = [];
    foreach (app(IsoPlantQuestionnaire::class)->questions(app(IsoPlantQuestionnaire::class)->definition()) as $q) {
        $answers[$q['key']] = match ($q['type']) {
            'select' => ['value' => 'unknown'],
            'multi' => ['value' => ['unknown']],
            default => $q['required'] ? ['value' => 'Dane zakładu testowego'] : ['unknown' => true],
        };
    }

    return $answers;
}

test('plant profile has 65 uniquely named questions and renders client and staff screens', function () {
    [$audit, $client, $staff] = plantFixture();
    $questions = app(IsoPlantQuestionnaire::class)->questions(app(IsoPlantQuestionnaire::class)->definition());
    expect($questions)->toHaveCount(65)->and(array_unique(array_column($questions, 'key')))->toHaveCount(65);
    $this->actingAs($client)->get(route('client.audits.plant-profile.index', $audit))->assertOk()->assertSee('data-sortable="false"', false)->assertSee('type="module"', false);
    $this->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła'])->assertRedirect();
    $profile = IsoPlantProfile::firstOrFail();
    $this->get(route('client.audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('Zatwierdź jako klient')->assertSee('name="answers[energy.carriers][value][]"', false)->assertDontSee('Zatwierdź jako audytor');
    $this->actingAs($staff)->get(route('audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertDontSee('Zatwierdź jako klient');
});

test('answer details stay collapsed even with saved details and sources for client and staff', function () {
    [$audit, $client, $staff] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $profile->update(['answers' => ['site.name' => ['value' => 'Piła', 'detail' => 'Dodatkowy opis', 'source' => 'Dokument źródłowy']]]);
    foreach ([[$client, 'client.audits.plant-profile.show'], [$staff, 'audits.plant-profile.show']] as [$user, $route]) {
        $response = $this->actingAs($user)->get(route($route, [$audit, $profile]))->assertOk()
            ->assertSee('Dodatkowy opis')->assertSee('Dokument źródłowy');
        preg_match_all('/<details\b([^>]*)>\s*<summary>Uzupełnienie i źródło odpowiedzi<\/summary>/u', $response->getContent(), $matches);
        expect($matches[1])->toHaveCount(65);
        foreach ($matches[1] as $attributes) {
            expect($attributes)->not->toContain('open');
        }
    }
});

test('unanswered highlighting starts after saving and remains on reopening for both roles', function () {
    [$audit, $client, $staff] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $url = route('client.audits.plant-profile.show', [$audit, $profile]);
    $this->get($url)->assertOk()->assertDontSee('data-highlight-unanswered');
    $this->post(route('client.audits.plant-profile.update', [$audit, $profile]), [
        'lock_version' => 0, 'operation' => 'save', 'complete_form' => 1,
        'as_of_date' => '2026-09-22', 'answers' => ['site.name' => ['value' => 'Piła']],
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->get($url)->assertOk()->assertSee('data-highlight-unanswered')->assertSee('Brak odpowiedzi');
    $this->actingAs($staff)->get(route('audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('data-highlight-unanswered');
});

test('plant profile rejects foreign clients audit mismatch and forged approval', function () {
    [$audit, $client, $staff] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $this->post(route('client.audits.plant-profile.update', [$audit, $profile]), ['lock_version' => 0, 'operation' => 'approve'])->assertForbidden();
    $this->post(route('client.audits.plant-profile.pdf', [$audit, $profile]), ['lock_version' => 0])->assertForbidden();
    [$otherAudit, $otherClient] = plantFixture();
    $this->actingAs($otherClient)->get(route('client.audits.plant-profile.show', [$audit, $profile]))->assertNotFound();
    $this->get(route('client.audits.plant-profile.show', [$otherAudit, $profile]))->assertNotFound();
    $this->post(route('client.audits.plant-profile.create', $otherAudit), ['site_id' => $profile->site_id])->assertNotFound();
    $this->actingAs($staff)->post(route('audits.plant-profile.update', [$audit, $profile]), ['lock_version' => 0, 'operation' => 'submit'])->assertForbidden();
});

test('plant profile validates submissions and protects simultaneous changes', function () {
    [$audit, $client] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $url = route('client.audits.plant-profile.update', [$audit, $profile]);
    $this->post($url, ['lock_version' => 0, 'operation' => 'submit', 'complete_form' => 1, 'as_of_date' => '2026-09-22', 'answers' => ['site.name' => ['value' => 'Piła']]])->assertSessionHasErrors();
    $this->post($url, ['lock_version' => 0, 'operation' => 'save', 'complete_form' => 1, 'as_of_date' => '2026-09-22', 'answers' => ['site.name' => ['value' => 'Piła'], 'admin.fake' => ['value' => 'injected']]])->assertRedirect();
    expect($profile->fresh()->answers)->not->toHaveKey('admin.fake');
    $this->post($url, ['lock_version' => 0, 'operation' => 'save'])->assertStatus(409);
    expect(DB::table('iso_plant_events')->count())->toBe(2);
});

test('plant profile approval PDF and new version preserve immutable history', function () {
    [$audit, $client, $staff] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $clientUrl = route('client.audits.plant-profile.update', [$audit, $profile]);
    $staffUrl = route('audits.plant-profile.update', [$audit, $profile]);
    $this->post($clientUrl, ['lock_version' => 0, 'operation' => 'submit', 'complete_form' => 1, 'as_of_date' => '2026-09-22', 'answers' => plantAnswers()])->assertSessionHasNoErrors()->assertRedirect();
    expect($profile->fresh()->status)->toBe('submitted');
    $this->post($clientUrl, ['lock_version' => 1, 'operation' => 'save', 'answers' => plantAnswers()])->assertStatus(409);
    $this->actingAs($staff)->get(route('audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('Zatwierdź jako audytor');
    $this->post($staffUrl, ['lock_version' => 1, 'operation' => 'approve', 'note' => 'Zweryfikowano dane; brakujące pomiary będą uzupełniane w przeglądzie energetycznym.'])->assertRedirect();
    $this->get(route('audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('Podgląd PDF');
    $pdfUrl = route('audits.plant-profile.pdf', [$audit, $profile]);
    $preview = $this->post($pdfUrl, ['lock_version' => 2, 'preview' => 1])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($preview->getContent(), 0, 4))->toBe('%PDF');
    expect(IsoSectionDocument::count())->toBe(0);
    $this->post($pdfUrl, ['lock_version' => 2])->assertRedirect();
    $this->post($pdfUrl, ['lock_version' => 2])->assertRedirect();
    $doc = IsoSectionDocument::firstOrFail();
    expect(IsoSectionDocument::count())->toBe(1)->and($doc->section_id)->toBe('intro')->and($doc->scope)->toBe('client')->and(substr($doc->contents(), 0, 4))->toBe('%PDF');
    $this->post($staffUrl, ['lock_version' => 2, 'operation' => 'revise'])->assertRedirect();
    $next = IsoPlantProfile::orderByDesc('id')->firstOrFail();
    expect($next->revision)->toBe(2)->and($next->client_approval)->toBeNull()->and($next->auditor_approval)->toBeNull()->and($next->document_id)->toBeNull()->and($profile->fresh()->status)->toBe('approved');
    $this->post(route('audits.plant-profile.pdf', [$audit, $next]), ['lock_version' => 0])->assertForbidden();
    $this->post($staffUrl, ['lock_version' => 2, 'operation' => 'revise'])->assertRedirect();
    expect(IsoPlantProfile::count())->toBe(2);
});

test('returning profile clears approval and requires a fresh client confirmation', function () {
    [$audit, $client, $staff] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $this->post(route('client.audits.plant-profile.update', [$audit, $profile]), ['lock_version' => 0, 'operation' => 'submit', 'complete_form' => 1, 'as_of_date' => '2026-09-22', 'answers' => plantAnswers()]);
    $url = route('audits.plant-profile.update', [$audit, $profile]);
    $this->actingAs($staff)->post($url, ['lock_version' => 1, 'operation' => 'return', 'note' => 'Uzupełnij adres.'])->assertRedirect();
    expect($profile->fresh()->status)->toBe('returned')->and($profile->fresh()->client_approval)->toBeNull();
    $this->post($url, ['lock_version' => 2, 'operation' => 'approve', 'note' => 'OK'])->assertForbidden();
});

test('ordinary client can fill but cannot approve and sites stay separate', function () {
    [$audit, $client] = plantFixture();
    $client->syncRoles([Role::findOrCreate('client_user')]);
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $this->get(route('client.audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertDontSee('Zatwierdź jako klient');
    $this->post(route('client.audits.plant-profile.update', [$audit, $profile]), ['lock_version' => 0, 'operation' => 'submit'])->assertForbidden();
    $this->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Chorzów'])->assertRedirect();
    expect(IsoPlantProfile::count())->toBe(2)->and(DB::table('iso_plant_sites')->count())->toBe(2);
});

test('invalid profile fields retain input and return exact sparse row errors without saving', function () {
    [$audit, $client] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $show = route('client.audits.plant-profile.show', [$audit, $profile]);
    $answers = ['energy.records' => ['value' => [7 => ['quantity' => 123, 'period_start' => '2026-12-31', 'period_end' => '2026-01-01']]]];
    $this->from($show)->post(route('client.audits.plant-profile.update', [$audit, $profile]), [
        'lock_version' => 0, 'operation' => 'save', 'complete_form' => 1,
        'as_of_date' => '2026-09-22', 'answers' => $answers,
    ])->assertSessionHasErrors(['answers.energy.records.value.7.unit', 'answers.energy.records.value.7.period_end']);
    expect($profile->fresh()->lock_version)->toBe(0);
    $this->get($show)->assertOk()->assertSee('answers[energy.records][value][7][unit]', false)->assertSee('field-validation.js');
});

test('malformed old profile input is safe and invalid scalar values are preserved', function () {
    $service = app(IsoPlantQuestionnaire::class);
    $answers = $service->formAnswers(['energy.records' => ['value' => [9 => ['quantity' => 'bad-number', 'unit' => ['bad']]]]], $service->definition());
    expect($answers['energy.records']['value'][9]['quantity'])->toBe('bad-number')
        ->and($answers['energy.records']['value'][9])->not->toHaveKey('unit');
});

test('profile energy values need units period and boundary and reject contradictory selections', function () {
    $service = app(IsoPlantQuestionnaire::class);
    $definition = $service->definition();
    expect(fn () => $service->normalize(['energy.records' => ['value' => [['quantity' => 123]]]], $definition, [], 1, false))->toThrow(ValidationException::class);
    expect(fn () => $service->normalize(['energy.carriers' => ['value' => ['unknown', 'v1']]], $definition, [], 1, false))->toThrow(ValidationException::class);
    $answers = $service->normalize(['energy.records' => ['value' => [['carrier' => 'Prąd', 'quantity' => 123, 'unit' => 'MWh', 'period_start' => '2025-01-01', 'period_end' => '2025-12-31', 'boundary' => 'Cały zakład', 'flow_type' => 'purchase', 'quality' => 'invoice']]]], $definition, [], 1, false);
    expect($answers['energy.records']['value'][0]['id'])->toBeString()->and($answers['energy.records']['value'][0]['quantity'])->toBe(123);
});

test('staff view permission cannot mutate and delegated auditor cannot access a foreign company', function () {
    [$audit, $client] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $staff = User::factory()->create();
    $staff->assignRole(Role::findOrCreate('employee'));
    $staff->givePermissionTo(Permission::findOrCreate('audits.view'));
    $this->actingAs($staff)->get(route('audits.plant-profile.show', [$audit, $profile]))->assertOk();
    $this->post(route('audits.plant-profile.update', [$audit, $profile]), ['lock_version' => 0, 'operation' => 'save'])->assertForbidden();
    $staff->syncRoles([Role::findOrCreate('auditor')]);
    $staff->givePermissionTo(Permission::findOrCreate('audits.manage'));
    $this->get(route('audits.plant-profile.show', [$audit, $profile]))->assertForbidden();
});

test('copying a site into another audit retains periods but not signatures', function () {
    [$audit, $client, $staff] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $this->post(route('client.audits.plant-profile.update', [$audit, $profile]), ['lock_version' => 0, 'operation' => 'submit', 'complete_form' => 1, 'as_of_date' => '2025-12-31', 'answers' => plantAnswers()]);
    $this->actingAs($staff)->post(route('audits.plant-profile.update', [$audit, $profile]), ['lock_version' => 1, 'operation' => 'approve', 'note' => 'Zweryfikowano dane.']);
    $nextAudit = $audit->replicate();
    $nextAudit->number = 'PLANT/next';
    $nextAudit->save();
    $nextAudit->surveys()->create(['audit_type_id' => AuditType::where('slug', 'iso50001')->value('id'), 'title' => 'ISO', 'status' => 'draft']);
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $nextAudit), ['site_id' => $profile->site_id, 'copy_latest' => 1])->assertRedirect();
    $copy = IsoPlantProfile::where('audit_id', $nextAudit->id)->firstOrFail();
    expect($copy->as_of_date->format('Y-m-d'))->toBe('2025-12-31')->and($copy->answers)->toBe($profile->fresh()->answers)->and($copy->client_approval)->toBeNull()->and($copy->status)->toBe('editing');
});

test('truncated forms do not erase answers and a person cannot approve in both roles', function () {
    [$audit, $client] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $url = route('client.audits.plant-profile.update', [$audit, $profile]);
    $this->post($url, ['lock_version' => 0, 'operation' => 'save', 'as_of_date' => '2026-09-22', 'answers' => plantAnswers()])->assertSessionHasErrors('complete_form');
    expect($profile->fresh()->lock_version)->toBe(0);
    $this->post($url, ['lock_version' => 0, 'operation' => 'submit', 'complete_form' => 1, 'as_of_date' => '2026-09-22', 'answers' => plantAnswers()])->assertRedirect();
    $client->assignRole(Role::findOrCreate('superadmin'));
    $this->post(route('audits.plant-profile.update', [$audit, $profile]), ['lock_version' => 1, 'operation' => 'approve', 'note' => 'OK'])->assertForbidden();
});
