<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\UserPermissions;
use App\Support\RolePermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (RolePermissionCatalog::names() as $name) {
        Permission::findOrCreate($name, 'web');
    }
    foreach (['superadmin', 'admin', 'auditor'] as $name) {
        Role::findOrCreate($name, 'web');
    }
    $this->manager = User::factory()->create();
    $this->manager->assignRole('superadmin');
    $this->actingAs($this->manager);
});

test('permission access supports any and all filters and excludes archived accounts', function () {
    $first = User::factory()->create(['name' => 'Pierwsza osoba']);
    $first->givePermissionTo(['dashboard.view', 'calendar.view']);
    $second = User::factory()->create(['name' => 'Druga osoba']);
    $second->givePermissionTo('dashboard.view');
    $archived = User::factory()->create(['name' => 'Archiwalna osoba']);
    $archived->givePermissionTo('dashboard.view');
    $archived->delete();
    $url = route('settings.users.permission-access', ['permissions' => ['dashboard.view', 'calendar.view']]);
    $this->get($url)->assertOk()->assertSee($first->email)->assertSee($second->email)->assertDontSee($archived->email)
        ->assertSee('id="permission-access-table"', false)->assertSee('data-sortable="false"', false);
    $this->get($url.'&match=all')->assertOk()->assertSee($first->email)->assertDontSee($second->email);
    $this->get(route('settings.users.permission-access'))->assertOk()->assertSee('Wybierz uprawnienia i kliknij');
    $this->get(route('settings.users.permission-access', ['permissions' => ['forged']]))->assertSessionHasErrors('permissions.0');
});

test('revoking a legacy role grant preserves other permissions and colleagues and records history', function () {
    Role::findByName('auditor')->givePermissionTo(['dashboard.view', 'calendar.view']);
    $target = User::factory()->create();
    $target->assignRole('auditor');
    $other = User::factory()->create();
    $other->assignRole('auditor');
    $this->from(route('settings.users.permission-access'))->delete(route('settings.users.permission-access.revoke', $target), ['permission' => 'dashboard.view'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($target->fresh()->can('dashboard.view'))->toBeFalse();
    expect($target->fresh()->can('calendar.view'))->toBeTrue();
    expect($other->fresh()->can('dashboard.view'))->toBeTrue();
    expect(ActivityLog::where('action', 'permissions_updated')->where('auditable_id', $target->id)->exists())->toBeTrue();
});

test('permission management rejects viewers peers protected users and uneditable permissions', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $manager->givePermissionTo(['settings.users.manage', 'settings.users.view', 'dashboard.view']);
    app(UserPermissions::class)->initialize($manager);
    $manager->syncPermissions(['settings.users.manage', 'settings.users.view', 'dashboard.view']);
    $peer = User::factory()->create();
    $peer->assignRole('admin');
    $target = User::factory()->create();
    $target->assignRole('auditor');
    $target->givePermissionTo('calendar.view');
    $this->actingAs($manager);
    $this->delete(route('settings.users.permission-access.revoke', $peer), ['permission' => 'dashboard.view'])->assertForbidden();
    $this->delete(route('settings.users.permission-access.revoke', $this->manager), ['permission' => 'dashboard.view'])->assertForbidden();
    $this->delete(route('settings.users.permission-access.revoke', $target), ['permission' => 'calendar.view'])->assertSessionHasErrors('permission');
    expect($target->fresh()->can('calendar.view'))->toBeTrue();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('settings.users.view');
    $this->actingAs($viewer)->get(route('settings.users.permission-access'))->assertForbidden();
    $this->delete(route('settings.users.permission-access.revoke', $target), ['permission' => 'dashboard.view'])->assertForbidden();
});

test('full access is visible but does not imply sensitive settings or vehicle subscription', function () {
    $full = User::factory()->create();
    $full->givePermissionTo('system.full_access');
    $this->get(route('settings.users.permission-access', ['permissions' => ['dashboard.view']]))->assertOk()->assertSee($full->email)->assertSee('pełny dostęp operacyjny');
    foreach (['settings.users.manage', 'hr.vehicles.responsible'] as $permission) {
        $this->get(route('settings.users.permission-access', ['permissions' => [$permission]]))->assertOk()->assertDontSee($full->email);
    }
});
