<?php

use App\Models\Company;
use App\Models\HrLeave;
use App\Models\HrLeaveEntitlement;
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

test('admin configures employment contract and yearly leave opening balance', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)->post(route('settings.users.store'), [
        'name' => 'Pracownik etatowy',
        'email' => 'etat@example.test',
        'role' => 'auditor',
        'password' => 'password123',
        'has_employment_contract' => 1,
        'leave_year' => 2026,
        'leave_entitled_days' => 15,
    ])->assertRedirect(route('settings.users.index'));

    $employee = User::where('email', 'etat@example.test')->firstOrFail();
    expect($employee->has_employment_contract)->toBeTrue()
        ->and(HrLeaveEntitlement::where('user_id', $employee->id)->where('year', 2026)->value('entitled_days'))->toBe(15);

    HrLeave::create([
        'user_id' => $employee->id, 'type' => 'annual', 'start_date' => '2026-09-07',
        'end_date' => '2026-09-11', 'days' => 5, 'include_weekends' => false,
    ]);
    HrLeave::create([
        'user_id' => $employee->id, 'type' => 'on_demand', 'start_date' => '2026-09-14',
        'end_date' => '2026-09-14', 'days' => 1, 'include_weekends' => false,
    ]);
    HrLeave::create([
        'user_id' => $employee->id, 'type' => 'sick_leave', 'start_date' => '2026-09-15',
        'end_date' => '2026-09-18', 'days' => 4, 'include_weekends' => false,
    ]);

    expect($employee->annualLeaveBalance(2026))->toBe(['entitled' => 15, 'used' => 6, 'remaining' => 9]);

    $this->actingAs($admin)->put(route('settings.users.update', $employee), [
        'name' => $employee->name, 'email' => $employee->email, 'role' => 'auditor',
        'has_employment_contract' => 1, 'leave_year' => 2027, 'leave_entitled_days' => 26,
    ])->assertRedirect(route('settings.users.index'));

    expect($employee->fresh()->leaveEntitlements()->count())->toBe(2)
        ->and($employee->fresh()->annualLeaveBalance(2027)['entitled'])->toBe(26);
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
