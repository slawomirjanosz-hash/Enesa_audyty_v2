<?php

use App\Models\IsoPlantProfile;
use App\Services\IsoPlantQuestionnaire;
use App\Services\QuestionnaireCompletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('auditor schema retains all 85 stable codes and all six departments', function () {
    $source = json_decode(file_get_contents(resource_path('iso50001/plant-profile-auditor-source-v1.4.json')), true);
    $service = app(IsoPlantQuestionnaire::class);
    $definition = $service->definition();
    $questions = collect($service->questions($definition))->keyBy('key');
    expect($definition['groups'])->toHaveCount(6)->and($questions)->toHaveCount(90);
    foreach ($source['sekcje'] as $section) {
        foreach ($section['pytania'] as $q) {
            expect($questions[$q['kod']]['label'])->toBe($q['pytanie']);
            foreach ($q['opcje'] as $option) {
                expect($questions[$q['kod']]['options'][$option['w']])->toBe($option['e']);
            }
        }
    }
    expect($service->visible($questions['FAKT_SYSTEM_BAZOWY_ROK'], []))->toBeFalse();
    expect($service->visible($questions['FAKT_SYSTEM_BAZOWY_ROK'], ['FAKT_SYSTEM_BAZOWY' => ['value' => 'brak']]))->toBeFalse();
    expect($service->visible($questions['FAKT_SYSTEM_BAZOWY_ROK'], ['FAKT_SYSTEM_BAZOWY' => ['value' => 'iso_9001']]))->toBeTrue();
    expect($service->visible($questions['FAKT_LOKALIZACJE_LISTA'], ['FAKT_LOKALIZACJE' => ['value' => 0]]))->toBeFalse();
    expect($service->visible($questions['FAKT_LOKALIZACJE_LISTA'], ['FAKT_LOKALIZACJE' => ['value' => 2]]))->toBeTrue();
    expect(app(QuestionnaireCompletion::class)->plant($definition, [])['total'])->toBe(57);
});

test('server calculates energy and fossil fuel facts without trusting submitted automatic fields', function () {
    $s = app(IsoPlantQuestionnaire::class);
    $input = ['FAKT_NOSNIKI' => ['value' => [['c0' => 'energia elektryczna', 'c1' => 1000, 'c2' => 'MWh', 'c3' => 0]]], 'ZUZYCIE_TJ' => ['value' => 999], 'FAKT_KOTLOWNIA' => ['value' => 'wlasne'], 'FAKT_CIEPLO_PALIWO' => ['value' => ['gaz ziemny']]];
    $answers = $s->normalize($input, $s->definition(), ['old.variable' => ['value' => 'Zachowaj']], 1, false);
    expect($answers['ZUZYCIE_TJ']['value'])->toBe(3.6)->and($answers['FAKT_CIEPLO_PALIWA']['value'])->toBe('tak')
        ->and($answers['old.variable']['value'])->toBe('Zachowaj');
    $input['FAKT_NOSNIKI']['value'][] = ['c0' => 'węgiel', 'c1' => 2, 'c2' => 'l'];
    $input['FAKT_KOTLOWNIA']['value'] = 'siec';
    $answers = $s->normalize($input, $s->definition(), [], 1, false);
    expect($answers['ZUZYCIE_TJ']['value'])->toBeNull()->and($answers['FAKT_CIEPLO_PALIWA']['value'])->toBe('do potwierdzenia');
    expect($s->facts($s->definition(), $answers)['FAKT_CIEPLO_PALIWO'])->toBeNull();
});

test('auditor questionnaire validates units percentages years dates and maximum three processes', function () {
    $s = app(IsoPlantQuestionnaire::class);
    foreach ([
        ['FAKT_LED_UDZIAL' => ['value' => 101]], ['FAKT_ROK_ODNIESIENIA' => ['value' => 12]],
        ['FAKT_UMOWA_DATA' => ['value' => '2026-99-99']],
        ['FAKT_NOSNIKI' => ['value' => [['c0' => 'gaz ziemny', 'c1' => 2]]]],
        ['FAKT_PROCESY' => ['value' => ['piece i nagrzewnice', 'suszarnie', 'prasy', 'malarnia']]],
    ] as $input) {
        expect(fn () => $s->normalize($input, $s->definition(), [], 1, false))->toThrow(ValidationException::class);
    }
});

test('new auditor questionnaire saves approves and renders with automatic fields and department labels', function () {
    [$audit, $client, $staff] = plantFixture();
    $s = app(IsoPlantQuestionnaire::class);
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Zakład']);
    $profile = IsoPlantProfile::firstOrFail();
    $this->get(route('client.audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('FAKT_CA_MOC')->assertSee('Kontroling / finanse')->assertSee('data-calculated', false);
    $input = [];
    foreach ($s->questions($profile->definition) as $q) {
        $input[$q['key']] = match ($q['type']) {
            'select' => ['value' => (string) array_key_first($q['options'])],
            'multi' => ['value' => [(string) array_key_first($q['options'])]],
            default => $q['required'] ? ['value' => 'Dane zakładu'] : ['unknown' => true],
        };
    }
    $this->post(route('client.audits.plant-profile.update', [$audit, $profile]), ['operation' => 'submit', 'lock_version' => 0, 'complete_form' => 1, 'as_of_date' => '2026-09-23', 'answers' => $input])->assertSessionHasNoErrors()->assertRedirect();
    expect($profile->fresh()->status)->toBe('submitted');
    $this->actingAs($staff)->post(route('audits.plant-profile.update', [$audit, $profile]), ['operation' => 'approve', 'lock_version' => 1, 'note' => 'Sprawdzono'])->assertRedirect();
    expect($profile->fresh()->status)->toBe('approved');
});

test('schema upgrade preserves answers and a full backup without creating profile revisions', function () {
    [$audit, $client] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Zakład']);
    $profile = IsoPlantProfile::firstOrFail();
    $oldDefinition = json_decode(file_get_contents(resource_path('iso50001/plant-profile-v1.json')), true);
    $original = ['site.name' => ['value' => 'Zakład'], 'operations.headcount' => ['value' => 42]];
    $profile->update(['definition' => $oldDefinition, 'answers' => $original, 'status' => 'approved', 'client_approval' => ['name' => 'Klient'], 'auditor_approval' => ['name' => 'Audytor']]);
    $migration = require database_path('migrations/2026_09_23_000002_upgrade_plant_profile_to_auditor_schema.php');
    $migration->up();
    $profile->refresh();
    expect($profile->definition['version'])->toBe('2.0')->and($profile->answers)->toBe($original)
        ->and($profile->client_approval)->toBeNull()->and($profile->status)->toBe('editing')
        ->and($profile->revision)->toBe(1)->and(IsoPlantProfile::count())->toBe(1);
    expect(DB::table('iso_plant_events')->where('action', 'schema_upgrade')->count())->toBe(1);
    $migration->up();
    expect(DB::table('iso_plant_events')->where('action', 'schema_upgrade')->count())->toBe(1);
    $this->get(route('client.audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('Odpowiedzi zachowane z poprzedniego profilu')->assertSee('42');
});
