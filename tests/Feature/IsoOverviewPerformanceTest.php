<?php

use App\Models\IsoEnergyCards;
use App\Models\IsoSectionDocument;
use App\Models\IsoSystemReview;
use App\Services\IsoSystemQuestionnaire;
use App\Services\QuestionnaireCompletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('overview shares dependency reads while preserving every section snapshot and progress', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $service = app(IsoSystemQuestionnaire::class);
    $sections = array_keys(IsoSystemReview::TITLES);
    foreach (['4-3', '6-3', '7-4', '9-1-1'] as $section) {
        $sources = $service->sources($profile, $section);
        IsoSystemReview::create([
            'audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => $section,
            'source_profile_id' => $profile->id, 'source_hash' => $sources['hash'], 'lock_version' => 1,
            'status' => $section === '4-3' ? 'approved' : 'editing', 'answers' => $service->seed($section, $sources),
            'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id],
        ]);
    }
    IsoEnergyCards::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'cards' => [['nr' => 'ENPI-1', 'wartoscEnB' => 0]]]);
    $before = IsoSystemReview::all()->toArray();
    DB::enableQueryLog();
    try {
        DB::flushQueryLog();
        $individual = [];
        foreach ($sections as $section) {
            $individual[$section] = $service->sources($profile, $section);
        }
        $individualQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $batch = $service->sourcesForSections($profile, $sections);
        $batchQueries = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect($batch)->toBe($individual);
    expect($batchQueries)->toBeLessThan($individualQueries / 4);
    $overview = app(QuestionnaireCompletion::class)->audit($audit);
    // The overview must use the same current site count as the actual 4.1 / 4.2 forms.
    expect($overview['factors']->first()['stale'])->toBeFalse();
    expect($overview['stakeholders']->first()['stale'])->toBeFalse();
    foreach ($individual as $section => $sources) {
        $card = $overview[$section]->first();
        $review = IsoSystemReview::where('audit_id', $audit->id)->where('section', $section)->first();
        expect($card['progress'])->toBe($service->progress($section, $review?->answers ?? $service->seed($section, $sources), $sources));
        expect($card['stale'])->toBe(! $sources['ready'] || ($review && $review->source_hash !== $sources['hash']));
    }
    expect(IsoSystemReview::all()->toArray())->toBe($before);
});

test('dependency batches never leak between audits or retain old values after writes or errors', function () {
    [$audit, , , $profile] = systemFixture();
    [$otherAudit, , , $otherProfile] = systemFixture();
    $service = app(IsoSystemQuestionnaire::class);
    $sections = ['4-3', '4-4', '6-3', '6-4', '9-1-1', '10-2'];
    $otherBefore = $service->sourcesForSections($otherProfile, $sections);
    $before = $service->sourcesForSections($profile, $sections);
    $scope = IsoSystemReview::create([
        'audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => '4-3',
        'source_profile_id' => $profile->id, 'source_hash' => $before['4-3']['hash'],
        'lock_version' => 1, 'status' => 'editing', 'answers' => ['ZAKRES' => ['opis' => 'Nowy zakres testowy']],
    ]);
    $after = $service->sourcesForSections($profile, $sections);
    expect($after['4-4']['hash'])->not->toBe($before['4-4']['hash']);
    expect($service->sourcesForSections($otherProfile, $sections))->toBe($otherBefore);
    expect(fn () => $service->sourcesForSections($profile, ['4-4', 'invalid-section']))->toThrow(UnhandledMatchError::class);
    $scope->update(['lock_version' => 2, 'answers' => ['ZAKRES' => ['opis' => 'Kolejna zmiana zakresu']]]);
    expect($service->sources($profile, '4-4')['hash'])->not->toBe($after['4-4']['hash']);
    expect($service->sourcesForSections($profile, ['4-4'])['4-4'])->toBe($service->sources($profile, '4-4'));
});

test('audit pages load document metadata while scoped PDF downloads still work', function () {
    Storage::fake('local');
    [$audit, $client, $staff] = systemFixture();
    [$otherAudit, $otherClient] = systemFixture();
    $document = IsoSectionDocument::where('audit_id', $audit->id)->firstOrFail();
    $document->update(['content_base64' => base64_encode('%PDF-test '.str_repeat('x', 1000000))]);
    foreach (['client.audits.' => $client, 'audits.' => $staff] as $prefix => $actor) {
        $response = $this->actingAs($actor)->get(route($prefix.'show', $audit))->assertOk();
        $listed = $response->viewData('clientDocuments')->flatten()->firstWhere('id', $document->id);
        expect($listed->getAttributes())->not->toHaveKey('content_base64');
        expect($listed->isAvailable())->toBeTrue();
        expect($listed->uploader->id)->toBe($staff->id);
        $download = $this->get(route($prefix.'iso-documents.download', [$audit, $document]))->assertOk()->assertDownload();
        expect(hash('sha256', $download->streamedContent()))->toBe(hash('sha256', '%PDF-test '.str_repeat('x', 1000000)));
    }
    $this->actingAs($otherClient)->get(route('client.audits.show', $audit))->assertNotFound();
    $this->get(route('client.audits.iso-documents.download', [$otherAudit, $document]))->assertNotFound();
});
