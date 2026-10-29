<?php

use App\Models\IsoFactorReview;
use App\Models\IsoSystemReview;
use App\Services\IsoChapterFourReport;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoQuestionnaireWorkbook;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('custom context factors require significance and the completion indicator includes high significance evidence', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $service = app(IsoFactorQuestionnaire::class);
    $facts = $service->facts($profile);
    $input = factorInput($profile)['answers'];
    $input['custom'] = [['id' => (string) Str::uuid(), 'tresc' => 'Własny czynnik', 'wplyw' => '+', 'wymiar' => 'wewn. techniczny', 'skutek' => 'Ocena możliwości poprawy']];
    expect($service->normalize($input, $facts, false)['custom'])->toHaveCount(1);
    try {
        $service->normalize($input, $facts, true);
        $this->fail('Missing custom significance accepted');
    } catch (ValidationException $error) {
        expect($error->errors())->toHaveKey('custom.0.istotnosc');
    }
    expect($service->progress($facts, $input)['percent'])->toBeLessThan(100);
    $input['custom'][0]['istotnosc'] = 'W';
    expect($service->progress($facts, $input)['percent'])->toBeLessThan(100);
    $input['custom'][0] += ['podstawa' => ['rodzaj' => 'pomiar', 'zrodlo' => 'Raport pomiarowy', 'okres' => '2026'], 'znaczenie' => 'Znaczące straty', 'decyzja_w' => 'Usunąć straty', 'dzialanie' => 'Modernizacja układu', 'sprawdzenie' => 'Pomiar po modernizacji'];
    expect($service->normalize($input, $facts, true)['custom'][0])->toEqual($input['custom'][0]);
    expect($service->progress($facts, $input)['percent'])->toBe(100);
});

test('chapter four energy suggestions keep LPG fleet distinct from vehicle fuels', function () {
    $service = app(IsoSystemQuestionnaire::class);
    $energy = $service->energy(['facts' => ['FAKT_FLOTA' => 'tak', 'FAKT_NOSNIKI' => [['c0' => 'LPG']]]]);
    expect($energy['LPG']['suggested'])->toBeTrue()->and($energy['PALIWA']['suggested'])->toBeFalse();
    $energy = $service->energy(['facts' => ['FAKT_FLOTA' => 'tak', 'FAKT_NOSNIKI' => [['c0' => 'LPG'], ['c0' => 'olej napędowy']]]]);
    expect($energy['PALIWA']['suggested'])->toBeTrue();
});

test('chapter four native climate decisions use backend visibility and can be submitted together', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $service = app(IsoFactorQuestionnaire::class);
    $payload = factorInput($profile);
    $payload['answers']['FAKT_KLIMAT_ISTOTNY'] = 'tak';
    $payload['answers']['climate_reason'] = 'Prognozy wieloletnie wskazują wzrost ryzyka przerw w pracy zakładu.';
    foreach ($service->factors($service->facts($profile), $payload['answers']) as $factor) {
        if (isset($factor['climate_visibility'])) {
            expect($factor['climate_visibility']['tak'])->toBe($factor['visible']);
        }
        if ($factor['visible'] && $factor['rodzaj'] !== 'AUTO') {
            $payload['answers']['factors'][$factor['kod']] = ['decyzja' => 'potwierdzony', 'tresc' => $factor['tresc'], 'istotnosc' => 'S', 'climate_basis' => 'tak'];
        }
    }
    $this->actingAs($client)->post(route('client.audits.factors.update', [$audit, $profile]), $payload)->assertSessionHasNoErrors();
    expect(IsoFactorReview::sole()->status)->toBe('submitted');
});

test('chapter four system keeps process owners and requires measured comparison instead of declaration', function () {
    $service = app(IsoSystemQuestionnaire::class);
    $sources = ['facts' => [], 'ready' => true];
    $answers = systemAnswers('4-4', $sources);
    $answers['ODP']['SYS-01'] = 'nadbudowa';
    $fields = collect($service->sections('4-4', $answers, $sources)['procesy']['fields'])->keyBy('key');
    expect($service->visible($fields['PROC.PR-05.wlasciciel'], $answers))->toBeTrue();
    $answers = systemAnswers('4-4', $sources);
    $answers['WYNIK']['wartoscOceny'] = 'Jest coraz lepiej';
    expect(fn () => $service->normalize('4-4', $answers, $sources, true))->toThrow(ValidationException::class);
    $answers['WYNIK']['wartoscOceny'] = '0,119 kWh/Nm³';
    $answers['WLASNE'] = [['nazwa' => 'Proces własny', 'klauzula' => '8.3', 'wlasciciel' => 'Zakupy', 'wynik' => 'Klauzule umowne', 'forma' => 'opis']];
    $saved = $service->normalize('4-4', $answers, $sources, true);
    expect($saved['WLASNE'][0]['id'])->toStartWith('WL-');
    expect($service->normalize('4-4', $saved, $sources, true)['WLASNE'][0]['id'])->toBe($saved['WLASNE'][0]['id']);
    $saved['WLASNE'][1] = $saved['WLASNE'][0];
    expect(fn () => $service->normalize('4-4', $saved, $sources, false))->toThrow(ValidationException::class);
});

test('chapter four factor evidence and edited consequence survive form and Excel round trip', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $review = IsoFactorReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id, 'source_hash' => app(IsoFactorQuestionnaire::class)->hash($profile), 'lock_version' => 0, 'status' => 'editing']);
    $service = app(IsoFactorQuestionnaire::class);
    $input = factorInput($profile)['answers'];
    $input['factors']['KTX-WT-01'] += ['skutek' => 'Kampania pomiarowa', 'podstawa' => ['rodzaj' => 'pomiar', 'zrodlo' => 'Protokół 12', 'okres' => '2026']];
    $normalized = $service->normalize($input, $service->facts($profile), true);
    $review->update(['answers' => $normalized, 'basis' => array_column($service->factors($service->facts($profile), $normalized), 'basis', 'kod')]);
    expect($service->formAnswers($normalized)['factors']['KTX-WT-01']['podstawa'])->toBe($normalized['factors']['KTX-WT-01']['podstawa']);
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'factors', $review);
    workbookUpload($book, function ($file) use ($profile, $review, $normalized) {
        $read = app(IsoQuestionnaireWorkbook::class)->read($file->getPathname(), $profile, 'factors', $review);
        expect($read['answers']['factors']['KTX-WT-01']['skutek'])->toBe('Kampania pomiarowa');
        expect($read['answers']['factors']['KTX-WT-01']['podstawa'])->toBe($normalized['factors']['KTX-WT-01']['podstawa']);
    });
});

test('chapter four high significance needs a source and approved decision needs confirmation', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $service = app(IsoFactorQuestionnaire::class);
    $input = factorInput($profile)['answers'];
    $input['factors']['KTX-WT-01']['istotnosc'] = 'W';
    foreach (['znaczenie', 'decyzja_w', 'dzialanie', 'sprawdzenie'] as $key) {
        $input['factors']['KTX-WT-01'][$key] = 'Sprawdzone ustalenie';
    }
    try {
        $service->normalize($input, $service->facts($profile), true);
        $this->fail('Missing evidence accepted');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('factors.KTX-WT-01.podstawa.zrodlo');
    }
    $rules = app(IsoChapterFourReport::class)->rules('analysis');
    expect(Validator::make(['analysis' => ['PODSUM' => ['decyzje' => [['status' => 'zatwierdzona']]]]], $rules)->fails())->toBeTrue();
});

test('chapter four scope consultant notes remain staff only and survive approval and PDF rendering', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $service = app(IsoSystemQuestionnaire::class);
    $sources = $service->sources($profile, '4-3');
    $answers = systemAnswers('4-3', $sources);
    $url = route('audits.system.update', [$audit, $profile, '4-3']);
    $this->actingAs($client)->post(route('client.audits.system.update', [$audit, $profile, '4-3']), ['operation' => 'submit', 'lock_version' => 0, 'source_hash' => $sources['hash'], 'answers' => $answers, 'complete_form' => 1])->assertSessionHasNoErrors();
    $review = IsoSystemReview::where('section', '4-3')->sole();
    $notes = ['PODSUM' => ['ocena' => 'Zakres wymaga decyzji Zarządu.'], 'WNIOSKI' => [['czynniki' => ['zakres', 'granice'], 'znaczenie' => 'Spójny zakres', 'decyzja' => 'Objąć oba obiekty', 'dzialanie' => 'Sprawdzić umowy', 'sprawdzenie' => 'Kontrola granic']]];
    $payload = ['operation' => 'consultant', 'lock_version' => $review->lock_version, 'source_hash' => $sources['hash'], 'consultant' => $notes];
    $this->post(route('client.audits.system.update', [$audit, $profile, '4-3']), $payload)->assertForbidden();
    $this->actingAs($staff)->post($url, $payload)->assertSessionHasNoErrors();
    $review->refresh();
    expect($review->consultant['WNIOSKI'][0]['czynniki'])->toBe(['zakres', 'granice']);
    $this->post($url, ['operation' => 'approve', 'lock_version' => $review->lock_version, 'source_hash' => $sources['hash']])->assertSessionHasNoErrors();
    $review->refresh();
    $html = view('audits.system.pdf', ['review' => $review, 'profile' => $profile, 'sources' => $sources, 'section' => '4-3', 'title' => IsoSystemReview::TITLES['4-3'], 'service' => $service, 'sections' => $service->sections('4-3', $review->answers, $sources)])->render();
    expect($html)->toContain('Zakres wymaga decyzji Zarządu.', 'Objąć oba obiekty', 'zakres, granice', '2. Zakres systemu zarządzania energią', '3. Granice fizyczne i organizacyjne', $answers['ZAKRES']['opis'])->not->toContain('Specyfikacja dla Sławka');
});
