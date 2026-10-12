<?php

use App\Models\AuditType;
use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\IsoStakeholderQuestionnaire;
use Spatie\Permission\Models\Role;

test('ISO library previews every questionnaire without a customer or audit', function (string $section) {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin'));
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $this->actingAs($user)->get(route('audit-types.questionnaire-preview', [$type, $section]))
        ->assertOk()->assertSee('Podgląd ankiety')->assertSee('data-preview-form', false)
        ->assertDontSee('data-autosave', false)->assertDontSee('name="_token"', false);
    $this->assertDatabaseCount('audits', 0);
    $this->assertDatabaseCount('iso_plant_profiles', 0);
    $this->assertDatabaseCount('iso_system_reviews', 0);
})->with(array_merge(['intro', '4-1', '4-2'], array_keys(IsoSystemReview::TITLES)));

test('ISO preview is staff only and rejects unknown sections and mutations', function () {
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $url = route('audit-types.questionnaire-preview', [$type, 'intro']);
    $this->get($url)->assertRedirect();
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_admin'));
    $this->actingAs($client)->get($url)->assertForbidden();
    $staff = User::factory()->create();
    $staff->assignRole(Role::findOrCreate('superadmin'));
    $this->actingAs($staff)->get(route('audit-types.questionnaire-preview', [$type, 'unknown']))->assertNotFound();
    $this->post($url, ['answers' => ['site.name' => 'Do not save']])->assertStatus(405);
    $this->get(route('audit-types.show', $type))->assertOk()->assertSee($url, false);
});

test('preview retains library requirement text and all current questionnaire links', function () {
    $staff = User::factory()->create();
    $staff->assignRole(Role::findOrCreate('superadmin'));
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $response = $this->actingAs($staff)->get(route('audit-types.show', $type))->assertOk();
    foreach (array_merge(['intro', '4-1', '4-2'], array_keys(IsoSystemReview::TITLES)) as $section) {
        $response->assertSee(route('audit-types.questionnaire-preview', [$type, $section]), false);
    }
    $requirement = app(IsoStakeholderQuestionnaire::class)->schema()['strony'][0]['wymagania'];
    $this->get(route('audit-types.questionnaire-preview', [$type, '4-2']))->assertOk()->assertSee($requirement);
});
