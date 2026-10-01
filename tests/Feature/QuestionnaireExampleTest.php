<?php

use App\Models\IsoStakeholderReview;

test('example fill controls are staff only and do not mutate questionnaires on opening', function () {
    [$audit, $client, $staff, $profile] = stakeholderFixture();
    $profile->update(['client_approval' => ['user_id' => $client->id, 'name' => $client->name, 'at' => now()->toIso8601String()], 'auditor_approval' => ['user_id' => $staff->id, 'name' => $staff->name, 'at' => now()->toIso8601String()]]);
    foreach (['plant-profile', 'factors', 'stakeholders'] as $section) {
        $this->actingAs($staff)->get(route("audits.$section.show", [$audit, $profile]))
            ->assertOk()->assertSee('data-questionnaire-example=', false);
        $this->actingAs($client)->get(route("client.audits.$section.show", [$audit, $profile]))
            ->assertOk()->assertDontSee('data-questionnaire-example=', false);
    }
    expect(IsoStakeholderReview::count())->toBe(0);
    expect($profile->fresh()->answers)->toBe($profile->answers);
});

test('example control respects editability and manage permission', function () {
    [$audit, $client, $staff, $profile] = stakeholderFixture();
    $this->actingAs($client);
    expect(view('audits.partials.questionnaire-example', ['exampleAllowed' => true, 'exampleForm' => 'plant-form'])->render())
        ->not->toContain('data-questionnaire-example=');
    $this->actingAs($staff);
    expect(view('audits.partials.questionnaire-example', ['exampleAllowed' => false, 'exampleForm' => 'plant-form'])->render())
        ->not->toContain('data-questionnaire-example=');
    $profile->update(['status' => 'editing', 'client_approval' => null]);
    $this->get(route('audits.factors.show', [$audit, $profile]))->assertOk()->assertDontSee('data-questionnaire-example=', false);
});
