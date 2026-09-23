<?php

use App\Models\IsoPlantProfile;
use App\Services\IsoPlantQuestionnaire;
use App\Services\QuestionnaireCompletion;

beforeEach(function () {
    app()->instance(IsoPlantQuestionnaire::class, new class extends IsoPlantQuestionnaire
    {
        public function definition(): array
        {
            return json_decode(file_get_contents(resource_path('iso50001/plant-profile-v1.json')), true, 512, JSON_THROW_ON_ERROR);
        }
    });
});

test('completion counts zero no and unknown but excludes unanswered and conditional questions', function () {
    $counter = app(QuestionnaireCompletion::class);
    $schema = app(IsoPlantQuestionnaire::class)->definition();
    expect($counter->plant($schema, []))->toBe(['answered' => 0, 'total' => 64, 'percent' => 0]);
    $answers = ['operations.headcount' => ['value' => 0], 'energy.own_generation' => ['value' => 'no'], 'site.industry' => ['value' => 'unknown'], 'site.buildings' => ['value' => [['id' => 'only-id']]], 'organization.name' => ['unknown' => true]];
    expect($counter->plant($schema, $answers))->toBe(['answered' => 3, 'total' => 64, 'percent' => 4]);
    $answers['systems.main_consumers_known'] = ['value' => 'v1'];
    expect($counter->plant($schema, $answers)['total'])->toBe(65);
    expect($counter->fields(['a', 'b', 'c', 'd'], ['a' => 0, 'b' => 'nie', 'c' => 'nie wiem', 'd' => '  ']))->toBe(['answered' => 3, 'total' => 4, 'percent' => 75]);
});

test('audit and profile screens display saved completion and only latest site revisions in audit summary', function () {
    [$audit, $client] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $audit), ['name' => 'Piła']);
    $profile = IsoPlantProfile::firstOrFail();
    $this->get(route('client.audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('2/64 pytań · 3%')->assertSee('data-completion-mode="plant"', false);
    $this->get(route('client.audits.plant-profile.index', $audit))->assertOk()->assertSee('Wypełnienie')->assertSee('data-sort-value="3"', false);
    $next = $profile->replicate();
    $next->revision = 2;
    $next->answers = $profile->answers + ['operations.headcount' => ['value' => 0]];
    $next->save();
    $this->get(route('client.audits.show', $audit))->assertOk()->assertSee('3/64 pytań · 4%')->assertSee('0/44 pytań · 0%')->assertDontSee('2/64 pytań · 3%');
    [$otherAudit, $otherClient] = plantFixture();
    $this->actingAs($otherClient)->get(route('client.audits.show', $audit))->assertNotFound();
});
