<?php

use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\IsoExecutionQuestionnaire;
use App\Services\IsoPlatformRelease;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

function executionAnswers(string $section, array $sources): array
{
    if (in_array($section, ['7-4', '7-5'])) {
        return app(IsoPlatformRelease::class)->answers($section, $sources);
    }
    $service = app(IsoExecutionQuestionnaire::class);
    $build = function ($node) use (&$build) {
        if ($node['type'] === 'list') {
            if ($node['fixed'] ?? false) {
                return [];
            }
            $row = [];
            foreach ($node['children'] as $key => $child) {
                $row[$key] = $build($child);
            }

            return isset($node['codes']) ? array_map(fn ($code) => array_replace($row, ['kod' => $code]), array_keys($node['codes'])) : [$row];
        }
        if ($node['type'] === 'object') {
            return array_map($build, $node['children']);
        }

        return null;
    };
    $answers = array_map($build, $service->tree($section));
    $answers = $service->project($section, $answers, $sources);
    for ($pass = 0; $pass < 3; $pass++) {
        foreach ($service->sections($section, $answers, $sources) as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['readonly'] || filled(data_get($answers, $field['key']))) {
                    continue;
                }
                $value = $field['options'] ? (string) array_key_first($field['options']) : ($field['numeric'] ? '1' : ($field['type'] === 'date' ? today()->toDateString() : 'Opis i dowód ze sprawdzenia'));
                if (str_ends_with($field['key'], '.brakZdarzen') || str_ends_with($field['key'], '.wlasnaPraca') || str_ends_with($field['key'], '.porownanieNd')) {
                    $value = 'nie';
                }
                Arr::set($answers, $field['key'], $value);
            }
        }
    }

    return $answers;
}

test('execution chapters preserve schemas drafts and nested field identifiers', function () {
    $service = app(IsoSystemQuestionnaire::class);
    foreach (array_keys(IsoExecutionQuestionnaire::TITLES) as $section) {
        expect($service->schema($section)['section'])->toBe($section);
        expect($service->normalize($section, $service->seed($section, []), [], false))->toBeArray();
        expect($service->progress($section, [], [])['percent'])->toBe(0);
    }
    $answers = ['KARTY' => [7 => ['kryteria' => [19 => ['parametr' => 'Ciśnienie']]]]];
    expect(data_get($service->normalize('8-1', $answers, [], false), 'KARTY.7.kryteria.19.parametr'))->toBe('Ciśnienie');
    expect(fn () => $service->normalize('8-1', ['KARTY' => ['bad.key' => []]], [], false))->toThrow(ValidationException::class);
    expect(fn () => $service->normalize('8-1', ['KARTY' => [['kryteria' => 'invalid']]], [], false))->toThrow(ValidationException::class);
});

test('execution linked compliance and energy rows cannot be forged or duplicated', function () {
    $service = app(IsoExecutionQuestionnaire::class);
    $sources = ['parties' => [['kod' => 'W-1', 'nazwa' => 'Organ', 'wymagania' => 'Obowiązek', 'zgodnosc' => 'tak', 'consultant' => ['legal_basis' => 'Podstawa']], ['kod' => 'W-2', 'zgodnosc' => 'nie']]];
    $saved = $service->normalize('9-1-2', ['WYM' => ['W-1' => ['wymaganie' => 'Sfałszowane', 'dowod' => 'Protokół'], 'W-2' => []]], $sources, false);
    expect($saved['WYM'])->toHaveCount(1);
    expect($saved['WYM']['W-1']['wymaganie'])->toBe('Obowiązek');
    expect($saved['WYM']['W-1']['dowod'])->toBe('Protokół');
    $sources = ['cards' => [['nr' => 'EnPI.1', 'nazwa' => 'Wskaźnik', 'wartoscEnB' => '5 kWh/szt.', 'okresBazowy' => '2025']]];
    $saved = $service->normalize('9-1-1', [], $sources, false);
    expect(array_values($saved['WSKAZNIKI'])[0]['enb'])->toBe('5 kWh/szt.');
    expect(array_values($saved['WSKAZNIKI'])[0]['kod'])->toBe('EnPI.1');
});

test('execution calculations never substitute missing data with zero', function () {
    $service = app(IsoExecutionQuestionnaire::class);
    $answers = ['ZAKUPY' => [['przedmiot' => 'Sprężarka', 'cenaEnergii' => '890', 'lata' => '15', 'oferty' => [['nazwa' => 'A', 'tryb' => 'roczne', 'roczne' => '1660', 'cena' => '330000', 'inne' => '0', 'minimalne' => 'tak'], ['nazwa' => 'B', 'tryb' => 'roczne', 'roczne' => '1600', 'cena' => '375000', 'inne' => '0']]]]];
    $rows = $service->calculations('8-3', $answers);
    expect($rows[0]['value'])->toBe(22491000.0);
    expect($rows[0]['value'] - $rows[1]['value'])->toBe(756000.0);
    unset($answers['ZAKUPY'][0]['oferty'][0]['inne']);
    expect($service->calculations('8-3', $answers)[0]['value'])->toBeNull();
    expect($service->calculations('9-1-1', ['MODEL' => ['a' => '0', 'b' => '0', 'c' => '0'], 'OKRESY' => [['x1' => '1', 'x2' => '1', 'rzeczywiste' => '5']]])[0]['value'])->toBeNull();
    expect(IsoExecutionQuestionnaire::number('1 234,56'))->toBe(1234.56);
    expect(IsoExecutionQuestionnaire::number('5 kWh'))->toBeNull();
});

test('execution nonconformity closure and audit impartiality require evidence', function () {
    $service = app(IsoExecutionQuestionnaire::class);
    expect(fn () => $service->normalize('10-1', ['KARTY' => [['decyzja' => 'zamknac', 'terminDowodu' => today()->addDay()->toDateString(), 'dataSprawdzenia' => today()->toDateString()]]], [], false))->toThrow(ValidationException::class);
    expect(fn () => $service->validateContent('9-2', ['PROGRAM' => [['nr' => 'P1']], 'RAPORTY' => [['wlasnaPraca' => 'tak', 'pozycja' => 'P1', 'ustalenia' => [[]]]]], ['ready' => true], true))->toThrow(ValidationException::class);
    expect(fn () => $service->normalize('9-1-1', ['MODEL' => ['a' => 'NaN']], [], false))->toThrow(ValidationException::class);
});

test('execution nine questionnaires follow existing approval PDF and correction workflow', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    [$audit, $client, $staff, $profile] = systemFixture();
    $sources = ['hash' => str_repeat('e', 64), 'ready' => true, 'approved' => [], 'published' => [], 'upstream' => [], 'cards' => [['nr' => 'E1', 'nazwa' => 'EnPI', 'wartoscEnB' => '5', 'okresBazowy' => '2025', 'warunkiPorownania' => 'Porównywalne']]];
    $service = Mockery::mock(IsoSystemQuestionnaire::class)->makePartial();
    $service->shouldReceive('sources')->andReturn($sources);
    app()->instance(IsoSystemQuestionnaire::class, $service);
    foreach (array_keys(IsoExecutionQuestionnaire::TITLES) as $section) {
        $answers = executionAnswers($section, $sources);
        $this->actingAs($staff)->get(route('audits.system.show', [$audit, $profile, $section]))->assertOk()->assertSee('data-exec-list', false);
        $clientUrl = route('client.audits.system.update', [$audit, $profile, $section]);
        $staffUrl = route('audits.system.update', [$audit, $profile, $section]);
        $this->actingAs($client)->post($clientUrl, ['operation' => 'submit', 'lock_version' => 0, 'source_hash' => $sources['hash'], 'complete_form' => 1, 'answers' => $answers])->assertSessionHasNoErrors();
        $review = IsoSystemReview::where('section', $section)->sole();
        expect($review->status)->toBe('submitted');
        $this->actingAs($staff)->post($staffUrl, ['operation' => 'approve', 'lock_version' => 1, 'source_hash' => $sources['hash']])->assertSessionHasErrors('consultant');
        $notes = ['WNIOSKI' => [['znaczenie' => 'WEWNETRZNY SEKRET', 'decyzja' => 'Decyzja', 'dzialanie' => 'Działanie', 'sprawdzenie' => 'Sprawdzenie']]];
        $this->post($staffUrl, ['operation' => 'consultant', 'lock_version' => 1, 'source_hash' => $sources['hash'], 'consultant' => $notes])->assertSessionHasNoErrors();
        $this->post($staffUrl, ['operation' => 'approve', 'lock_version' => 2, 'source_hash' => $sources['hash']])->assertSessionHasNoErrors();
        $this->post(route('audits.system.pdf', [$audit, $profile, $section]), ['lock_version' => 3])->assertRedirect()->assertSessionHasNoErrors();
        expect($review->fresh()->document_id)->not->toBeNull();
        $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, $section]))->assertOk()->assertDontSee('WEWNETRZNY SEKRET')->assertDontSee('id="system-example"', false);
        $changed = $answers;
        $field = collect($service->sections($section, $answers, $sources))->flatMap(fn ($group) => $group['fields'])->first(fn ($field) => ! $field['readonly'] && $field['type'] === 'textarea');
        Arr::set($changed, $field['key'], 'Zmieniony opis po przeglądzie');
        $this->actingAs($staff)->post($staffUrl, ['operation' => 'save', 'lock_version' => 3, 'source_hash' => $sources['hash'], 'complete_form' => 1, 'answers' => $changed])->assertSessionHasNoErrors();
        expect($review->fresh()->client_approval)->toBeNull();
        expect($review->fresh()->document_id)->toBeNull();
    }
});

test('execution access and transitive sources stay within the audit and site', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $service = app(IsoSystemQuestionnaire::class);
    $hash = $service->sources($profile, '10-2')['hash'];
    IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => '8-1', 'status' => 'editing', 'source_hash' => str_repeat('a', 64), 'answers' => ['KARTY' => [['seu' => 'Nowe SEU']]]]);
    expect($service->sources($profile, '10-2')['hash'])->not->toBe($hash);
    $outsider = User::factory()->create();
    $outsider->assignRole('client_admin');
    $this->actingAs($outsider)->get(route('client.audits.system.show', [$audit, $profile, '10-2']))->assertNotFound();
    $this->actingAs($staff)->post(route('audits.system.update', [$audit, $profile, '10-2']), ['operation' => 'save', 'lock_version' => 0, 'source_hash' => $hash, 'complete_form' => 1, 'answers' => ['META' => []]])->assertConflict();
});
