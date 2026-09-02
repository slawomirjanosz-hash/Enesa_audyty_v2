<?php

use App\Models\CompanySettings;
use App\Models\HrAttendance;
use App\Models\HrBusinessTrip;
use App\Models\HrLeave;
use App\Models\HrLeaveEntitlement;
use App\Models\HrVehicle;
use App\Models\User;
use Illuminate\Support\Facades\Http;
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
    config(['services.google.maps_key' => 'test-google-maps-key']);
    CompanySettings::create(['name' => 'Firma testowa', 'hr_km_rate' => 1.15, 'hr_diet_rate' => 45]);
    $employee = User::factory()->create();
    $role = Role::findOrCreate('employee_hr');
    $role->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $employee->assignRole($role);

    $this->actingAs($employee)->post(route('hr.delegations.store'), [
        'purpose' => 'Spotkanie z projektantem',
        'departure_at' => '2026-08-26 08:00',
        'outbound_arrival_at' => '2026-08-26 11:30',
        'outbound_travel_hours' => 3.5,
        'return_departure_at' => '2026-08-26 16:00',
        'return_at' => '2026-08-26 19:30',
        'return_travel_hours' => 3.5,
        'origin' => 'Cieszyn',
        'destination' => 'Kraków',
        'distance_km' => 150.4,
        'km_rate' => 1.15,
        'diet_rate' => 45,
        'vehicle_type' => 'private',
        'vehicle_name' => 'Moja Skoda',
        'registration_number' => 'SCI 12345',
        'toll_cost' => 32.50,
        'remember_vehicle' => 1,
    ])->assertRedirect(route('hr.index', ['tab' => 'delegations']));

    $trip = HrBusinessTrip::firstOrFail();
    expect($trip->user_id)->toBe($employee->id)
        ->and($trip->return_at?->format('Y-m-d H:i'))->toBe('2026-08-26 19:30')
        ->and($trip->days)->toBe(1)
        ->and((float) $trip->diet_amount)->toBe(22.5)
        ->and((float) $trip->mileage_amount)->toBe(172.96)
        ->and(HrVehicle::where('registration_number', 'SCI 12345')->where('user_id', $employee->id)->exists())->toBeTrue();

    $this->actingAs($employee)->get(route('hr.index', ['tab' => 'delegations']))
        ->assertOk()->assertSee('Spotkanie z projektantem')->assertSee('SCI 12345')
        ->assertSee('trip-route-fields')->assertSee('Adres lub nazwa miejsca')
        ->assertSee('Inny samochód — wpiszę dane')->assertSee('trip-departure-time');

    Http::fake([
        'places.googleapis.com/*' => Http::response(['suggestions' => [
            ['placePrediction' => ['text' => ['text' => 'Gliwice, Polska']]],
        ]]),
    ]);
    $this->actingAs($employee)->getJson(route('hr.places.autocomplete', ['q' => 'gliw']))
        ->assertOk()->assertJson(['suggestions' => ['Gliwice, Polska']]);

    $this->actingAs($employee)->put(route('hr.delegations.update', $trip), [
        'purpose' => 'Zmieniony cel', 'departure_at' => '2026-08-26 08:00', 'outbound_arrival_at' => '2026-08-26 10:00',
        'outbound_travel_hours' => 2, 'return_departure_at' => '2026-08-27 17:00', 'return_at' => '2026-08-27 19:00',
        'return_travel_hours' => 2, 'origin' => 'Cieszyn', 'destination' => 'Kraków', 'distance_km' => 300,
        'km_rate' => 1, 'diet_rate' => 45, 'vehicle_type' => 'private', 'toll_cost' => 20,
    ])->assertSessionHas('success');
    expect((float) $trip->fresh()->diet_amount)->toBe(90.0)
        ->and((float) $trip->fresh()->total_amount)->toBe(455.0);

    $this->actingAs($employee)->get(route('hr.delegations.show', $trip))
        ->assertOk()->assertSee('Mapa przejazdu')->assertSee('google.com/maps?output=embed', false);

    $this->actingAs($employee)->get(route('hr.delegations.pdf', $trip))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('HR rates apply to saved and manually entered private cars but not company cars', function () {
    $admin = User::factory()->create();
    $employee = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $employeeRole = Role::findOrCreate('employee_hr');
    $employeeRole->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $employee->assignRole($employeeRole);

    $this->actingAs($admin)->put(route('hr.settings.update'), [
        'hr_km_rate' => 1.25,
        'hr_diet_rate' => 50,
    ])->assertSessionHas('success');
    expect((float) CompanySettings::firstOrFail()->hr_km_rate)->toBe(1.25)
        ->and((float) CompanySettings::firstOrFail()->hr_diet_rate)->toBe(50.0);

    $this->actingAs($employee)->put(route('hr.settings.update'), [
        'hr_km_rate' => 9,
        'hr_diet_rate' => 9,
    ])->assertForbidden();

    $companyVehicle = HrVehicle::create(['type' => 'company', 'name' => 'Auto firmowe', 'registration_number' => 'SB 10000']);
    $tripData = [
        'purpose' => 'Wyjazd autem firmowym', 'departure_at' => '2026-08-26 08:00',
        'outbound_arrival_at' => '2026-08-26 10:00', 'outbound_travel_hours' => 2,
        'return_departure_at' => '2026-08-26 16:00', 'return_at' => '2026-08-26 18:00',
        'return_travel_hours' => 2, 'origin' => 'Cieszyn', 'destination' => 'Kraków',
        'distance_km' => 300,
    ];
    $this->actingAs($employee)->post(route('hr.delegations.store'), array_merge($tripData, [
        'vehicle_id' => $companyVehicle->id,
    ]))->assertSessionHas('success');

    expect((float) HrBusinessTrip::firstOrFail()->mileage_amount)->toBe(0.0)
        ->and((float) HrBusinessTrip::firstOrFail()->km_rate)->toBe(1.25);

    $privateVehicle = HrVehicle::create(['user_id' => $employee->id, 'type' => 'private', 'name' => 'Auto prywatne', 'registration_number' => 'SCI 20000']);
    $this->actingAs($employee)->post(route('hr.delegations.store'), array_merge($tripData, [
        'purpose' => 'Wyjazd zapisanym autem prywatnym', 'vehicle_id' => $privateVehicle->id,
    ]))->assertSessionHas('success');
    expect((float) HrBusinessTrip::latest('id')->firstOrFail()->mileage_amount)->toBe(375.0);

    $this->actingAs($employee)->post(route('hr.delegations.store'), array_merge($tripData, [
        'purpose' => 'Wyjazd innym autem prywatnym', 'vehicle_id' => 'manual',
        'vehicle_type' => 'private', 'vehicle_name' => 'Pożyczone auto', 'registration_number' => 'SCI 30000',
    ]))->assertSessionHas('success');
    expect((float) HrBusinessTrip::latest('id')->firstOrFail()->mileage_amount)->toBe(375.0);
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

test('employee manages own leave and a manager can register leave for another user', function () {
    $employee = User::factory()->create(['name' => 'Pracownik Urlopowy', 'has_employment_contract' => true]);
    HrLeaveEntitlement::create(['user_id' => $employee->id, 'year' => 2026, 'entitled_days' => 15]);
    $manager = User::factory()->create();
    $employeeRole = Role::findOrCreate('employee_hr');
    $employeeRole->givePermissionTo(Permission::findOrCreate('hr.leaves.view'));
    $employee->assignRole($employeeRole);
    $managerRole = Role::findOrCreate('manager_hr');
    $managerRole->givePermissionTo([Permission::findOrCreate('hr.leaves.view'), Permission::findOrCreate('hr.team.view')]);
    $manager->assignRole($managerRole);

    $this->actingAs($employee)->post(route('hr.leaves.store'), [
        'type' => 'annual', 'document_date' => '2026-08-20', 'start_date' => '2026-09-07', 'days' => 5, 'notes' => 'Planowany wypoczynek',
    ])->assertRedirect(route('hr.index', ['tab' => 'leaves']));
    $leave = HrLeave::firstOrFail();
    expect($leave->user_id)->toBe($employee->id)
        ->and($leave->document_date->toDateString())->toBe('2026-08-20')
        ->and($leave->end_date->toDateString())->toBe('2026-09-11');

    $this->actingAs($employee)->get(route('hr.index', ['tab' => 'leaves']))
        ->assertOk()->assertSee('Urlopy / L4')->assertSee('Urlop wypoczynkowy')->assertSee('Planowany wypoczynek')
        ->assertSee('Uwzględnij weekendy w liczbie dni')->assertSee('Pobierz PDF')
        ->assertSee('Pula przyznana')->assertSee('Pozostało')->assertSee('10 dni');

    $this->actingAs($employee)->get(route('hr.leaves.pdf', $leave))
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs($manager)->put(route('hr.leaves.update', $leave), [
        'type' => 'sick_leave', 'document_date' => '2026-09-01', 'start_date' => '2026-09-08', 'days' => 3, 'notes' => 'Zwolnienie lekarskie',
    ])->assertSessionHas('success');
    expect($leave->fresh()->type)->toBe('sick_leave')->and($leave->fresh()->days)->toBe(3);

    $this->actingAs($manager)->post(route('hr.leaves.store'), [
        'user_id' => $employee->id, 'type' => 'caregiver', 'document_date' => '2026-09-15', 'start_date' => '2026-10-02', 'days' => 3,
        'include_weekends' => 1,
    ])->assertSessionHas('success');
    expect(HrLeave::where('user_id', $employee->id)->count())->toBe(2)
        ->and(HrLeave::latest('id')->firstOrFail()->end_date->toDateString())->toBe('2026-10-04')
        ->and(HrLeave::latest('id')->firstOrFail()->include_weekends)->toBeTrue();
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

test('leave-only role sees the leave tab without delegation access', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('leave_only');
    $role->givePermissionTo(Permission::findOrCreate('hr.leaves.view'));
    $user->assignRole($role);

    $this->actingAs($user)->get(route('hr.index', ['tab' => 'leaves']))
        ->assertOk()
        ->assertSee('Urlopy / L4')
        ->assertSee('Dodaj urlop / L4')
        ->assertDontSee('Dodaj delegację');
});

test('access to other employees cars requires a separate role permission', function () {
    $owner = User::factory()->create(['name' => 'Właściciel auta']);
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('employee_hr');
    $role->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $viewer->assignRole($role);
    HrVehicle::create(['user_id' => $owner->id, 'type' => 'private', 'name' => 'Prywatne Audi', 'registration_number' => 'SCI 77777']);

    $this->actingAs($viewer)->get(route('hr.index', ['tab' => 'vehicles']))
        ->assertOk()->assertDontSee('Prywatne Audi');

    $role->givePermissionTo(Permission::findOrCreate('hr.vehicles.all.view'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($viewer)->get(route('hr.index', ['tab' => 'vehicles']))
        ->assertOk()->assertSee('Prywatne Audi')->assertSee('Właściciel auta');
});
