<?php

use App\Models\ActivityLog;
use App\Models\HrVehicle;
use App\Models\User;
use App\Services\HrVehicleReminderSelection;
use App\Services\UserPermissions;
use App\Support\RolePermissionCatalog;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (RolePermissionCatalog::names() as $name) {
        Permission::findOrCreate($name, 'web');
    }
    foreach (['superadmin', 'admin', 'auditor_senior', 'auditor', 'client_user'] as $name) {
        Role::findOrCreate($name, 'web');
    }
    $this->manager = User::factory()->create();
    $this->manager->assignRole('superadmin');
    $this->actingAs($this->manager);
});

function individualAccount(array $changes = []): array
{
    return $changes + ['name' => 'Pracownik testowy', 'email' => 'individual@example.test', 'role' => 'auditor', 'password' => '', 'send_email' => '0'];
}

function individualEmployee(string $role = 'auditor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    app(UserPermissions::class)->initialize($user);

    return $user;
}

test('role initializes a new employee once and later template changes do not change that employee', function () {
    $role = Role::findByName('auditor');
    $role->syncPermissions(['dashboard.view', 'hr.delegations.view']);
    $this->post(route('settings.users.store'), individualAccount())->assertSessionHasNoErrors();
    $user = User::where('email', 'individual@example.test')->firstOrFail();
    expect($user->individual_permissions)->toBeTrue();
    expect($user->can('dashboard.view'))->toBeTrue();
    $this->put(route('settings.roles.update', $role), ['display_name' => 'Audytor', 'permissions' => ['calendar.view']])->assertSessionHasNoErrors();
    $user = $user->fresh();
    expect($user->can('dashboard.view'))->toBeTrue()->and($user->can('calendar.view'))->toBeFalse();
    expect($user->getAllPermissions()->pluck('name')->all())->toContain('dashboard.view')->not->toContain('calendar.view');
    $this->post(route('settings.users.store'), individualAccount(['email' => 'next@example.test']))->assertSessionHasNoErrors();
    expect(User::where('email', 'next@example.test')->firstOrFail()->can('calendar.view'))->toBeTrue();
});

test('one employee can lose a role permission and gain responsibility without changing colleagues', function () {
    Role::findByName('auditor')->syncPermissions(['dashboard.view', 'hr.delegations.view']);
    $first = individualEmployee();
    $second = individualEmployee();
    $this->put(route('settings.users.update', $first), individualAccount(['email' => $first->email, 'permissions_present' => '1', 'permissions' => ['hr.delegations.view', 'hr.vehicles.responsible']]))->assertSessionHasNoErrors();
    expect($first->fresh()->can('dashboard.view'))->toBeFalse();
    expect($first->fresh()->can('hr.vehicles.responsible'))->toBeTrue();
    expect($second->fresh()->can('dashboard.view'))->toBeTrue();
    expect($second->fresh()->can('hr.vehicles.responsible'))->toBeFalse();
    expect(Role::findByName('auditor')->hasPermissionTo('hr.vehicles.responsible'))->toBeFalse();
    $log = ActivityLog::where('action', 'permissions_updated')->where('auditable_id', $first->id)->firstOrFail();
    expect($log->user_id)->toBe($this->manager->id);
    expect($log->changes['permissions']['new'])->toContain('hr.vehicles.responsible')->not->toContain('dashboard.view');
    $this->actingAs($first->fresh())->get(route('dashboard'))->assertForbidden();
});

test('empty selection removes editable rights and changing user details or role does not reset them', function () {
    Role::findByName('auditor')->syncPermissions(['dashboard.view']);
    Role::findByName('auditor_senior')->syncPermissions(['calendar.view']);
    $user = individualEmployee();
    $this->put(route('settings.users.update', $user), individualAccount(['email' => $user->email, 'permissions_present' => '1']))->assertSessionHasNoErrors();
    expect($user->fresh()->getAllPermissions())->toBeEmpty();
    $this->put(route('settings.users.update', $user), individualAccount(['email' => $user->email, 'name' => 'Zmienione dane', 'role' => 'auditor_senior']))->assertSessionHasNoErrors();
    expect($user->fresh()->getAllPermissions())->toBeEmpty();
});

test('forged permission grants and peer or superadmin edits are rejected before account mutation', function () {
    Role::findByName('auditor_senior')->syncPermissions([]);
    $senior = User::factory()->create();
    $senior->assignRole('auditor_senior');
    $senior->givePermissionTo(['settings.users.manage', 'settings.users.view', 'dashboard.view']);
    app(UserPermissions::class)->initialize($senior);
    expect($senior->fresh()->checkPermissionTo('settings.users.manage'))->toBeTrue();
    expect($senior->fresh()->checkPermissionTo('settings.roles.manage'))->toBeFalse();
    $user = individualEmployee();
    $this->actingAs($senior->fresh())->put(route('settings.users.update', $user), individualAccount(['email' => $user->email, 'name' => 'Forged', 'permissions_present' => '1', 'permissions' => ['system.full_access']]))->assertRedirect()->assertSessionHasErrors('permissions');
    expect($user->fresh()->name)->not->toBe('Forged');
    expect($user->fresh()->can('system.full_access'))->toBeFalse();
    $this->put(route('settings.users.update', $this->manager), individualAccount(['email' => $this->manager->email]))->assertForbidden();
    $this->put(route('settings.users.update', $senior), individualAccount(['email' => $senior->email]))->assertForbidden();
    $this->post(route('settings.users.store'), individualAccount(['permissions_present' => '1', 'permissions' => ['settings.roles.manage']]))->assertSessionHasErrors('permissions');
    expect(User::where('email', 'individual@example.test')->exists())->toBeFalse();
});

test('client accounts cannot receive employee permissions or retain them when converted', function () {
    $user = individualEmployee();
    $user->givePermissionTo('hr.vehicles.responsible');
    $this->put(route('settings.users.update', $user), individualAccount(['email' => $user->email, 'role' => 'client_user', 'permissions_present' => '1', 'permissions' => ['hr.vehicles.responsible']]))->assertSessionHasErrors('permissions');
    expect($user->fresh()->hasRole('auditor'))->toBeTrue();
    $this->put(route('settings.users.update', $user), individualAccount(['email' => $user->email, 'role' => 'client_user']))->assertSessionHasNoErrors();
    expect($user->fresh()->getAllPermissions())->toBeEmpty();
});

test('fleet notifications follow only individual subscribers and never include another persons private car', function () {
    $this->travelTo(now('Europe/Warsaw')->setDate(2026, 10, 19)->setTime(8, 0));
    Role::findByName('auditor')->syncPermissions(['hr.delegations.view', 'hr.vehicles.responsible']);
    $first = individualEmployee();
    $second = individualEmployee();
    app(UserPermissions::class)->apply($second, $this->manager, ['hr.delegations.view']);
    HrVehicle::create(['type' => 'company', 'name' => 'Służbowy', 'registration_number' => 'COMPANY', 'inspection_due_at' => now()->subDay()]);
    HrVehicle::create(['type' => 'private', 'name' => 'Prywatny', 'registration_number' => 'PRIVATE', 'created_by' => $second->id, 'user_id' => $second->id, 'inspection_due_at' => now()->subDay()]);
    $groups = app(HrVehicleReminderSelection::class)->groups();
    expect($groups->keys()->all())->toBe([$first->id]);
    expect($groups->first()->pluck('vehicle.registration_number')->unique()->all())->toBe(['COMPANY']);
});

test('migration preserves union of all roles and direct permissions for active and archived users', function () {
    Role::findByName('auditor')->givePermissionTo('dashboard.view');
    Role::findByName('auditor_senior')->givePermissionTo('calendar.view');
    $user = User::factory()->create();
    $user->assignRole(['auditor', 'auditor_senior']);
    $user->givePermissionTo('hr.vehicles.responsible');
    $archived = User::factory()->create();
    $archived->assignRole('auditor');
    $archived->delete();
    Schema::table('users', fn ($table) => $table->dropColumn('individual_permissions'));
    $migration = require database_path('migrations/2026_10_17_200000_snapshot_individual_user_permissions.php');
    $migration->up();
    expect($user->fresh()->individual_permissions)->toBeTrue();
    expect($user->fresh()->permissions->pluck('name')->all())->toContain('dashboard.view', 'calendar.view', 'hr.vehicles.responsible');
    expect(User::withTrashed()->findOrFail($archived->id)->permissions->pluck('name')->all())->toContain('dashboard.view');
    Role::findByName('auditor')->syncPermissions([]);
    expect($user->fresh()->can('dashboard.view'))->toBeTrue();
});

test('permission editor and role templates explain independent access without exposing protected source users', function () {
    $employee = individualEmployee();
    $this->get(route('settings.users.index'))->assertOk()->assertSee('Uprawnienia i odpowiedzialności')->assertSee('Wczytaj uprawnienia z roli')->assertSee('user-permission-data', false)
        ->assertSee('data-copy-permissions="edit"', false)->assertSee('Odpowiedzialność za samochody');
    $this->get(route('settings.roles.index'))->assertOk()->assertSee('Role są szablonami startowymi');
});
