<?php

use App\Models\HrVehicle;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function vehicleRegisterUser(bool $admin = false): User
{
    $user = User::factory()->create();
    $role = Role::findOrCreate($admin ? 'superadmin' : 'vehicle_employee');
    $role->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $user->assignRole($role);

    return $user;
}

test('private vehicle ownership cannot be forged and even admin cannot read edit or delete another private vehicle', function () {
    $owner = vehicleRegisterUser();
    $admin = vehicleRegisterUser(true);
    $this->actingAs($owner)->post(route('hr.vehicles.store'), ['type' => 'private', 'name' => 'Prywatny sekret', 'registration_number' => 'ABC123', 'user_id' => $admin->id, 'created_by' => $admin->id, 'fuel_card_number' => '001234567890123456789'])->assertSessionHas('success');
    $vehicle = HrVehicle::firstOrFail();
    expect($vehicle->user_id)->toBe($owner->id)->and($vehicle->created_by)->toBe($owner->id)->and($vehicle->fuel_card_number)->toBe('001234567890123456789');
    foreach ([vehicleRegisterUser(), $admin] as $other) {
        $this->actingAs($other)->get(route('hr.index', ['tab' => 'vehicles']))->assertOk()->assertDontSee('Prywatny sekret')->assertDontSee('001234567890123456789');
        $this->get(route('hr.vehicles.edit', $vehicle))->assertForbidden();
        $this->put(route('hr.vehicles.update', $vehicle), ['name' => 'Hacked'])->assertForbidden();
        $this->delete(route('hr.vehicles.destroy', $vehicle))->assertForbidden();
        $this->post(route('hr.delegations.store'), ['vehicle_id' => $vehicle->id, 'purpose' => 'Test', 'origin' => 'A', 'destination' => 'B', 'departure_at' => '2026-10-12 08:00', 'outbound_arrival_at' => '2026-10-12 09:00', 'return_departure_at' => '2026-10-12 16:00', 'return_at' => '2026-10-12 17:00', 'outbound_travel_hours' => 1, 'return_travel_hours' => 1])->assertForbidden();
    }
    $this->actingAs($owner)->get(route('hr.vehicles.edit', $vehicle))->assertOk()->assertSee('001234567890123456789');
    $this->put(route('hr.vehicles.update', $vehicle), ['name' => 'Poprawione', 'registration_number' => 'ABC123', 'type' => 'company', 'user_id' => $admin->id, 'insurance_expires_at' => '2026-11-01', 'inspection_due_at' => '2026-11-02', 'cit_percent' => 75, 'vat_percent' => 50, 'gps' => 0, 'vat_26' => 1])->assertSessionHas('success');
    expect($vehicle->fresh()->type)->toBe('private')->and($vehicle->fresh()->insurance_expires_at->format('Y-m-d'))->toBe('2026-11-01')->and($vehicle->fresh()->gps)->toBeFalse();
    $this->put(route('hr.vehicles.update', $vehicle), ['name' => 'Test', 'registration_number' => 'ABC123', 'insurance_expires_at' => '2026-02-30', 'cit_percent' => 101])->assertSessionHasErrorsIn('vehicle', ['insurance_expires_at', 'cit_percent']);
});

test('vehicle deadline colours include exact boundaries and overdue overrides the row', function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
    foreach ([-1 => 'overdue', 0 => 'urgent', 7 => 'urgent', 8 => 'soon', 14 => 'soon', 15 => ''] as $days => $state) {
        $vehicle = new HrVehicle(['insurance_expires_at' => today()->addDays($days), 'inspection_due_at' => today()->addDays($days)]);
        expect($vehicle->deadlineState('insurance_expires_at'))->toBe($state)->and($vehicle->deadlineState('inspection_due_at'))->toBe($state);
    }
    expect((new HrVehicle)->deadlineState('inspection_due_at'))->toBe('');
    $owner = vehicleRegisterUser();
    HrVehicle::create(['type' => 'private', 'user_id' => $owner->id, 'name' => 'Termin', 'registration_number' => 'TEST', 'insurance_expires_at' => today()->subDay(), 'inspection_due_at' => today()->addDays(14)]);
    $response = $this->actingAs($owner)->get(route('hr.index', ['tab' => 'vehicles']))->assertOk()->assertSee('class="vehicle-overdue"', false)->assertSee('class="deadline-soon"', false)->assertSee('data-sort-value="2026-10-11"', false);
    if (getenv('VEHICLE_QA')) {
        file_put_contents(base_path('tmp/vehicle-register.html'), $response->getContent());
    }
});

test('vehicle column preferences are per user allowlisted and keep vehicle identity visible', function () {
    $owner = vehicleRegisterUser();
    $other = vehicleRegisterUser();
    $this->actingAs($owner)->put(route('hr.vehicles.columns'), ['columns' => ['insurance_expires_at', 'gps'], 'user_id' => $other->id])->assertSessionHas('success');
    expect($owner->fresh()->hr_vehicle_columns)->toBe(['name', 'registration_number', 'insurance_expires_at', 'gps'])->and($other->fresh()->hr_vehicle_columns)->toBeNull();
    $this->get(route('hr.index', ['tab' => 'vehicles']))->assertOk()->assertSee('--columns:6', false);
    $this->put(route('hr.vehicles.columns'), ['columns' => ['password']])->assertSessionHasErrors('columns.0');
    $this->post(route('hr.vehicles.store'), ['type' => 'company', 'name' => 'Forbidden', 'registration_number' => 'ABC'])->assertForbidden();
});
