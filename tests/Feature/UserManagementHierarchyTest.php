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

function userWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('a higher ranked employee can archive a staff member assigned only to the owner company', function () {
    $admin = userWithRole('admin');
    $auditor = userWithRole('auditor');
    $ownerCompany = Company::create(['name' => 'ENESA', 'is_owner' => true]);
    $auditor->companies()->attach($ownerCompany->id);

    $this->actingAs($admin)
        ->delete(route('settings.users.destroy', $auditor))
        ->assertRedirect(route('settings.users.index'));

    $this->assertSoftDeleted('users', ['id' => $auditor->id]);
});

test('admin sees every active user and can edit or archive lower ranked accounts', function () {
    $admin = userWithRole('admin');
    $client = userWithRole('client_user');
    $orphanClient = userWithRole('client_user');
    $clientCompany = Company::create(['name' => 'Aktywny klient']);
    $client->companies()->attach($clientCompany->id);

    $this->actingAs($admin)
        ->get(route('settings.users.index'))
        ->assertOk()
        ->assertSee($client->email)
        ->assertSee($orphanClient->email);

    $this->actingAs($admin)
        ->put(route('settings.users.update', $orphanClient), [
            'name' => 'Zmieniony użytkownik',
            'email' => $orphanClient->email,
            'phone' => '',
            'role' => 'client_user',
            'password' => '',
        ])
        ->assertRedirect(route('settings.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $orphanClient->id,
        'name' => 'Zmieniony użytkownik',
    ]);

    $this->actingAs($admin)
        ->delete(route('settings.users.destroy', $client))
        ->assertRedirect(route('settings.users.index'));

    $this->assertSoftDeleted('users', ['id' => $client->id]);
});

test('users cannot manage peers or users higher in the hierarchy', function () {
    $seniorAuditor = userWithRole('auditor_senior');
    $admin = userWithRole('admin');
    $anotherAdmin = userWithRole('admin');

    $this->actingAs($seniorAuditor)
        ->delete(route('settings.users.destroy', $admin))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('settings.users.destroy', $anotherAdmin))
        ->assertForbidden();
});

test('an auditor senior can archive an auditor but cannot create or promote an admin', function () {
    $seniorAuditor = userWithRole('auditor_senior');
    $auditor = userWithRole('auditor');

    $this->actingAs($seniorAuditor)
        ->delete(route('settings.users.destroy', $auditor))
        ->assertRedirect(route('settings.users.index'));

    $this->assertSoftDeleted('users', ['id' => $auditor->id]);

    $this->actingAs($seniorAuditor)
        ->post(route('settings.users.store'), [
            'name' => 'Nieuprawniony admin',
            'email' => 'unauthorized-admin@example.test',
            'role' => 'admin',
            'password' => 'password123',
        ])
        ->assertForbidden();
});

test('a superadmin is never archived or permanently deleted', function () {
    $admin = userWithRole('admin');
    $superadmin = userWithRole('superadmin');

    $this->actingAs($admin)
        ->delete(route('settings.users.destroy', $superadmin))
        ->assertRedirect(route('settings.users.index'));

    $this->actingAs($admin)
        ->delete(route('settings.users.forceDestroy', $superadmin))
        ->assertRedirect(route('settings.users.index'));

    $this->assertDatabaseHas('users', ['id' => $superadmin->id, 'deleted_at' => null]);
});
