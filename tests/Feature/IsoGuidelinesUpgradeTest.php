<?php

use App\Models\IsoSystemReview;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoStakeholderQuestionnaire;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('guideline upgrade preserves answers documents and snapshots and resets all chapter approvals once', function () {
    [$audit, $client, $staff, $profile, $factor, $party] = systemFixture();
    $factor->update(['swot' => factorSwot()]);
    $party->update(['answers' => ['parties' => ['STK-W-01' => ['wymagania' => 'Własny tekst'], 'STK-W-03' => ['wymagania' => 'Dawne wspólne wymagania']]]]);
    $scope = IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => '4-3', 'status' => 'approved', 'source_hash' => str_repeat('a', 64), 'answers' => ['ZAKRES' => ['opis' => 'Zachowany zakres']], 'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id]]);
    $documentId = $factor->document_id;
    $migration = require database_path('migrations/2026_10_10_000003_upgrade_iso_chapter_four_guidelines.php');
    $migration->up();
    foreach ([$factor, $party, $scope] as $review) {
        $review->refresh();
        expect($review->status)->toBe('editing')->and($review->client_approval)->toBeNull()->and($review->auditor_approval)->toBeNull()->and($review->document_id)->toBeNull();
    }
    expect($factor->swot)->toBe(factorSwot());
    expect($scope->answers['ZAKRES']['opis'])->toBe('Zachowany zakres');
    expect($party->answers['parties']['WYM-01']['wymagania'])->toBe('Własny tekst');
    expect($party->answers['parties'])->not->toHaveKey('WYM-03');
    expect($party->answers['legacy_parties']['STK-W-03']['wymagania'])->toBe('Dawne wspólne wymagania');
    expect(DB::table('iso_section_documents')->where('id', $documentId)->value('description'))->toContain('historyczny');
    $version = $factor->lock_version;
    $migration->up();
    expect($factor->fresh()->lock_version)->toBe($version);
    expect(DB::table('iso_factor_events')->where('action', 'guidelines_2026_10')->count())->toBe(1);
});

test('high significance requires all four conclusions and legal requirements require dated evidence', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $service = app(IsoFactorQuestionnaire::class);
    $input = factorInput($profile)['answers'];
    $input['factors']['KTX-WT-01']['istotnosc'] = 'W';
    try {
        $service->normalize($input, $service->facts($profile), true);
        $this->fail('Missing conclusions were accepted');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['factors.KTX-WT-01.znaczenie', 'factors.KTX-WT-01.decyzja_w', 'factors.KTX-WT-01.dzialanie', 'factors.KTX-WT-01.sprawdzenie']);
    }
    $partyService = app(IsoStakeholderQuestionnaire::class);
    expect($partyService->requiredFields(['kwalifikacja' => 'prawne']))->toContain('data_brzmienia', 'zr_data', 'sprawdzanie');
    expect($partyService->requiredFields(['kwalifikacja' => 'oczekiwanie_nieprzyjete']))->toBe(['kwalifikacja', 'powod']);
});

test('requirement codes retain party links and voluntary obligations join compliance extract', function () {
    [$audit, $client, $staff, $profile, $factor, $input] = stakeholderFixture();
    $service = app(IsoStakeholderQuestionnaire::class);
    $parties = $service->parties($profile, $factor);
    $answers = $input['answers'];
    $answers['parties']['WYM-01']['kwalifikacja'] = 'przyjete';
    $answers['parties']['WYM-02'] = ['decyzja' => 'potwierdzona', 'wymagania' => 'Raport', 'kwalifikacja' => 'oczekiwanie_nieprzyjete', 'powod' => 'Poza przyjętym zakresem'];
    $rows = collect($service->register($answers, $parties, []))->keyBy('kod');
    expect($rows['WYM-01']['zgodnosc'])->toBe('tak')->and($rows['WYM-01']['strona_kod'])->toBe('STK-W-01');
    $scope = app(IsoSystemQuestionnaire::class);
    $consequences = $scope->consequences(['facts' => ['FAKT_ODBIORCY_CO2' => 'tak'], 'parties' => [['kod' => 'WYM-23', 'strona_kod' => 'STK-Z-12']]]);
    expect($consequences)->toHaveKey('K-01');
    $answers['parties']['WYM-01']['kwalifikacja'] = 'prawne';
    try {
        $service->normalize($answers, $parties, true, []);
        $this->fail('Missing date of legal verification accepted');
    } catch (ValidationException $error) {
        expect($error->errors())->toHaveKey('parties.WYM-01.data_brzmienia');
    }
});
