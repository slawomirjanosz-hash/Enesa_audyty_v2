<?php

use App\Models\IsoPlantProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('staff creates an assigned profile while the client sees only questionnaire entry', function () {
    [$audit, $client, $staff] = plantFixture();
    foreach ([[$staff, 'audits.']] as [$actor, $prefix]) {
        $url = route($prefix.'show', [$audit, 'tab' => 'iso50001', 'section' => 'intro']);
        $response = $this->actingAs($actor)->get($url)->assertOk()->assertSee('id="plant-create-dialog"', false)->assertSee('data-plant-create-open', false)->assertDontSee('#add-profile')->assertDontSee('Istniejący zakład');
        expect(IsoPlantProfile::count())->toBe(0);
        $this->get(route($prefix.'plant-profile.index', $audit))->assertRedirect(route($prefix.'show', [$audit, 'tab' => 'iso50001', 'section' => 'intro', 'add_profile' => 1]));
        $this->from($url)->post(route($prefix.'plant-profile.create', $audit), ['return_to' => 'overview', 'name' => 'Zakład '.$actor->id, 'responsible_user_id' => $client->id])->assertRedirect($url)->assertSessionHasNoErrors();
        $profile = IsoPlantProfile::orderByDesc('id')->firstOrFail();
        expect($profile->answers['site.name']['value'])->toBe('Zakład '.$actor->id)->and($profile->responsible_user_id)->toBe($client->id)->and($profile->status)->toBe('editing')->and($profile->client_approval)->toBeNull()->and($profile->auditor_approval)->toBeNull();
        $this->get($url)->assertOk()->assertSee('Zakład '.$actor->id)->assertSee($client->name);
    }
    $this->actingAs($client)->get(route('client.audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'intro', 'add_profile' => 1]))
        ->assertOk()->assertSee('Wypełnij ankietę')->assertSee($client->name)
        ->assertDontSee('data-plant-create-open')->assertDontSee('id="plant-create-dialog"', false)
        ->assertDontSee('name="responsible_user_id"', false)->assertDontSee('Podgląd profilu')->assertDontSee('Edytuj profil')->assertDontSee('Kopiuj profil');
    $this->get(route('client.audits.plant-profile.index', $audit))->assertRedirect(route('client.audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'intro']));
    if (getenv('PLANT_OVERVIEW_BROWSER_QA')) {
        enmsBrowserExport('plant-overview-browser', [$audit, $client, $staff, $profile]);
    }
});

test('plant overview validates the modal and keeps company and write permission boundaries', function () {
    [$audit, $client, $staff] = plantFixture();
    [$otherAudit, $otherClient] = plantFixture();
    $url = route('audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'intro']);
    $create = route('audits.plant-profile.create', $audit);
    $this->actingAs($staff)->from($url)->post($create, ['return_to' => 'overview', 'name' => '', 'responsible_user_id' => $otherClient->id])->assertRedirect($url)->assertSessionHasErrors(['name', 'responsible_user_id'], errorBag: 'plantCreate');
    $this->get($url)->assertOk()->assertSee('aria-invalid="true"', false)->assertSee("document.getElementById('plant-create-dialog').showModal()", false)->assertDontSee($otherClient->email);
    $this->postJson($create, ['return_to' => 'overview', 'name' => 'Nowy', 'site_id' => 1, 'copy_latest' => true])->assertUnprocessable();
    $this->postJson($create, ['return_to' => 'https://example.test', 'name' => 'Nowy'])->assertUnprocessable();
    $this->actingAs($otherClient)->postJson(route('client.audits.plant-profile.create', $audit), ['return_to' => 'overview', 'name' => 'Obcy'])->assertNotFound();
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('employee'));
    $viewer->givePermissionTo(Permission::findOrCreate('audits.view'));
    $this->actingAs($viewer)->get(route('audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'intro']))->assertOk()->assertDontSee('id="plant-create-dialog"', false)->assertDontSee('data-plant-create-open');
    $this->get(route('audits.plant-profile.index', $audit))->assertRedirect(route('audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'intro']));
    $this->postJson(route('audits.plant-profile.create', $audit), ['return_to' => 'overview', 'name' => 'Brak uprawnień'])->assertForbidden();
    expect(IsoPlantProfile::count())->toBe(0)->and(DB::table('iso_plant_sites')->count())->toBe(0);
});

test('neither client role can create copy or reassign profiles through direct requests', function () {
    [$audit, $client, $staff] = plantFixture();
    createPlantForClient($audit, $client, ['name' => 'Zakład', 'responsible_user_id' => $client->id]);
    $profile = IsoPlantProfile::firstOrFail();
    $before = $profile->toArray();
    $events = DB::table('iso_plant_events')->count();
    foreach (['client_admin', 'client_user'] as $role) {
        $client->syncRoles([Role::findOrCreate($role)]);
        $this->actingAs($client)->postJson(route('client.audits.plant-profile.create', $audit), ['name' => 'Niedozwolony'])->assertForbidden();
        $this->postJson(route('client.audits.plant-profile.copy', [$audit, $profile]))->assertForbidden();
        $this->patchJson(route('client.audits.plant-profile.responsible', [$audit, $profile]), ['responsible_user_id' => null])->assertForbidden();
        $this->postJson(route('audits.plant-profile.create', $audit), ['name' => 'Niedozwolony'])->assertForbidden();
        $this->postJson(route('audits.plant-profile.copy', [$audit, $profile]))->assertForbidden();
        $this->patchJson(route('audits.plant-profile.responsible', [$audit, $profile]), ['responsible_user_id' => null])->assertForbidden();
        $this->get(route('client.audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('value="save"', false);
    }
    expect(IsoPlantProfile::count())->toBe(1)->and($profile->fresh()->toArray())->toBe($before);
    expect(DB::table('iso_plant_events')->count())->toBe($events);
});
