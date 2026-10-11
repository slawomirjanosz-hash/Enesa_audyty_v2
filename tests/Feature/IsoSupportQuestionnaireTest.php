<?php

use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\IsoSupportQuestionnaire;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Validation\ValidationException;

test('support questionnaires preserve roots and source checklists and can save drafts', function () {
    $service = app(IsoSystemQuestionnaire::class);
    foreach (['7-1' => 5, '7-2' => 6, '7-3' => 5] as $section => $count) {
        expect($service->schema($section)['checklist'])->toHaveCount($count);
        $draft = $service->normalize($section, $service->seed($section, []), [], false);
        expect($draft)->toBeArray();
        expect($service->progress($section, [], [])['percent'])->toBe(0);
    }
});

test('resource balance retains sparse person identifiers and rejects orphan or negative hours', function () {
    $service = app(IsoSystemQuestionnaire::class);
    $answers = ['OSOBY' => [8 => ['nazwa' => 'Energetyk', 'dostepne' => '2,5']], 'ZADANIA' => [15 => ['osoba' => '8', 'godziny' => '4']]];
    $saved = $service->normalize('7-1', $answers, [], false);
    expect($saved['OSOBY'][8]['dostepne'])->toBe('2.5');
    expect(app(IsoSupportQuestionnaire::class)->balance($saved)[0]['difference'])->toBe(-1.5);
    expect($service->warnings('7-1', $saved, []))->not->toBeEmpty();
    $answers['ZADANIA'][15]['osoba'] = '99';
    expect(fn () => $service->normalize('7-1', $answers, [], false))->toThrow(ValidationException::class);
    $answers['ZADANIA'][15]['osoba'] = '8';
    $answers['OSOBY'][8]['dostepne'] = '-1';
    expect(fn () => $service->normalize('7-1', $answers, [], false))->toThrow(ValidationException::class);
});

test('competency matrix validates levels and requires actions for substantial gaps', function () {
    $service = app(IsoSystemQuestionnaire::class);
    $answers = ['ROLE' => [5 => ['nazwa' => 'Operator']], 'KOMPETENCJE' => [3 => ['nazwa' => 'Regulacja']], 'MAC' => [3 => [5 => ['W' => '4', 'A' => '5']]]];
    expect(fn () => $service->normalize('7-2', $answers, [], false))->toThrow(ValidationException::class);
    $answers['MAC'][3][5]['A'] = '1';
    $saved = $service->normalize('7-2', $answers, [], false);
    $saved['PLAN'] = [[]];
    expect(fn () => app(IsoSupportQuestionnaire::class)->validateContent('7-2', $saved, true))->toThrow(ValidationException::class);
    $saved['LUKI'] = [['dotyczy' => '3|5', 'dzialanie' => 'Instruktaż']];
    app(IsoSupportQuestionnaire::class)->validateContent('7-2', $saved, true);
    unset($saved['ROLE'][5]);
    expect(fn () => app(IsoSupportQuestionnaire::class)->validateContent('7-2', $saved, false))->toThrow(ValidationException::class);
});

test('awareness history is append only and competency plan is readonly', function () {
    $service = app(IsoSystemQuestionnaire::class);
    $support = app(IsoSupportQuestionnaire::class);
    $entry = ['data' => today()->toDateString(), 'kto' => 'Kierownik', 'osoby' => 'Zmiana A', 'wynik' => 'Poprawnie', 'luki' => '', 'dzialanie' => ''];
    $old = $service->normalize('7-3', ['SPRAWDZENIA' => [7 => $entry]], [], false);
    $next = $service->normalize('7-3', ['SPRAWDZENIA' => [7 => $entry, 9 => $entry], 'PLAN' => [['temat' => 'Sfałszowany']]], [], false);
    expect($next)->not->toHaveKey('PLAN');
    $support->protectHistory('7-3', $old, $next);
    $next['SPRAWDZENIA'][7]['wynik'] = 'Zmiana';
    expect(fn () => $support->protectHistory('7-3', $old, $next))->toThrow(ValidationException::class);
    expect($support->plan(['upstream' => ['7-2' => ['PLAN' => [['punkty' => ['7.2']], ['punkty' => ['7.3']]]]]]))->toHaveCount(1);
    $entry['data'] = today()->addDay()->toDateString();
    expect(fn () => $service->normalize('7-3', ['SPRAWDZENIA' => [$entry]], [], false))->toThrow(ValidationException::class);
});

test('support screens isolate tenants preserve history and invalidate shared plan sources', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $service = app(IsoSystemQuestionnaire::class);
    foreach (['7-1', '7-2', '7-3'] as $section) {
        $this->actingAs($staff)->get(route('audits.system.show', [$audit, $profile, $section]))->assertOk();
        $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, $section]))->assertOk()->assertDontSee('Analiza konsultanta — wewnętrzna')->assertDontSee('id="system-example"', false);
    }
    $oldHash = $service->sources($profile, '7-3')['hash'];
    $url = route('audits.system.update', [$audit, $profile, '7-2']);
    $this->actingAs($staff)->post($url, ['operation' => 'save', 'lock_version' => 0, 'source_hash' => $service->sources($profile, '7-2')['hash'], 'answers' => ['PLAN' => [['nr' => 'P1', 'temat' => 'Świadomość', 'punkty' => ['7.3']]]], 'complete_form' => 1])->assertSessionHasNoErrors();
    expect($service->sources($profile, '7-3')['hash'])->not->toBe($oldHash);
    $transitiveHash = $service->sources($profile, '7-3')['hash'];
    IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => '6-1', 'status' => 'editing', 'source_hash' => $service->sources($profile, '6-1')['hash'], 'answers' => ['RYZYKA' => [['opis' => 'Nowe ryzyko kompetencyjne']]]]);
    expect($service->sources($profile, '7-3')['hash'])->not->toBe($transitiveHash);
    $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, '7-3']))->assertOk()->assertSee('P1');
    $outsider = User::factory()->create();
    $outsider->assignRole('client_admin');
    $this->actingAs($outsider)->get(route('client.audits.system.show', [$audit, $profile, '7-3']))->assertNotFound();
});

test('each support questionnaire follows client consultant auditor and PDF workflow', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $sources = ['hash' => str_repeat('a', 64), 'ready' => true, 'approved' => [], 'published' => [], 'upstream' => []];
    $service = Mockery::mock(IsoSystemQuestionnaire::class)->makePartial();
    $service->shouldReceive('sources')->andReturn($sources);
    app()->instance(IsoSystemQuestionnaire::class, $service);
    foreach (['7-1', '7-2', '7-3'] as $section) {
        $answers = systemAnswers($section, $sources);
        array_walk_recursive($answers, function (&$value) {
            $value = (string) $value;
        });
        if ($section === '7-1') {
            $answers['OSOBY'][0]['dostepne'] = '8';
            $answers['ZADANIA'][0]['godziny'] = '4';
        }
        $clientUrl = route('client.audits.system.update', [$audit, $profile, $section]);
        $staffUrl = route('audits.system.update', [$audit, $profile, $section]);
        $this->actingAs($client)->post($clientUrl, ['operation' => 'submit', 'lock_version' => 0, 'source_hash' => $sources['hash'], 'complete_form' => 1, 'answers' => $answers])->assertSessionHasNoErrors();
        $review = IsoSystemReview::where('section', $section)->sole();
        expect($review->status)->toBe('submitted');
        $this->actingAs($staff)->post($staffUrl, ['operation' => 'approve', 'lock_version' => 1, 'source_hash' => $sources['hash']])->assertSessionHasErrors('consultant');
        $notes = ['WNIOSKI' => [['znaczenie' => 'WEWNETRZNY SEKRET', 'decyzja' => 'Decyzja', 'dzialanie' => 'Działanie', 'sprawdzenie' => 'Sprawdzenie']]];
        $this->post($staffUrl, ['operation' => 'consultant', 'lock_version' => 1, 'source_hash' => $sources['hash'], 'consultant' => $notes])->assertSessionHasNoErrors();
        $this->post($staffUrl, ['operation' => 'approve', 'lock_version' => 2, 'source_hash' => $sources['hash']])->assertSessionHasNoErrors();
        $this->post(route('audits.system.pdf', [$audit, $profile, $section]), ['lock_version' => 3])->assertSessionHasNoErrors();
        expect($review->fresh()->document_id)->not->toBeNull();
        $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, $section]))->assertOk()->assertDontSee('WEWNETRZNY SEKRET');
        $this->actingAs($staff)->post($staffUrl, ['operation' => 'save', 'lock_version' => 3, 'source_hash' => $sources['hash'], 'complete_form' => 1, 'answers' => array_replace_recursive($answers, ['META' => ['zatwierdzil' => 'Poprawiona funkcja']])])->assertSessionHasNoErrors();
        expect($review->fresh()->client_approval)->toBeNull();
        expect($review->fresh()->document_id)->toBeNull();
        if ($section === '7-3') {
            $answers['SPRAWDZENIA'][0]['wynik'] = 'Próba nadpisania historii';
            $this->post($staffUrl, ['operation' => 'save', 'lock_version' => 4, 'source_hash' => $sources['hash'], 'complete_form' => 1, 'answers' => $answers])->assertSessionHasErrors('answers.SPRAWDZENIA');
            expect($review->fresh()->answers['SPRAWDZENIA'][0]['wynik'])->not->toBe('Próba nadpisania historii');
        }
    }
});
