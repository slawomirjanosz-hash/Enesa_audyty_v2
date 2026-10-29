<?php

use App\Models\AuditType;
use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoPlantQuestionnaire;
use App\Services\IsoPlatformRelease;
use App\Services\IsoStakeholderQuestionnaire;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

test('October release examples render all questionnaires without persisting customer data', function (string $section) {
    $staff = User::factory()->create();
    $staff->assignRole(Role::findOrCreate('superadmin'));
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $this->actingAs($staff)->get(route('audit-types.questionnaire-preview', [$type, $section, 'source_example' => 1]))
        ->assertOk()->assertSee('Przykład AutoParts')->assertDontSee('data-autosave', false);
    $this->assertDatabaseCount('audits', 0);
    $this->assertDatabaseCount('iso_plant_profiles', 0);
    $this->assertDatabaseCount('iso_system_reviews', 0);
})->with(array_merge(['intro', '4-1', '4-2'], array_keys(IsoSystemReview::TITLES)));

test('release system examples satisfy the current questionnaire contract', function (string $section) {
    $release = app(IsoPlatformRelease::class);
    $sources = array_replace($release->demoSources(), ['ready' => true]);
    $answers = $release->answers($section, $sources);
    try {
        $normalized = app(IsoSystemQuestionnaire::class)->normalize($section, $answers, $sources, true);
    } catch (ValidationException $e) {
        $this->fail(json_encode($e->errors(), JSON_UNESCAPED_UNICODE));
    }
    expect($normalized)->toBeArray();
})->with(array_keys(IsoSystemReview::TITLES));

test('source examples are staff only and never mutate approval state on read', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    foreach (['7-4', '7-5'] as $section) {
        $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, $section, 'source_example' => 1]))->assertForbidden();
        $this->actingAs($staff)->get(route('audits.system.show', [$audit, $profile, $section, 'source_example' => 1]))->assertOk()->assertSee('Wczytano przykład');
    }
    $this->assertDatabaseCount('iso_system_reviews', 0);
    expect($profile->fresh()->status)->toBe('approved');
});

test('October requirements retain stored IDs when source rows are inserted', function () {
    $rows = collect(json_decode(file_get_contents(resource_path('iso50001/requirements-v2.json')), true)['wymagania'])->keyBy('id');
    expect($rows['WYM-25']['strona_kod'])->toBe('STK-Z-13');
    expect($rows['WYM-30']['strona_kod'])->toBe('STK-Z-18');
    expect($rows['WYM-31']['strona_kod'])->toBe('STK-Z-12');
    expect($rows['WYM-31']['source_id'])->toBe('WYM-25');
});

test('release profile and chapter four examples validate without guessed missing facts', function () {
    $release = app(IsoPlatformRelease::class);
    $profile = $release->profile();
    $plant = app(IsoPlantQuestionnaire::class);
    $answers = $plant->normalize($profile->answers, $profile->definition, [], 1, true);
    expect($answers['FAKT_PRACOWNICY']['value'])->toBe('850');
    expect($answers['FAKT_CHLODZENIE_MOC']['unknown'])->toBeTrue();
    $facts = $plant->facts($profile->definition, $answers);
    $factorService = app(IsoFactorQuestionnaire::class);
    $factorAnswers = $factorService->normalize($release->answers('4-1'), $facts, true);
    expect($factorService->progress($facts, $factorAnswers)['percent'])->toBe(100);
    $stakeholders = app(IsoStakeholderQuestionnaire::class);
    $parties = $stakeholders->parties($profile, null);
    $stakeholderAnswers = $stakeholders->normalize($release->answers('4-2'), $parties, true, []);
    expect($stakeholders->progress($stakeholderAnswers, $parties)['percent'])->toBe(100);
    expect($release->demoSources()['parties'])->toHaveCount(23);
});

test('climate decision activates the corresponding library factor and preserves integration proposal', function () {
    $release = app(IsoPlatformRelease::class);
    $facts = $release->demoSources()['facts'];
    $service = app(IsoFactorQuestionnaire::class);
    $yes = collect($service->factors($facts, ['FAKT_KLIMAT_ISTOTNY' => 'tak']))->keyBy('kod');
    $no = collect($service->factors($facts, ['FAKT_KLIMAT_ISTOTNY' => 'nie']))->keyBy('kod');
    expect($yes['KTX-ZK-01']['visible'])->toBeTrue()->and($yes['KTX-ZK-02']['visible'])->toBeFalse();
    expect($no['KTX-ZK-02']['visible'])->toBeTrue()->and($no['KTX-ZK-01']['visible'])->toBeFalse();
    expect($yes['KTX-WO-12']['rodzaj'])->toBe('PROPOZYCJA')->and($yes['KTX-WO-12']['verification'])->toContain('zatwierdzenia merytorycznego');
});

test('documentation control rejects missing coverage but only warns about titles and outdated checks', function () {
    $service = app(IsoSystemQuestionnaire::class);
    $answers = app(IsoPlatformRelease::class)->answers('7-5');
    $answers['WYKAZ'] = [$answers['WYKAZ'][0], $answers['WYKAZ'][0]];
    $answers['ZEWN'][0]['sprawdzono'] = today()->subYears(2)->toDateString();
    try {
        $service->normalize('7-5', $answers, ['ready' => true], true);
        $this->fail('Incomplete documentation was approved.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('WYKAZ')->not->toHaveKeys(['WYKAZ.1.tytul', 'ZEWN.0.sprawdzono']);
    }
    expect(implode(' ', $service->warnings('7-5', $answers, [])))->toContain('Powtórzony tytuł', '24 pełnych miesięcy');
});
