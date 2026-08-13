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

    $this->actingAs($admin)
        ->get('/settings/users')
        ->assertOk()
        ->assertSee('Kierownik Testowy')
        ->assertSee('kierownik@example.test')
        ->assertSee('Kierownik Projektu');

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->actingAs($user)->get('/crm')->assertOk();
    $this->actingAs($user)->get('/documents')->assertOk();
    $this->actingAs($user)->get('/pricing-catalog')->assertOk();
    $this->actingAs($user)->get('/settings/company')->assertForbidden();
    $this->actingAs($user)->get('/settings/roles')->assertForbidden();
});

test('admin can rename a custom role and delete it only when no user is assigned', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::create(['name' => 'Kierownik Projektu', 'guard_name' => 'web']);
    $employee = User::factory()->create(['email' => 'pracownik@example.test']);
    $employee->assignRole($role);

    $this->actingAs($admin)
        ->put("/settings/roles/{$role->id}", [
            'name' => 'Dyrektor Projektu',
            'permissions' => ['system.full_access'],
        ])
        ->assertRedirect('/settings/roles');

    expect($role->fresh()->name)->toBe('Dyrektor Projektu')
        ->and($employee->fresh()->hasRole('Dyrektor Projektu'))->toBeTrue();

    $this->actingAs($admin)
        ->delete("/settings/roles/{$role->id}")
        ->assertUnprocessable();

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue()
        ->and($employee->fresh()->hasRole('Dyrektor Projektu'))->toBeTrue();

    $employee->removeRole($role);

    $this->actingAs($admin)
        ->delete("/settings/roles/{$role->id}")
        ->assertRedirect('/settings/roles')
        ->assertSessionHas('success');

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($employee->id)->exists())->toBeTrue()
        ->and($employee->fresh()->roles)->toBeEmpty();

    $this->actingAs($admin)
        ->get('/settings/users')
        ->assertOk()
        ->assertSee('pracownik@example.test')
        ->assertSee('—');
});

test('superadmin manages system roles and granular custom role access', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $this->actingAs($superadmin)
        ->get('/settings/roles')
        ->assertOk()
        ->assertSee('Super Admin')
        ->assertSee('Administrator')
        ->assertSee('Audytor')
        ->assertSee('Zarządzanie finansami projektu');

    $this->actingAs($superadmin)->post('/settings/roles', [
        'name' => 'Kontroler Projektu',
        'permissions' => ['projects.view', 'projects.finances.manage'],
    ])->assertRedirect('/settings/roles');

    $role = Role::findByName('Kontroler Projektu');
    $employee = User::factory()->create();
    $employee->assignRole($role);

    $this->actingAs($employee)->get('/projects')->assertOk();
    $this->actingAs($employee)->get('/crm')->assertForbidden();
    $this->actingAs($employee)->get('/settings/roles')->assertForbidden();

    expect($role->hasPermissionTo('projects.finances.manage'))->toBeTrue()
        ->and($role->hasPermissionTo('projects.requirements.manage'))->toBeFalse();
});

test('guest is redirected to login for CRM', function () {
    $this->get('/crm')->assertRedirect(route('login'));
});

test('delegated auditor cannot modify companies or their users', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');

    $company = Company::create(['name' => 'Firma chroniona']);
    $client = User::factory()->create();
    $client->assignRole('client_user');
    $company->users()->attach($client, ['is_admin' => false]);

    $this->actingAs($auditor)
        ->post('/companies', ['name' => 'Niedozwolona firma', 'company_type' => 'client'])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post("/companies/{$company->id}/users", [])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->put("/companies/{$company->id}/users/{$client->id}", [])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->delete("/companies/{$company->id}/users/{$client->id}")
        ->assertForbidden();

    $this->actingAs($auditor)
        ->delete("/companies/{$company->id}")
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post("/companies/{$company->id}/restore")
        ->assertForbidden();

    $this->actingAs($auditor)
        ->delete('/crm/orphaned-users/999999')
        ->assertForbidden();

    expect(Company::where('name', 'Niedozwolona firma')->exists())->toBeFalse()
        ->and($company->users()->whereKey($client->id)->exists())->toBeTrue();
});

test('company user update cannot target a user from another company', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $firstCompany = Company::create(['name' => 'Pierwsza firma']);
    $secondCompany = Company::create(['name' => 'Druga firma']);
    $client = User::factory()->create();
    $client->assignRole('client_user');
    $secondCompany->users()->attach($client, ['is_admin' => false]);

    $this->actingAs($admin)
        ->put("/companies/{$firstCompany->id}/users/{$client->id}", [
            'name' => 'Nieautoryzowana zmiana',
            'email' => $client->email,
            'role' => 'client_user',
        ])
        ->assertNotFound();

    expect($client->fresh()->name)->not->toBe('Nieautoryzowana zmiana');
});

test('delegated auditor cannot mutate shared configuration or impersonate clients', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');

    $this->actingAs($auditor)
        ->post('/pricing-catalog', [])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post('/offer-forms', [])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->get('/client-zone')
        ->assertForbidden();
});
