<?php

use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferDelegation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

test('admin can edit an older offer containing malformed editor data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Klient starszej oferty', 'company_type' => 'client', 'status' => 'active']);
    $offer = Offer::create([
        'company_id' => $company->id,
        'offer_number' => 'OF_OLD_001',
        'offer_full_number' => 'OF_OLD_001',
        'status' => 'w_toku',
    ]);

    DB::table('offers')->where('id', $offer->id)->update([
        'valid_until' => 'not-a-date',
        'price_sections' => 'not-json',
        'text_sections' => 'not-json',
        'delegations' => 'not-json',
    ]);

    $this->actingAs($admin)
        ->get(route('offers.edit', $offer))
        ->assertOk()
        ->assertSee('OF_OLD_001')
        ->assertSee('Zapisz ofertę');
});

test('missing optional staff role does not break offer forms', function () {
    Role::query()->where('name', 'auditor_senior')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Klient bez starszej roli', 'company_type' => 'client', 'status' => 'active']);
    $offer = Offer::create([
        'company_id' => $company->id,
        'offer_number' => 'OF_NO_ROLE_001',
        'offer_full_number' => 'OF_NO_ROLE_001',
        'status' => 'w_toku',
    ]);

    $this->actingAs($admin)
        ->get(route('offers.create'))
        ->assertOk()
        ->assertSee('moveTextSection', false)
        ->assertSee('clearTextSection', false)
        ->assertSee('enableResizableTables', false);

    $this->actingAs($admin)
        ->get(route('offers.edit', $offer))
        ->assertOk()
        ->assertSee('OF_NO_ROLE_001')
        ->assertSee('moveTextSection', false)
        ->assertSee('clearTextSection', false)
        ->assertSee('enableResizableTables', false);
});

test('empty delegation list is not rebuilt from the legacy delegation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Klient bez delegacji', 'company_type' => 'client', 'status' => 'active']);
    $offer = Offer::create([
        'company_id' => $company->id,
        'offer_number' => 'OF_NO_DELEGATION_001',
        'offer_full_number' => 'OF_NO_DELEGATION_001',
        'status' => 'w_toku',
    ]);

    OfferDelegation::create([
        'offer_id' => $offer->id,
        'km_do_klienta' => 777,
        'liczba_wyjazdow' => 1,
        'liczba_noc' => 0,
        'liczba_osob' => 1,
    ]);
    $this->actingAs($admin)
        ->put(route('offers.update', $offer), [
            'company_id' => $company->id,
            'offer_number' => $offer->offer_number,
            'status' => 'w_toku',
            'delegations' => '[]',
            'liczba_wyjazdow' => 1,
            'liczba_noc' => 0,
            'liczba_osob' => 1,
            'stawka_noc' => 0,
        ])
        ->assertRedirect(route('offers.show', $offer));

    $this->assertDatabaseMissing('offer_delegations', ['offer_id' => $offer->id]);

    $this->actingAs($admin)
        ->get(route('offers.edit', $offer))
        ->assertOk()
        ->assertDontSee('km:       777', false);
});
