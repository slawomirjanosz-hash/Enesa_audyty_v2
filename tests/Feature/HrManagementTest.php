<?php

use App\Models\HrAttendance;
use App\Models\HrBusinessTrip;
use App\Models\HrVehicle;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['superadmin', 'admin', 'employee_hr', 'manager_hr'] as $role) {
        Role::findOrCreate($role);
    }
});

test('employee creates own delegation with calculated return and remembered private car', function () {
    $employee = User::factory()->create();
    $role = Role::findOrCreate('employee_hr');
    $role->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $employee->assignRole($role);

    $this->actingAs($employee)->post(route('hr.delegations.store'), [
        'purpose' => 'Spotkanie z projektantem',
        'departure_at' => '2026-08-26 08:00',
        'travel_hours' => 3.5,
        'origin' => 'Cieszyn',
        'destination' => 'Kraków',
        'distance_km' => 150.4,
        'vehicle_type' => 'private',
        'vehicle_name' => 'Moja Skoda',
        'registration_number' => 'SCI 12345',
        'toll_cost' => 32.50,
        'remember_vehicle' => 1,
    ])->assertRedirect(route('hr.index', ['tab' => 'delegations']));

    $trip = HrBusinessTrip::firstOrFail();
    expect($trip->user_id)->toBe($employee->id)
        ->and($trip->return_at?->format('Y-m-d H:i'))->toBe('2026-08-26 11:30')
        ->and($trip->days)->toBe(1)
        ->and(HrVehicle::where('registration_number', 'SCI 12345')->where('user_id', $employee->id)->exists())->toBeTrue();

    $this->actingAs($employee)->get(route('hr.index', ['tab' => 'delegations']))
        ->assertOk()->assertSee('Spotkanie z projektantem')->assertSee('SCI 12345');
});

test('ordinary HR employee cannot see or create records for another employee', function () {
    $employee = User::factory()->create();
    $other = User::factory()->create();
    $role = Role::findOrCreate('employee_hr');
    $role->givePermissionTo([Permission::findOrCreate('hr.delegations.view'), Permission::findOrCreate('hr.attendance.view')]);
    $employee->assignRole($role);
    HrBusinessTrip::create(['user_id' => $other->id, 'purpose' => 'Tajna delegacja', 'departure_at' => now(), 'days' => 1, 'origin' => 'A', 'destination' => 'B', 'vehicle_type' => 'company']);

    $this->actingAs($employee)->get(route('hr.index', ['tab' => 'delegations', 'user_id' => $other->id]))
        ->assertOk()->assertDontSee('Tajna delegacja')->assertDontSee('Wszyscy użytkownicy');

    $this->actingAs($employee)->post(route('hr.attendance.store'), ['user_id' => $other->id, 'work_date' => '2026-08-26', 'status' => 'present']);
    expect(HrAttendance::firstOrFail()->user_id)->toBe($employee->id);
});

test('HR team manager filters users and manages attendance and company cars', function () {
    $manager = User::factory()->create();
    $employee = User::factory()->create(['name' => 'Jan Pracownik']);
    $role = Role::findOrCreate('manager_hr');
    $role->givePermissionTo([
        Permission::findOrCreate('hr.delegations.view'), Permission::findOrCreate('hr.attendance.view'), Permission::findOrCreate('hr.team.view'),
    ]);
    $manager->assignRole($role);

    $this->actingAs($manager)->post(route('hr.attendance.store'), ['user_id' => $employee->id, 'work_date' => '2026-08-26', 'started_at' => '08:00', 'finished_at' => '16:00', 'status' => 'present'])->assertSessionHas('success');
    $this->actingAs($manager)->post(route('hr.vehicles.store'), ['type' => 'company', 'name' => 'Auto firmowe 1', 'make_model' => 'Ford Transit', 'registration_number' => 'SB 99999'])->assertSessionHas('success');

    $this->actingAs($manager)->get(route('hr.index', ['tab' => 'attendance', 'user_id' => $employee->id]))
        ->assertOk()->assertSee('Wszyscy użytkownicy')->assertSee('Jan Pracownik')->assertSee('08:00');
    $this->actingAs($manager)->get(route('hr.index', ['tab' => 'vehicles']))
        ->assertOk()->assertSee('Auto firmowe 1')->assertSee('Ford Transit');
});

test('attendance-only role cannot open delegation data', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('attendance_only');
    $role->givePermissionTo(Permission::findOrCreate('hr.attendance.view'));
    $user->assignRole($role);

    $this->actingAs($user)->get(route('hr.index', ['tab' => 'delegations']))
        ->assertOk()->assertSee('Lista obecności')->assertDontSee('Dodaj delegację');
    $this->actingAs($user)->post(route('hr.delegations.store'), [])->assertForbidden();
});
