<?php

use App\Models\IsoSectionDocument;
use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

function leadershipAnswers(string $section, array $sources): array
{
    $answers = systemAnswers($section, $sources);
    if ($section === '5-2') {
        $answers['POL']['wstep'] = 'Zobowiązujemy się do realizacji następujących zasad.';
        foreach (app(IsoSystemQuestionnaire::class)->schema($section)['ELEMENTY'] as $element) {
            $answers['TRESC'][$element['kod']] = $element['przykladPelny'];
        }
    }
    if ($section === '5-3') {
        foreach ($answers['ROLE'] as $i => &$role) {
            foreach (app(IsoSystemQuestionnaire::class)->schema($section)['MAT_ZADANIA'] as $task) {
                $role['MAT'][$task['kod']] = $i === 0 ? 'O' : 'I';
            }
        }
        unset($role);
    }

    return $answers;
}

test('chapter five retains source identifiers and validates evidence policy and responsibility', function () {
    $service = app(IsoSystemQuestionnaire::class);
    $sources = ['ready' => true, 'facts' => []];
    foreach (['5-1', '5-2', '5-3'] as $section) {
        $answers = leadershipAnswers($section, $sources);
        $normalized = $service->normalize($section, $answers, $sources, true);
        expect($service->progress($section, $normalized, $sources)['percent'])->toBe(100);
    }
    $answers = leadershipAnswers('5-1', $sources);
    unset($answers['ZOB']['PRZ-01']['dowod']);
    expect(fn () => $service->normalize('5-1', $answers, $sources, true))->toThrow(ValidationException::class);
    expect($service->normalize('5-1', $answers, $sources, false))->toHaveKey('ZOB.PRZ-01');
    $answers = leadershipAnswers('5-2', $sources);
    $answers['TRESC']['e'] = 'Doskonalimy wynik energetyczny naszego zakładu na podstawie prowadzonych pomiarów.';
    expect(fn () => $service->normalize('5-2', $answers, $sources, true))->toThrow(ValidationException::class);
    $answers = leadershipAnswers('5-3', $sources);
    expect($service->normalize('5-3', $answers, $sources, true)['MAT']['MAT-01|zarzad'])->toBe('O');
    $answers['ROLE'][1]['MAT']['MAT-01'] = 'O';
    expect(fn () => $service->normalize('5-3', $answers, $sources, true))->toThrow(ValidationException::class);
    $answers['UWAGI']['MAT-01'] = 'Zarząd powołuje, koordynator komunikuje.';
    expect($service->normalize('5-3', $answers, $sources, true))->toHaveKey('ROLE');
});

test('chapter five workflow protects consultant notes and publishes current documents only', function () {
    [$audit,$client,$staff,$profile] = systemFixture();
    $service = app(IsoSystemQuestionnaire::class);
    foreach (['4-3', '4-4'] as $section) {
        $sources = $service->sources($profile, $section);
        $doc = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => $section, 'scope' => 'client', 'title' => 'Źródło', 'document_year' => now()->year, 'version_number' => '1', 'original_filename' => 'test.pdf', 'stored_path' => 'test/'.$section, 'mime_type' => 'application/pdf', 'size' => 4, 'content_base64' => base64_encode('%PDF'), 'uploaded_by' => $staff->id]);
        IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => $section, 'status' => 'approved', 'lock_version' => 1, 'answers' => systemAnswers($section, $sources), 'source_hash' => $sources['hash'], 'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id], 'document_id' => $doc->id]);
    }
    foreach (['5-1', '5-2', '5-3'] as $section) {
        $sources = $service->sources($profile, $section);
        expect($sources['ready'])->toBeTrue();
        $url = route('client.audits.system.update', [$audit, $profile, $section]);
        $staffUrl = route('audits.system.update', [$audit, $profile, $section]);
        $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, $section]))->assertOk()->assertSee('Wypełnienie');
        $answers = leadershipAnswers($section, $sources);
        $payload = ['operation' => 'submit', 'lock_version' => 0, 'source_hash' => $sources['hash'], 'complete_form' => 1, 'answers' => $answers];
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $review = IsoSystemReview::where('section', $section)->sole();
        $analysis = ['WNIOSKI' => [['znaczenie' => 'TAJNA ANALIZA TESTOWA', 'decyzja' => 'Decyzja', 'dzialanie' => 'Działanie', 'sprawdzenie' => 'Dowód']]];
        $this->post($url, ['operation' => 'consultant', 'lock_version' => 1, 'source_hash' => $sources['hash'], 'consultant' => $analysis])->assertForbidden();
        $this->actingAs($staff)->post($staffUrl, ['operation' => 'approve', 'lock_version' => 1, 'source_hash' => $sources['hash']])->assertSessionHasErrors('consultant');
        $this->post($staffUrl, ['operation' => 'consultant', 'lock_version' => 1, 'source_hash' => $sources['hash'], 'consultant' => $analysis])->assertSessionHasNoErrors();
        expect($review->fresh()->client_approval)->not->toBeNull();
        $this->post($staffUrl, ['operation' => 'approve', 'lock_version' => 2, 'source_hash' => $sources['hash']])->assertSessionHasNoErrors();
        $this->post(route('audits.system.pdf', [$audit, $profile, $section]), ['lock_version' => 3])->assertSessionHasNoErrors();
        expect($review->fresh()->document_id)->not->toBeNull();
        $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, $section]))->assertOk()->assertDontSee('TAJNA ANALIZA TESTOWA');
        $this->actingAs($staff)->get(route('audits.system.show', [$audit, $profile, $section]))->assertOk()->assertSee('TAJNA ANALIZA TESTOWA');
    }
    $profile->increment('lock_version');
    $review = IsoSystemReview::where('section', '5-2')->sole();
    $this->post(route('audits.system.pdf', [$audit, $profile, '5-2']), ['lock_version' => $review->lock_version])->assertStatus(409);
    $other = User::factory()->create();
    $other->assignRole(Role::findOrCreate('client_admin','web'));
    $this->actingAs($other)->get(route('client.audits.system.show',[$audit, $profile, '5-1']))->assertNotFound();
});
