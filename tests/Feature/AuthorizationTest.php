<?php

use App\Models\Company;
use App\Models\CompanySettings;
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

test('only superadmin can access company settings', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $auditorSenior = User::factory()->create();
    $auditorSenior->assignRole('auditor_senior');

    $this->actingAs($superadmin)->get('/settings/company')->assertOk();
    $this->actingAs($admin)->get('/settings/company')->assertForbidden();
    $this->actingAs($auditorSenior)->get('/settings/company')->assertForbidden();
});

test('company owner can choose a universal or login-only welcome screen', function () {
    CompanySettings::create([
        'name' => 'Firma uniwersalna',
        'primary_color' => '#1A4D3A',
        'welcome_page_mode' => 'general',
    ]);

    $this->get('/')->assertOk()->assertSee('Firma uniwersalna')->assertDontSee('Audyty energetyczne dla przemysłu');

    CompanySettings::query()->update(['welcome_page_mode' => 'login_only']);

    $this->get('/')->assertRedirect(route('login'));
});

test('admin can create and delegate a custom role without granting company settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post('/settings/roles', [
            'name' => '  Kierownik   Projektu  ',
            'permissions' => ['system.full_access'],
        ])
        ->assertRedirect('/settings/roles');

    $this->actingAs($admin)
        ->from('/settings/roles')
        ->post('/settings/roles', ['name' => 'kierownik projektu'])
        ->assertRedirect('/settings/roles')
        ->assertSessionHasErrors('name');

    $this->actingAs($admin)
        ->post('/settings/users', [
            'name' => 'Kierownik Testowy',
            'email' => 'kierownik@example.test',
            'role' => 'Kierownik Projektu',
        ])
        ->assertRedirect('/settings/users');

    $user = User::where('email', 'kierownik@example.test')->firstOrFail();

    expect($user->hasRole('Kierownik Projektu'))->toBeTrue();

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->actingAs($user)->get('/crm')->assertOk();
    $this->actingAs($user)->get('/documents')->assertOk();
    $this->actingAs($user)->get('/pricing-catalog')->assertOk();
    $this->actingAs($user)->get('/settings/company')->assertForbidden();
    $this->actingAs($user)->get('/settings/roles')->assertForbidden();
});

test('guest is redirected to login for CRM', function () {
    $this->get('/crm')->assertRedirect(route('login'));
});
