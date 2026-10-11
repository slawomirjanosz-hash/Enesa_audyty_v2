<?php

use App\Models\IsoSectionDocument;
use App\Models\IsoSystemReview;
use App\Services\IsoPlanningQuestionnaire;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Validation\ValidationException;

test('planning schemas preserve all six source checklists and stable field identifiers', function () {
    $service = app(IsoSystemQuestionnaire::class);
    foreach (['6-1' => 20, '6-2' => 8, '6-3' => 15, '6-4' => 6, '6-5' => 6, '6-6' => 7] as $section => $count) {
        $schema = $service->schema($section);
        expect($schema['LISTA_'.str_replace('-', '', $section)])->toHaveCount($count);
        $answers = $service->seed($section, []);
        expect($service->sections($section, $answers, []))->not->toBeEmpty();
        expect($service->normalize($section, $answers, [], false))->toBeArray();
    }
});

test('energy balance normalizes Polish decimals and refuses negative quantities', function () {
    $service = app(IsoSystemQuestionnaire::class);
    $answers = ['NOSNIKI' => [['nazwa' => 'Gaz', 'biezacy' => '1 000,5', 'przelicznik' => '10,97']]];
    $saved = $service->normalize('6-3', $answers, [], false);
    expect($saved['NOSNIKI'][0]['biezacy'])->toBe('1000.5');
    expect(app(IsoPlanningQuestionnaire::class)->calculations('6-3', $saved)['energy'])->toBe(10.975485);
    $answers['NOSNIKI'][0]['biezacy'] = '-1';
    expect(fn () => $service->normalize('6-3', $answers, [], false))->toThrow(ValidationException::class);
});

test('planning refuses duplicate identifiers and unknown goal references', function () {
    $service = app(IsoSystemQuestionnaire::class);
    expect(fn () => $service->normalize('6-2', ['CELE' => [['nr' => 'C1'], ['nr' => 'C1']]], [], false))->toThrow(ValidationException::class);
    expect(fn () => $service->normalize('6-2', ['CELE' => [['nr' => 'C1']], 'ZADANIA' => [['doCelu' => 'C2']]], [], false))->toThrow(ValidationException::class);
});

test('shared energy cards preserve the other half and invalidate stale approvals', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $service = app(IsoSystemQuestionnaire::class);
    $planning = app(IsoPlanningQuestionnaire::class);
    foreach (range(1, 6) as $number) {
        $this->actingAs($staff)->get(route('audits.system.show', [$audit, $profile, '6-'.$number]))->assertOk();
        $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, '6-'.$number]))->assertOk()->assertDontSee('id="system-example"', false);
    }
    $save = function (string $section, array $answers, int $version = 0) use ($audit, $profile, $staff, $service) {
        return $this->actingAs($staff)->post(route('audits.system.update', [$audit, $profile, $section]), ['operation' => 'save', 'lock_version' => $version, 'source_hash' => $service->sources($profile, $section)['hash'], 'answers' => $answers, 'complete_form' => 1]);
    };
    $save('6-4', ['KARTY' => [['nr' => 'O-1', 'nazwa' => 'Energia zakładu']]])->assertSessionHasNoErrors();
    $hash = $service->sources($profile, '6-4')['hash'];
    $save('6-5', ['KARTY' => [['nr' => 'O-1', 'wartoscEnB' => '100 MWh']]])->assertSessionHasNoErrors();
    $sources = $service->sources($profile, '6-4');
    expect($sources['hash'])->not->toBe($hash);
    expect($sources['cards'][0]['nazwa'])->toBe('Energia zakładu');
    expect($sources['cards'][0]['wartoscEnB'])->toBe('100 MWh');
    $save('6-4', ['KARTY' => [['nr' => 'O-1', 'nazwa' => 'Zmieniona nazwa']]], 1)->assertSessionHasNoErrors();
    expect($service->sources($profile, '6-5')['cards'][0]['wartoscEnB'])->toBe('100 MWh');
    $save('6-5', ['KARTY' => [['nr' => 'O-2']]], 1)->assertSessionHasErrors('answers.KARTY.0.nr');
    expect($planning->projectCards('6-5', [], $service->sources($profile, '6-5'))['KARTY'][0]['nr'])->toBe('O-1');
});

test('energy review requires two approvals and publishes its document without consultant notes', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $service = app(IsoSystemQuestionnaire::class);
    $scope = IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => '4-3', 'status' => 'approved', 'answers' => [], 'source_hash' => $service->sources($profile, '4-3')['hash'], 'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id]]);
    $document = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => '4-3', 'scope' => 'client', 'title' => 'Zakres', 'document_year' => now()->year, 'version_number' => '1', 'original_filename' => 'scope.pdf', 'stored_path' => 'scope.pdf', 'mime_type' => 'application/pdf', 'size' => 4, 'content_base64' => base64_encode('%PDF'), 'uploaded_by' => $staff->id]);
    $scope->update(['document_id' => $document->id]);
    $sources = $service->sources($profile, '6-3');
    expect($sources['ready'])->toBeTrue();
    $answers = systemAnswers('6-3', $sources);
    $answers['PROG']['bez'] = '100';
    $answers['PROG']['zDzial'] = '90';
    $answers['NOSNIKI'][0] = ['nazwa' => 'Energia elektryczna', 'jednostka' => 'MWh', 'biezacy' => '100', 'poprzedni' => '110', 'przelicznik' => '1000', 'zrodlo' => 'Faktura i licznik'];
    $answers['OBSZARY'][0]['nr'] = 'A1';
    $answers['OBSZARY'][0]['mwh'] = '100';
    $answers['SEU'][0]['nr'] = 'A1';
    $answers['MOZL'][0] = ['nr' => 'M1', 'nazwa' => 'Regulacja', 'seu' => 'A1', 'mwh' => '10', 'zl' => '1000', 'capex' => '500'];
    $clientUrl = route('client.audits.system.update', [$audit, $profile, '6-3']);
    $staffUrl = route('audits.system.update', [$audit, $profile, '6-3']);
    $payload = ['operation' => 'submit', 'answers' => $answers, 'lock_version' => 0, 'source_hash' => $sources['hash'], 'complete_form' => 1];
    $this->actingAs($client)->post($clientUrl, $payload)->assertSessionHasNoErrors();
    $review = IsoSystemReview::where('section', '6-3')->sole();
    expect($review->status)->toBe('submitted');
    $this->actingAs($staff)->post($staffUrl, ['operation' => 'approve', 'lock_version' => 1, 'source_hash' => $sources['hash']])->assertSessionHasErrors('consultant');
    $notes = ['WNIOSKI' => [['znaczenie' => 'Poufna analiza konsultanta', 'decyzja' => 'Sprawdzono', 'dzialanie' => 'Kontynuować pomiary', 'sprawdzenie' => 'Przegląd miesięczny']]];
    $this->post($staffUrl, ['operation' => 'consultant', 'lock_version' => 1, 'source_hash' => $sources['hash'], 'consultant' => $notes])->assertSessionHasNoErrors();
    $this->post($staffUrl, ['operation' => 'approve', 'lock_version' => 2, 'source_hash' => $sources['hash']])->assertSessionHasNoErrors();
    $this->post(route('audits.system.pdf', [$audit, $profile, '6-3']), ['lock_version' => 3])->assertSessionHasNoErrors();
    expect($review->fresh()->document_id)->not->toBeNull();
    $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, '6-3']))->assertOk()->assertDontSee('Poufna analiza konsultanta');
});
