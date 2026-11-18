<?php

use App\Models\IsoSystemReview;
use App\Services\IsoSystemQuestionnaire;

test('enms source previews retain scoped values and labels without rendering JSON or changing records', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $matrixKey = app(IsoSystemQuestionnaire::class)->schema('5-3')['MAT_ZADANIA'][0]['kod'];
    $sources = [
        '4-3' => ['ZAKRES' => ['dzialania' => ['produkcja', 'stołówka / socjalne'], 'opis' => "Zakres testowy <b>Popowo & pomiary</b>\nDrugi akapit zakresu."]],
        '6-2' => ['CELE' => [['nr' => 'CEL-1', 'tresc' => 'Ograniczenie zużycia', 'rodzaj' => 'wynik', 'status' => 'wtoku']]],
        '6-3' => ['SEU' => [['nr' => 'SEU-1', 'nazwa' => 'Sprężarkownia', 'brakZmiennych' => 'nie']]],
        '6-4' => ['KARTY' => [['nr' => 'ENPI-1', 'nazwa' => 'Zużycie na jednostkę', 'postac' => 'jednostkowy', 'obszar' => 'organizacja']]],
        '6-5' => ['KARTY' => [['nr' => 'ENPI-1', 'okresBazowy' => '2025', 'wartoscEnB' => 0, 'modelBazowy' => null]]],
        '5-3' => ['ROLE' => [['id' => 'zarzad', 'skrot' => 'Z', 'nazwa' => 'Zarząd', 'MAT' => [$matrixKey => 'O']]]],
        '9-1-1' => ['WSKAZNIKI' => [['kod' => 'ENPI-1', 'nazwa' => 'Zużycie na jednostkę', 'biez' => 0]]],
    ];
    foreach ($sources as $section => $answers) {
        IsoSystemReview::updateOrCreate(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => $section], [
            'source_profile_id' => $profile->id, 'source_hash' => 'test-source', 'status' => 'editing', 'lock_version' => 1, 'answers' => $answers,
        ]);
    }
    foreach (['a', 'b', 'g', 'i', 'l'] as $code) {
        $s->saveDeclaration($w, $w->revision(), $s->sources($w)['hash'], $s->library->id('requirement', $code), [
            'stan' => 'czesciowo', 'plan' => ['opis' => 'Plan testowy', 'termin' => '2026-12-31'],
        ]);
    }
    $before = $w->records();
    $sourceBefore = $s->sources($w);
    foreach ([$staff, $client] as $actor) {
        $prefix = $actor->id === $client->id ? 'client.audits.' : 'audits.';
        $response = $this->actingAs($actor)->get(route($prefix.'enms.show', [$audit, $profile, 'tab' => 'obowiazki']));
        $response->assertOk()->assertSee('Zakres testowy <b>Popowo & pomiary</b>')->assertDontSee('<b>Popowo', false)
            ->assertSee('Obszary działalności')->assertSee('stołówka / socjalne')
            ->assertSee('Poprawa wyniku energetycznego')->assertSee('W toku')->assertSee('Wskaźnik jednostkowy')
            ->assertSee('Matryca odpowiedzialności')->assertSee('O — odpowiada')->assertSee('Wartość EnB wraz z jednostką')
            ->assertSee('<p class="enms-source-text">0</p>', false)->assertSee('<p class="enms-source-text">Nie podano</p>', false)
            ->assertSee('Wersja źródła: 1')->assertSee('Brak aktualnego zatwierdzonego dokumentu')
            ->assertDontSee('<pre>', false)->assertDontSee('&quot;dzialania&quot;', false)->assertDontSee('stołówka \\/ socjalne', false);
        $response->assertSeeInOrder(['Działania, procesy i jednostki objęte systemem', 'Drugi akapit zakresu.', 'Obszary działalności', 'stołówka / socjalne']);
    }
    expect($w->records())->toBe($before)->and($s->sources($w))->toBe($sourceBefore);
    if (getenv('ENMS_SOURCE_PREVIEW_BROWSER_QA')) {
        enmsBrowserExport('enms-browser-source-preview', [$audit, $client, $staff, $profile]);
    }
});
