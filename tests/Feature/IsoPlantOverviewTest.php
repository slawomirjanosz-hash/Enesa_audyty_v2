<?php

use App\Models\IsoPlantProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('plant overview creates a named assigned profile directly and returns to ISO introduction for both roles', function () {
    [$audit, $client, $staff] = plantFixture();
    foreach ([[$client, 'client.audits.'], [$staff, 'audits.']] as [$actor, $prefix]) {
        $url = route($prefix.'show', [$audit, 'tab' => 'iso50001', 'section' => 'intro']);
        $response = $this->actingAs($actor)->get($url)->assertOk()->assertSee('id="plant-create-dialog"', false)->assertSee('data-plant-create-open', false)->assertDontSee('#add-profile')->assertDontSee('Istniejący zakład');
        expect(IsoPlantProfile::count())->toBe($actor->id === $client->id ? 0 : 1);
        $this->get(route($prefix.'plant-profile.index', $audit))->assertRedirect(route($prefix.'show', [$audit, 'tab' => 'iso50001', 'section' => 'intro', 'add_profile' => 1]));
        $this->from($url)->post(route($prefix.'plant-profile.create', $audit), ['return_to' => 'overview', 'name' => 'Zakład '.$actor->id, 'responsible_user_id' => $client->id])->assertRedirect($url)->assertSessionHasNoErrors();
        $profile = IsoPlantProfile::orderByDesc('id')->firstOrFail();
        expect($profile->answers['site.name']['value'])->toBe('Zakład '.$actor->id)->and($profile->responsible_user_id)->toBe($client->id)->and($profile->status)->toBe('editing')->and($profile->client_approval)->toBeNull()->and($profile->auditor_approval)->toBeNull();
        $this->get($url)->assertOk()->assertSee('Zakład '.$actor->id)->assertSee($client->name);
    }
    if (getenv('PLANT_OVERVIEW_BROWSER_QA')) {
        enmsBrowserExport('plant-overview-browser', [$audit, $client, $staff, $profile]);
    }
});

test('plant overview validates the modal and keeps company and write permission boundaries', function () {
    [$audit, $client, $staff] = plantFixture();
    [$otherAudit, $otherClient] = plantFixture();
    $url = route('client.audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'intro']);
    $create = route('client.audits.plant-profile.create', $audit);
    $this->actingAs($client)->from($url)->post($create, ['return_to' => 'overview', 'name' => '', 'responsible_user_id' => $otherClient->id])->assertRedirect($url)->assertSessionHasErrors(['name', 'responsible_user_id'], errorBag: 'plantCreate');
    $this->get($url)->assertOk()->assertSee('aria-invalid="true"', false)->assertSee("document.getElementById('plant-create-dialog').showModal()", false)->assertDontSee($otherClient->email);
    $this->postJson($create, ['return_to' => 'overview', 'name' => 'Nowy', 'site_id' => 1, 'copy_latest' => true])->assertUnprocessable();
    $this->postJson($create, ['return_to' => 'https://example.test', 'name' => 'Nowy'])->assertUnprocessable();
    $this->actingAs($otherClient)->postJson($create, ['return_to' => 'overview', 'name' => 'Obcy'])->assertNotFound();
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('employee'));
    $viewer->givePermissionTo(Permission::findOrCreate('audits.view'));
    $this->actingAs($viewer)->get(route('audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'intro']))->assertOk()->assertDontSee('id="plant-create-dialog"', false)->assertDontSee('data-plant-create-open');
    $this->get(route('audits.plant-profile.index', $audit))->assertRedirect(route('audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'intro']));
    $this->postJson(route('audits.plant-profile.create', $audit), ['return_to' => 'overview', 'name' => 'Brak uprawnień'])->assertForbidden();
    expect(IsoPlantProfile::count())->toBe(0)->and(DB::table('iso_plant_sites')->count())->toBe(0);
});
