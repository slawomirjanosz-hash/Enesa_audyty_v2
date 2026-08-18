<?php

use App\Models\Company;
use App\Models\Offer;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

test('admin can edit a legacy offer with optional fields left empty', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create([
        'name' => 'Starszy klient oferty',
        'company_type' => 'client',
        'status' => 'active',
    ]);
    $offer = Offer::create([
        'company_id' => $company->id,
        'offer_number' => 'OF_LEGACY_001',
        'offer_full_number' => 'OF_LEGACY_001',
        'status' => 'w_toku',
    ]);

    $this->actingAs($admin)
        ->get(route('offers.edit', $offer))
        ->assertOk()
        ->assertSee('OF_LEGACY_001')
        ->assertSee('Zapisz ofertę');
});

test('missing calendar permission during deployment does not break offer editor', function () {
    Permission::query()->whereIn('name', ['calendar.view', 'calendar.team.view'])->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Klient podczas migracji', 'company_type' => 'client', 'status' => 'active']);
    $offer = Offer::create([
        'company_id' => $company->id,
        'offer_number' => 'OF_DEPLOY_001',
        'offer_full_number' => 'OF_DEPLOY_001',
        'status' => 'w_toku',
    ]);

    $this->actingAs($admin)
        ->get(route('offers.edit', $offer))
        ->assertOk()
        ->assertSee('Zapisz ofertę')
        ->assertDontSee('href="'.route('calendar.index').'"', false);

    $this->actingAs($admin)->get(route('calendar.index'))->assertForbidden();
});
