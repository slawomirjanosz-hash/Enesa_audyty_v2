<?php

use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\IsoExecutionQuestionnaire;
use App\Services\IsoPlatformRelease;
use App\Services\IsoSystemQuestionnaire;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

test('all fifteen documentation requirements are active including 10.1 rather than 10.2', function () {
    $service = app(IsoExecutionQuestionnaire::class);
    $coverage = $service->documentationCoverage([]);
    expect($coverage)->toHaveCount(15);
    expect(array_column($coverage, 'punkt'))->toContain('10.1')->not->toContain('10.2');
    expect(array_column($coverage, 'covered'))->each->toBeFalse();
    try {
        $service->validateContent('7-5', [], ['ready' => true], true);
        $this->fail('Missing documentation must block submission.');
    } catch (ValidationException $e) {
        expect(array_filter($e->errors()['WYKAZ'], fn ($message) => str_starts_with($message, 'Uzupełnij wykaz')))->toHaveCount(15);
        expect(implode(' ', $e->errors()['WYKAZ']))->toContain('8.1', '8.2', '8.3', '9.1', '9.2', '9.3', '10.1');
    }
});

test('coverage accepts multiple exact clause references and does not equate coverage with the declared basis', function () {
    $coverage = collect(app(IsoExecutionQuestionnaire::class)->documentationCoverage([
        'WYKAZ' => [
            ['punkt' => '6.4, 6.5', 'podstawa' => 'M'],
            ['punkt' => '19.1; 10.10; 9.1.1', 'podstawa' => 'N'],
        ],
    ]))->keyBy('punkt');
    expect($coverage['6.4']['covered'])->toBeTrue();
    expect($coverage['6.5']['covered'])->toBeTrue();
    expect($coverage['9.1']['covered'])->toBeFalse();
    expect($coverage['10.1']['covered'])->toBeFalse();
});

test('duplicate document titles and overdue external checks warn without blocking issuance', function () {
    $this->travelTo(Carbon::parse('2026-10-29'));
    $service = app(IsoExecutionQuestionnaire::class);
    $answers = ['WYKAZ' => array_map(fn ($row) => [
        'kod' => 'DOC-'.$row['punkt'], 'punkt' => $row['punkt'], 'podstawa' => 'N',
        'tytul' => 'Instrukcja', 'wersja' => '1',
    ], $service->documentationCoverage([])), 'ZEWN' => [
        ['tytul' => 'DTR', 'sprawdzono' => '2025-09-29'],
        ['tytul' => 'Norma', 'sprawdzono' => '2025-10-29'],
        ['tytul' => 'Umowa', 'sprawdzono' => '2026-01-29'],
    ]];
    $service->validateContent('7-5', $answers, ['ready' => true], true);
    $warnings = $service->warnings('7-5', $answers, []);
    expect($warnings)->toHaveCount(3);
    expect($warnings[0])->toContain('Powtórzony tytuł', 'DOC-4.3', 'DOC-10.1');
    expect($warnings[1])->toContain('DTR', '13 pełnych miesięcy');
    expect($warnings[2])->toContain('Norma', 'zbliża się', '12 pełnych miesięcy');
    $this->travelBack();
});

test('external check age counts full months and ignores missing dates', function () {
    $this->travelTo(Carbon::parse('2026-10-28'));
    $warnings = app(IsoExecutionQuestionnaire::class)->warnings('7-5', ['ZEWN' => [
        ['tytul' => 'DTR', 'sprawdzono' => '2025-09-29'],
        ['tytul' => 'Brak daty', 'sprawdzono' => null],
    ]], []);
    expect($warnings)->toHaveCount(1);
    expect($warnings[0])->toContain('12 pełnych miesięcy', 'zbliża się');
    $this->travelBack();
});

test('a basis differing from the documentation dictionary requires a preserved justification', function () {
    $service = app(IsoExecutionQuestionnaire::class);
    $answers = app(IsoPlatformRelease::class)->answers('7-5');
    $answers['WYKAZ'][0]['podstawa'] = 'N'; // The context report at 4.1 is methodological in the supplied dictionary.
    try {
        $service->normalize('7-5', $answers, ['ready' => true], true);
        $this->fail('An unexplained basis mismatch must be reported.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('WYKAZ.0.powodPodstawy');
    }
    $answers['WYKAZ'][0]['powodPodstawy'] = 'Uzasadnienie kwalifikacji zapisane przez konsultanta';
    expect($service->normalize('7-5', $answers, ['ready' => true], true)['WYKAZ'][0]['powodPodstawy'])
        ->toBe('Uzasadnienie kwalifikacji zapisane przez konsultanta');
});

test('documentation coverage is rendered in the native questionnaire and PDF with existing access scopes', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $sources = ['hash' => str_repeat('e', 64), 'ready' => true, 'approved' => [], 'published' => [], 'upstream' => []];
    $service = Mockery::mock(IsoSystemQuestionnaire::class)->makePartial();
    $service->shouldReceive('sources')->andReturn($sources);
    app()->instance(IsoSystemQuestionnaire::class, $service);
    $answers = app(IsoPlatformRelease::class)->answers('7-5', $sources);
    IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id,
        'section' => '7-5', 'source_hash' => $sources['hash'], 'answers' => $answers, 'status' => 'editing']);
    $response = $this->actingAs($staff)->get(route('audits.system.show', [$audit, $profile, '7-5']))
        ->assertOk()->assertSee('Pokrycie wymagań normy')->assertDontSee('Specyfikacja dla Sławka');
    expect(substr_count($response->getContent(), 'data-coverage-clause='))->toBe(15);
    $pdf = view('audits.system.execution-summary', ['section' => '7-5', 'answers' => $answers, 'pdf' => true])->render();
    expect($pdf)->toContain('data-coverage-count>15</span>', '10.1');
    $this->actingAs(User::factory()->create())->get(route('audits.system.show', [$audit, $profile, '7-5']))->assertForbidden();
    if (getenv('ISO_DOCUMENTATION_QA')) {
        file_put_contents(base_path('tmp/iso-documentation.html'), $response->getContent());
    }
});
