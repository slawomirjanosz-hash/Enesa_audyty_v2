<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['superadmin', 'admin', 'auditor_senior', 'auditor', 'client_admin', 'client_user'] as $role) {
        Role::findOrCreate($role);
    }
});

test('client user cannot access internal staff panels', function () {
    $clientUser = User::factory()->create();
    $clientUser->assignRole('client_user');

    foreach (['/dashboard', '/crm', '/offers', '/documents', '/client-zone'] as $url) {
        $this->actingAs($clientUser)->get($url)->assertForbidden();
    }
});

test('client user can access the client dashboard and profile', function () {
    $clientUser = User::factory()->create();
    $clientUser->assignRole('client_user');

    $company = Company::create(['name' => 'Firma testowa']);
    $company->users()->attach($clientUser, ['is_admin' => false]);

    $this->actingAs($clientUser)->get('/client/dashboard')->assertOk();
    $this->actingAs($clientUser)->get('/profile')->assertOk();
});

test('admin can access dashboard, CRM and documents', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/dashboard')->assertOk();
    $this->actingAs($admin)->get('/crm')->assertOk();
    $this->actingAs($admin)->get('/documents')->assertOk();
});

test('guest is redirected to login for CRM', function () {
    $this->get('/crm')->assertRedirect(route('login'));
});
