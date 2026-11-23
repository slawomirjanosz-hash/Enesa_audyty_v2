<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Offer;
use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('status submitted by the list form is saved even with an empty new status field', function ($empty) {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $company = Company::create(['name' => 'Klient', 'company_type' => 'client']);
    $offer = Offer::create(['company_id' => $company->id, 'offer_number' => 'OF-STATUS', 'status' => 'w_toku']);
    $this->actingAs($admin);
    foreach (['wygrana', 'przegrana', 'anulowana', 'w_negocjacji', 'w_toku'] as $status) {
        $this->from(route('offers.index'))->patch(route('offers.status', $offer), ['status' => $status, 'new_status' => $empty, 'create_project' => '0'])
            ->assertSessionHasNoErrors()->assertRedirect(route('offers.index'));
        expect($offer->fresh()->status)->toBe($status);
        $html = $this->get(route('offers.index'))->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        expect($xpath->evaluate('string(//select[@data-offer-status-select]/option[@selected]/@value)'))->toBe($status);
    }
})->with(['empty string' => '', 'null' => null, 'spaces' => '   ']);

test('accepting project creation opens an editable prefilled modal without creating a project', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('superadmin'));
    $company = Company::create(['name' => 'Klient projektu', 'company_type' => 'client']);
    $offer = Offer::create(['company_id' => $company->id, 'offer_number' => 'OF-WON', 'offer_title' => 'Modernizacja kotłowni', 'kwota_netto' => 125000.50, 'status' => 'w_toku']);
    $url = route('projects.index', ['from_offer' => $offer->id]);
    $this->actingAs($admin)->patch(route('offers.status', $offer), ['status' => 'wygrana', 'new_status' => '', 'create_project' => '1'])
        ->assertSessionHasNoErrors()->assertRedirect($url);
    expect($offer->fresh()->status)->toBe('wygrana')->and(Project::count())->toBe(0);
    $html = $this->get($url)->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($dom);
    expect($xpath->evaluate('string(//*[@id="project-modal"]/@class)'))->toContain('open');
    expect($xpath->evaluate('string(//*[@id="project-modal"]//input[@name="name"]/@value)'))->toBe('Modernizacja kotłowni');
    expect($xpath->evaluate('string(//*[@id="project-modal"]//select[@name="company_id"]/option[@selected]/@value)'))->toBe((string) $company->id);
    expect((float) $xpath->evaluate('string(//*[@id="project-modal"]//input[@name="contract_value"]/@value)'))->toBe(125000.50);
    expect($xpath->query('//*[@id="project-modal"]//*[@disabled or @readonly]')->length)->toBe(0);
    expect(Project::count())->toBe(0);
    $this->post(route('projects.store'), ['name' => 'Zmieniona nazwa', 'number' => 'P-TEST', 'company_id' => $company->id, 'manager_id' => $admin->id, 'status' => 'planned', 'contract_value' => 99000])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(Project::sole()->name)->toBe('Zmieniona nazwa')->and((float) Project::sole()->contract_value)->toBe(99000.0);
});

test('prefilling from offers cannot bypass project creation permissions or the offers module', function () {
    $user = User::factory()->create(['individual_permissions' => true]);
    $user->assignRole(Role::findOrCreate('employee'));
    foreach (['projects.view', 'offers.view', 'offers.edit'] as $name) {
        $user->givePermissionTo(Permission::findOrCreate($name));
    }
    $company = Company::create(['name' => 'Klient', 'company_type' => 'client']);
    $offer = Offer::create(['company_id' => $company->id, 'offer_number' => 'OF-PRIVATE', 'status' => 'wygrana', 'assigned_user_id' => $user->id]);
    $this->actingAs($user)->get(route('projects.index', ['from_offer' => $offer->id]))->assertForbidden();
    $this->get(route('offers.index'))->assertOk()->assertDontSee('name="create_project"', false);
    $this->from(route('offers.index'))->patch(route('offers.status', $offer), ['status' => 'wygrana', 'new_status' => '', 'create_project' => '1'])->assertRedirect(route('offers.index'));
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('superadmin'));
    CompanySettings::create(['name' => 'Firma', 'enabled_modules' => ['projects']]);
    $this->actingAs($admin)->get(route('projects.index', ['from_offer' => $offer->id]))->assertForbidden();
    expect(Project::count())->toBe(0);
});
