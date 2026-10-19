<?php

use App\Models\IsoPlantProfile;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoHtmlState;
use App\Services\IsoPlantCalculations;
use App\Services\IsoPlantQuestionnaire;
use App\Services\IsoQuestionnaireWorkbook;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

function energyAnswers(array $rows): array
{
    return ['FAKT_NOSNIKI' => ['value' => $rows]];
}
function energyRow(mixed $quantity, string $unit = 'MWh', string $carrier = 'LPG'): array
{
    return ['c0' => $carrier, 'c1' => $quantity, 'c2' => $unit];
}

test('every supported carrier accepts all universal energy units without new calorific factors', function () {
    $calc = app(IsoPlantCalculations::class);
    foreach (IsoPlantCalculations::FACTORS as $carrier => $old) {
        foreach (['kWh' => 0.0036, 'MWh' => 3.6, 'GJ' => 1] as $unit => $factor) {
            expect($calc->energy([energyRow('20000', $unit, $carrier)])['value'])->toBe(round(20 * $factor, 6));
        }
        foreach ($old as $unit => $factor) {
            expect(IsoPlantCalculations::factors()[$carrier][$unit])->toBe($factor);
        }
    }
    expect($calc->energy([energyRow(1, 'm³')])['value'])->toBeNull();
});

test('energy thresholds select the correct contextual factor including both inclusive boundaries', function (string $quantity, string $unit, float $tj, string $expected) {
    $plant = app(IsoPlantQuestionnaire::class);
    $answers = $plant->normalize(energyAnswers([energyRow($quantity, $unit)]), $plant->definition(), [], 1, false);
    expect($answers['ZUZYCIE_TJ']['value'])->toBe($tj);
    $facts = $plant->facts($plant->definition(), $answers);
    $factors = collect(app(IsoFactorQuestionnaire::class)->factors($facts, []))->keyBy('kod');
    expect($factors[$expected]['visible'])->toBeTrue();
    expect($factors[$expected === 'KTX-ZR-01' ? 'KTX-ZR-02' : 'KTX-ZR-01']['visible'])->toBeFalse();
})->with([
    ['20000', 'MWh', 72.0, 'KTX-ZR-02'], ['1000000', 'MWh', 3600.0, 'KTX-ZR-01'],
    ['10000', 'GJ', 10.0, 'KTX-ZR-02'], ['85000', 'GJ', 85.0, 'KTX-ZR-02'], ['85000,001', 'GJ', 85.000001, 'KTX-ZR-01'], ['85000,0001', 'GJ', 85.0000001, 'KTX-ZR-01'],
]);

test('regulatory factors three through seven use independent profile answers at every consumption level', function () {
    foreach ([0, 72, 3600, null] as $tj) {
        foreach (['tak' => true, 'nie' => false] as $answer => $visible) {
            $facts = ['ZUZYCIE_TJ' => $tj, 'FAKT_AUDYT_ENERG' => $answer, 'FAKT_ETS' => $answer, 'FAKT_CSRD' => $answer, 'FAKT_UDT' => $answer, 'FAKT_FGAZ' => $answer];
            $factors = collect(app(IsoFactorQuestionnaire::class)->factors($facts, []))->keyBy('kod');
            foreach (range(3, 7) as $i) {
                expect($factors['KTX-ZR-0'.$i]['visible'])->toBe($visible);
            }
        }
    }
});

test('energy keeps decimal comma zero and mixed carriers but never reports partial totals', function () {
    $calc = app(IsoPlantCalculations::class);
    expect($calc->energy([energyRow('20 000,5'), energyRow('0', 'GJ', 'biomasa'), energyRow('1000', 'kWh', 'węgiel')])['value'])->toBe(72.0054);
    expect($calc->energy([energyRow(0)])['value'])->toBe(0.0);
    foreach ([null, '', 'nie wiem', '12abc', -1, '1,2,3'] as $quantity) {
        $result = $calc->energy([energyRow(20), energyRow($quantity)]);
        expect($result['value'])->toBeNull()->and($result['detail'])->toContain('Wiersz 2', 'LPG / MWh', 'Nie podajemy sumy częściowej');
    }
    foreach ([[], [['c0' => 'LPG']], [energyRow(1, 'l', 'węgiel')], [energyRow(1, 'MWh', 'nieznany')]] as $rows) {
        expect($calc->energy($rows)['value'])->toBeNull();
    }
    expect($calc->energy([energyRow(20)], true)['value'])->toBeNull();
    expect($calc->energy([energyRow(1, 'l', 'węgiel')], true)['errors'])->toBe([]);
    expect($calc->energy([energyRow(20), []])['value'])->toBe(0.072);
    expect($calc->energy([energyRow('1e3', 'kWh')])['value'])->toBe(0.0036);
    $plant = app(IsoPlantQuestionnaire::class);
    $unknown = $plant->normalize(['FAKT_NOSNIKI' => ['unknown' => true, 'value' => [energyRow(1, 'l', 'węgiel')]]], $plant->definition(), [], 1, false);
    expect($unknown['ZUZYCIE_TJ']['value'])->toBeNull()->and($unknown['FAKT_NOSNIKI']['unknown'])->toBeTrue();
    expect(fn () => $plant->normalize(energyAnswers([energyRow(1, 'l', 'węgiel')]), $plant->definition(), [], 1, false))->toThrow(ValidationException::class);
});

test('saved source HTML profile reopens with the same facts at point 4.1 and Excel export', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $profile->update(['status' => 'editing', 'client_approval' => null, 'auditor_approval' => null]);
    $state = app(IsoHtmlState::class)->fromAnswers('intro', $profile->answers);
    $state['ODP']['FAKT_NOSNIKI'] = [energyRow('20 000,0')];
    $this->actingAs($client)->post(route('client.audits.plant-profile.update', [$audit, $profile]), [
        'operation' => 'save', 'lock_version' => 0, 'as_of_date' => today()->toDateString(), 'html_state' => json_encode($state),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $profile->refresh();
    expect($profile->answers['ZUZYCIE_TJ']['value'])->toEqual(72);
    $this->get(route('client.audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('iso-plant-energy.js', false);
    $profile->update(['status' => 'approved', 'client_approval' => ['user_id' => $client->id, 'name' => $client->name, 'at' => now()], 'auditor_approval' => ['user_id' => $staff->id, 'name' => $staff->name, 'at' => now()]]);
    $this->actingAs($staff)->post(route('audits.plant-profile.pdf', [$audit, $profile]), ['lock_version' => $profile->lock_version, 'preview' => 1])->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(view('audits.plant-profile.pdf', ['profile' => $profile, 'questionnaire' => app(IsoPlantQuestionnaire::class)])->render())->toContain('72');
    $response = $this->actingAs($staff)->get(route('audits.factors.show', [$audit, $profile]))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    foreach ($document->getElementsByTagName('iframe') as $frame) {
        if (! $frame->hasAttribute('srcdoc')) {
            continue;
        }
        preg_match('/var ENESA_HOST=(.*?);var localStorage/s', $frame->getAttribute('srcdoc'), $matches);
        $config = json_decode($matches[1], true);
        expect($config['context']['ODP']['ZUZYCIE_TJ'])->toEqual(72);
    }
    // A legacy persisted null must never replace a current computed value in Excel.
    $old = $profile->answers;
    $old['ZUZYCIE_TJ']['value'] = null;
    $profile->answers = $old;
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'plant');
    $rows = $book->getSheetByName('Pytania')->toArray();
    $energy = collect($rows)->first(fn ($row) => $row[0] === 'ZUZYCIE_TJ');
    expect((float) $energy[2])->toBe(72.0)->and($profile->answers['ZUZYCIE_TJ']['value'])->toBeNull();
    expect(IsoPlantProfile::find($profile->id)->answers['ZUZYCIE_TJ']['value'])->toEqual(72);
});

test('corrected calculation does not silently approve or regenerate an old approved result', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $answers = array_replace($profile->answers, energyAnswers([energyRow(20000)]), ['ZUZYCIE_TJ' => ['value' => null]]);
    $profile->update(['answers' => $answers]);
    $snapshot = $profile->fresh()->getRawOriginal();
    $this->actingAs($staff)->post(route('audits.plant-profile.pdf', [$audit, $profile]), ['lock_version' => 0])->assertStatus(409);
    expect($profile->fresh()->getRawOriginal())->toBe($snapshot);
    expect(Artisan::call('iso:check-energy'))->toBe(0);
    $report = json_decode(Artisan::output(), true);
    expect($report['affected'])->toBe(1)->and($report['profiles'][0]['computed_tj'])->toEqual(72);
    expect($profile->fresh()->getRawOriginal())->toBe($snapshot);
    $bad = array_replace($answers, energyAnswers([energyRow(1, 'm³')]));
    expect(app(IsoPlantCalculations::class)->energyChanged($profile->definition, $bad))->toBeFalse();
    $profile->update(['answers' => $bad]);
    $this->post(route('audits.plant-profile.pdf', [$audit, $profile]), ['lock_version' => 0])->assertStatus(409);
});

test('source lists and contextual conditions share the same stable select values', function () {
    $questions = collect(app(IsoPlantQuestionnaire::class)->questions(app(IsoPlantQuestionnaire::class)->definition()))->keyBy('key');
    foreach (app(IsoFactorQuestionnaire::class)->schema()['czynniki'] as $factor) {
        preg_match_all('/(FAKT_[A-Z0-9_]+)\s+(IN|!=|=)\s+(\([^)]*\)|[^ ]+)/', $factor['pokaz_gdy'], $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $question = $questions[$match[1]] ?? null;
            if (! $question || ! in_array($question['type'], ['select', 'multi'])) {
                continue;
            }
            foreach (explode(',', trim($match[3], '()')) as $value) {
                expect($question['options'])->toHaveKey(trim($value));
            }
        }
    }
});
