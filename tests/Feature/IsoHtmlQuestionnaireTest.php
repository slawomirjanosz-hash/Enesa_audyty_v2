<?php

use App\Models\IsoFactorReview;
use App\Models\IsoSystemReview;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoHtmlQuestionnaire;
use App\Services\IsoHtmlState;
use App\Services\IsoPlantQuestionnaire;
use App\Services\IsoPlatformRelease;
use App\Services\IsoSystemQuestionnaire;

test('supplied HTML remains byte identical to the reviewed source package', function () {
    $html = app(IsoHtmlQuestionnaire::class);
    expect($html->manifest())->toHaveCount(28);
    foreach ($html->manifest() as $entry) {
        expect(hash('sha256', $html->document($entry['section'])))->toBe($entry['sha256']);
    }
    expect($html->document('4-1'))->toContain('1. Klimat i stan prawny', '2. Czynniki zewnętrzne', '3. Czynniki wewnętrzne', 'Specyfikacja dla Sławka');
});

test('latest 4.1 source is selected while the previous source and persistence contract stay intact', function () {
    $html = app(IsoHtmlQuestionnaire::class);
    $entry = collect($html->manifest())->firstWhere('section', '4-1');
    expect($entry['release'])->toBe('2026-10-21');
    expect($html->document('4-1'))->toContain('function brakiKlienta()', 'var b = brakiKlienta();');
    $previous = collect(json_decode(file_get_contents(resource_path('iso50001/html-2026-10-18/manifest.json')), true))->firstWhere('section', '4-1');
    expect(hash_file('sha256', resource_path('iso50001/html-2026-10-18/4-1.html')))->toBe($previous['sha256']);
    expect($entry['tabs'])->toHaveCount(7)->and($entry['state'])->toContain('PODSUM', 'WNIOSKI', 'ID_SEQ');
});

test('each clause page uses the source page and actual profile chooser instead of demo progress', function (string $section) {
    $html = app(IsoHtmlQuestionnaire::class)->point($section, '<div>REAL-PROFILES</div>', 'test-channel', 'Rzeczywisty klient', false);
    expect($html)->toContain('REAL-PROFILES', 'Rzeczywisty klient', 'Specyfikacja dla Sławka')->not->toContain('ENESA_PROFILE_CARDS');
})->with(array_merge(['4-1', '4-2'], array_keys(IsoSystemReview::TITLES)));

test('HTML editor round trips stable answers through every system questionnaire', function (string $section) {
    $release = app(IsoPlatformRelease::class);
    $bridge = app(IsoHtmlState::class);
    $service = app(IsoSystemQuestionnaire::class);
    $sources = $release->demoSources();
    $answers = $release->answers($section, $sources);
    $state = $bridge->fromAnswers($section, $answers, $sources);
    $restored = $bridge->toAnswers($section, $state, $sources, $answers);
    expect($service->normalize($section, $restored, $sources, false))->toBe($service->normalize($section, $answers, $sources, false));
})->with(array_keys(IsoSystemReview::TITLES));

test('source-only fields and source status wording survive canonical projection', function () {
    $bridge = app(IsoHtmlState::class);
    $answers = ['CELE' => [['nr' => 'C1', 'status' => 'plan', 'opis' => 'Cel']], '_html' => ['CELE' => [['nr' => 'C1', 'status' => 'zatwierdzone', 'notatka' => 'Szczegół z HTML']]]];
    $state = $bridge->fromAnswers('6-2', $answers);
    expect($state['CELE'][0]['status'])->toBe('zatwierdzone')->and($state['CELE'][0]['notatka'])->toBe('Szczegół z HTML');
    $answers['CELE'][0]['status'] = 'wtoku';
    expect($bridge->fromAnswers('6-2', $answers)['CELE'][0]['status'])->toBe('wtoku');
});

test('JavaScript numeric key enumeration cannot reorder existing grouped SEU cards', function () {
    $release = app(IsoPlatformRelease::class);
    $bridge = app(IsoHtmlState::class);
    $sources = $release->demoSources();
    $answers = $release->answers('6-3', $sources);
    $state = $bridge->fromAnswers('6-3', $answers, $sources);
    uksort($state['SEU'], fn ($left, $right) => (int) str_starts_with((string) $left, 'G:') <=> (int) str_starts_with((string) $right, 'G:'));
    expect(array_column($bridge->toAnswers('6-3', $state, $sources, $answers)['SEU'], 'nr'))->toBe(array_column($answers['SEU'], 'nr'));
});

test('source purchase price zero is preserved and a negative flag does not supply a recommendation', function () {
    $out = app(IsoHtmlState::class)->toAnswers('8-3', ['META' => ['cenaEnergii' => '800'], 'ZAKUPY' => [['cenaEnergii' => 0, 'porownanieNd' => 'nie', 'porownanieNdPowod' => 'Nie używać jako rekomendacji']]], []);
    expect($out['ZAKUPY'][0]['cenaEnergii'])->toBe('0');
    expect($out['ZAKUPY'][0]['rekomendacja'] ?? '')->toBe('');
});

test('custom management review decisions keep their name across the source boolean flag', function () {
    $bridge = app(IsoHtmlState::class);
    $answers = ['DECYZJE' => [['kod' => 'W1', 'nazwa' => 'Własna decyzja', 'wlasna' => 'Własna decyzja']]];
    $state = $bridge->fromAnswers('9-3', $answers);
    expect($state['DECYZJE'][0]['wlasna'])->toBeTrue();
    $state['DECYZJE'][0]['nazwa'] = 'Zmieniona własna decyzja';
    expect($bridge->toAnswers('9-3', $state, [], $answers)['DECYZJE'][0]['wlasna'])->toBe('Zmieniona własna decyzja');
});

test('edits to source stakeholder aliases replace stored text without renumbering questions', function () {
    $bridge = app(IsoHtmlState::class);
    $answers = ['parties' => ['WYM-31' => ['decyzja' => 'potwierdzona', 'wymagania' => 'Poprzedni zapis', 'jak' => 'Dawny sposób']],
        'custom' => [['id' => '9aab897b-81e6-4474-9f1d-7ce330a80440', 'nazwa' => 'Własny odbiorca', 'typ' => 'zewnętrzna', 'wymagania' => 'Stare wymaganie', 'jak' => 'Stary sposób']]];
    $state = $bridge->fromAnswers('4-2', $answers);
    $state['W']['WYM-25']['wymaganie'] = 'Nowy zapis';
    $state['WLASNE'][0]['wymaganie'] = 'Nowe wymaganie';
    $state['WLASNE'][0]['sposob'] = 'Nowy sposób';
    $out = $bridge->toAnswers('4-2', $state, [], $answers);
    expect($out['parties']['WYM-31']['wymagania'])->toBe('Nowy zapis');
    expect($out['custom'][0])->toMatchArray(['id' => $answers['custom'][0]['id'], 'wymagania' => 'Nowe wymaganie', 'jak' => 'Nowy sposób']);
});

test('baseline revision history maps between source cards and stable Excel fields', function () {
    $bridge = app(IsoHtmlState::class);
    $revision = ['enpi' => 'E1', 'data' => '2026-10-10', 'powod' => 'a', 'opis' => 'Zmiana zmiennej', 'stara' => '10', 'nowa' => '12', 'poprawa' => '5%', 'zatwierdzil' => 'Dyrektor'];
    $answers = ['KARTY' => [['nr' => 'E1']], 'REWIZJE' => [$revision]];
    $state = $bridge->fromAnswers('6-5', $answers);
    expect($state['KARTY'][0]['historia'][0]['uzasadnienie'])->toBe('Zmiana zmiennej');
    expect($bridge->toAnswers('6-5', $state, [], $answers)['REWIZJE'][0])->toMatchArray($revision);
});

test('new source rows cannot reuse the identity of a deleted or moved plan position', function () {
    $bridge = app(IsoHtmlState::class);
    $previous = ['POZYCJE' => [['nr' => '1', 'parametr' => 'Pierwszy'], ['nr' => '2', 'parametr' => 'Drugi']], '_html' => ['_ids' => ['POZYCJE' => 5]]];
    $state = ['POZYCJE' => [['nr' => '2', 'parametr' => 'Drugi'], ['parametr' => 'Nowy']]];
    $out = $bridge->toAnswers('6-6', $state, [], $previous);
    expect($out['POZYCJE'][0]['nr'])->toBe('2')->and($out['POZYCJE'][1]['nr'])->toBe('6');
});

test('HTML factor save uses ordinary locks and never accepts client consultant content', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $payload = factorInput($profile);
    $state = app(IsoHtmlState::class)->fromAnswers('4-1', $payload['answers']);
    $state['WNIOSKI'] = [['id' => 'WN-1', 'czynniki' => ['KTX-WT-01'], 'znaczenie' => 'Wniosek klienta']];
    $state['SWOT'] = ['S' => 'Prywatne pole'];
    $url = route('client.audits.factors.update', [$audit, $profile]);
    $this->actingAs($client)->post($url, ['operation' => 'save', 'lock_version' => 0, 'source_hash' => $payload['source_hash'], 'html_state' => json_encode($state)])
        ->assertRedirect()->assertSessionHasNoErrors();
    $review = IsoFactorReview::firstOrFail();
    expect($review->answers['FAKT_KLIMAT_ISTOTNY'])->toBe('nie')->and($review->swot['wnioski'][0]['scope'])->toBe('client')->and($review->swot)->not->toHaveKey('strengths');
    expect($review->answers['_html'])->not->toHaveKeys(['WNIOSKI', 'SWOT', 'ODP', 'PROFIL']);
    $this->post($url, ['operation' => 'save', 'lock_version' => 0, 'source_hash' => $payload['source_hash'], 'html_state' => json_encode($state)])->assertStatus(409);
    expect($review->fresh()->lock_version)->toBe(1);
});

test('client HTML receives real scoped facts and never receives private analysis', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $service = app(IsoFactorQuestionnaire::class);
    $review = IsoFactorReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id, 'status' => 'editing', 'lock_version' => 0, 'source_hash' => $service->hash($profile), 'answers' => [], 'swot' => ['strengths' => 'SECRET-INTERNAL-HTML-ANALYSIS']]);
    $this->actingAs($client)->get(route('client.audits.factors.show', [$audit, $profile]))
        ->assertOk()->assertSee('sandbox="allow-scripts allow-modals allow-downloads"', false)
        ->assertDontSee('SECRET-INTERNAL-HTML-ANALYSIS');
    $this->actingAs($staff)->get(route('audits.factors.show', [$audit, $profile]))->assertOk()->assertSee('SECRET-INTERNAL-HTML-ANALYSIS');
});

test('profile source answers preserve unknown answers and repeater identities', function () {
    $bridge = app(IsoHtmlState::class);
    $release = app(IsoPlatformRelease::class);
    $profile = $release->profile();
    $state = $bridge->fromAnswers('intro', $profile->answers);
    $answers = $bridge->toAnswers('intro', $state, [], $profile->answers);
    $plant = app(IsoPlantQuestionnaire::class);
    expect($plant->normalize($answers, $profile->definition, $profile->answers, 1, false))->toBe($plant->normalize($profile->answers, $profile->definition, $profile->answers, 1, false));
});

test('modern profile HTML saves identity fields and the literal unknown select choice', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $answers = app(IsoPlatformRelease::class)->profile()->answers;
    $profile->update(['answers' => $answers, 'status' => 'editing', 'client_approval' => null, 'auditor_approval' => null]);
    $state = app(IsoHtmlState::class)->fromAnswers('intro', $answers);
    $state['ODP']['FAKT_SEZONOWOSC'] = 'nie wiem';
    $this->actingAs($client)->post(route('client.audits.plant-profile.update', [$audit, $profile]), [
        'operation' => 'save', 'lock_version' => 0, 'as_of_date' => today()->toDateString(), 'html_state' => json_encode($state),
        'html_meta' => ['profile.contact_person' => 'Kierownik energetyki'],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($profile->fresh()->answers['FAKT_SEZONOWOSC']['value'])->toBe('nie wiem');
    expect($profile->fresh()->answers['profile.contact_person']['value'])->toBe('Kierownik energetyki');
});

test('HTML can submit freshly answered climate-dependent factors without a second save cycle', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $input = factorInput($profile);
    $this->actingAs($client)->post(route('client.audits.factors.update', [$audit, $profile]), array_replace($input, ['operation' => 'save']))->assertSessionHasNoErrors();
    $answers = array_replace($input['answers'], ['FAKT_KLIMAT_ISTOTNY' => 'tak', 'climate_reason' => 'Prognozy na przyszłe lata wskazują wzrost zapotrzebowania na chłodzenie.']);
    $service = app(IsoFactorQuestionnaire::class);
    foreach ($service->factors($service->facts($profile), $answers) as $factor) {
        if ($factor['visible'] && $factor['rodzaj'] !== 'AUTO') {
            $answers['factors'][$factor['kod']] = ['decyzja' => 'potwierdzony', 'tresc' => $factor['tresc'], 'istotnosc' => 'S'];
        }
    }
    $state = app(IsoHtmlState::class)->fromAnswers('4-1', $answers);
    $this->post(route('client.audits.factors.update', [$audit, $profile]), ['operation' => 'submit', 'lock_version' => 1, 'source_hash' => $input['source_hash'], 'html_state' => json_encode($state)])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(IsoFactorReview::firstOrFail()->status)->toBe('submitted');
});
