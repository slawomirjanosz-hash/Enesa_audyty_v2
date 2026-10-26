<?php

use App\Models\HrVehicle;
use App\Models\HrVehicleServiceEntry;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function serviceBookUser(string $role = 'service_reader'): User
{
    $user = User::factory()->create();
    $role = Role::findOrCreate($role);
    $role->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $user->assignRole($role);

    return $user;
}

test('vehicle editors record each service type with actual author and history stays separate', function () {
    $admin = serviceBookUser('admin');
    $vehicle = HrVehicle::create(['type' => 'company', 'name' => 'Serwisowane auto', 'registration_number' => 'SRV123']);
    foreach (array_keys(HrVehicleServiceEntry::TYPES) as $type) {
        $this->actingAs($admin)->post(route('hr.vehicles.service.store', $vehicle), [
            'type' => $type, 'performed_on' => today()->subDay()->format('Y-m-d'),
            'mileage' => 120500, 'description' => 'Warsztat — wymiana filtrów', 'created_by' => 999,
        ])->assertRedirect(route('hr.vehicles.service.index', $vehicle))->assertSessionHasNoErrors();
    }
    expect($vehicle->serviceEntries()->count())->toBe(4);
    expect($vehicle->serviceEntries()->where('created_by', $admin->id)->count())->toBe(4);
    $this->assertDatabaseCount('tasks', 0);
    $html = $this->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertSee('Wymiana rozrządu')->getContent();
    expect($html)->toContain('data-sort-value="120500"', 'data-sort-type="number"', 'table-sort.js');
    if (getenv('VEHICLE_SERVICE_QA')) {
        file_put_contents(base_path('tmp/vehicle-service-qa.html'), $html);
    }
    $this->get(route('hr.vehicles.edit', $vehicle))->assertOk()->assertSee('Książka serwisowa auta');
});

test('assigned company car reader may view history but cannot add entries', function () {
    $reader = serviceBookUser();
    $vehicle = HrVehicle::create(['type' => 'company', 'name' => 'Auto', 'registration_number' => 'SRV124']);
    $vehicle->forceFill(['responsible_user_id' => $reader->id])->save();
    $this->actingAs($reader)->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertDontSee('Dodaj wykonany serwis');
    $this->post(route('hr.vehicles.service.store', $vehicle), ['type' => 'engine_oil', 'performed_on' => today()->format('Y-m-d'), 'mileage' => 100])->assertForbidden();
    $this->get(route('hr.index', ['tab' => 'vehicles']))->assertOk()->assertSee(route('hr.vehicles.service.index', $vehicle), false);
});

test('private history is visible and writable only to its creator and inactive cars reject writes', function () {
    $owner = serviceBookUser();
    $other = serviceBookUser('admin');
    $vehicle = HrVehicle::create(['type' => 'private', 'name' => 'Prywatne', 'registration_number' => 'SRV125', 'created_by' => $owner->id, 'user_id' => $owner->id]);
    $data = ['type' => 'engine_oil', 'performed_on' => today()->format('Y-m-d'), 'mileage' => 500];
    $this->actingAs($other)->get(route('hr.vehicles.service.index', $vehicle))->assertNotFound();
    $this->post(route('hr.vehicles.service.store', $vehicle), $data)->assertForbidden();
    $this->actingAs($owner)->post(route('hr.vehicles.service.store', $vehicle), $data)->assertSessionHasNoErrors();
    $this->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertSee('Dodaj wykonany serwis');
    $vehicle->update(['is_active' => false]);
    $this->post(route('hr.vehicles.service.store', $vehicle), $data)->assertNotFound();
});

test('service entry validates mileage date and description and preserves historical mileage', function () {
    $admin = serviceBookUser('admin');
    $vehicle = HrVehicle::create(['type' => 'company', 'name' => 'Auto', 'registration_number' => 'SRV126']);
    $data = ['type' => 'other', 'performed_on' => today()->addDay()->format('Y-m-d'), 'mileage' => -1];
    $this->actingAs($admin)->post(route('hr.vehicles.service.store', $vehicle), $data)->assertSessionHasErrors(['description', 'mileage', 'performed_on']);
    $data = ['type' => 'unknown', 'performed_on' => today()->format('Y-m-d'), 'mileage' => 1.5];
    $this->post(route('hr.vehicles.service.store', $vehicle), $data)->assertSessionHasErrors(['type', 'mileage']);
    foreach ([150000, 100000] as $mileage) {
        $this->post(route('hr.vehicles.service.store', $vehicle), ['type' => 'timing', 'performed_on' => today()->subYear()->format('Y-m-d'), 'mileage' => $mileage])->assertSessionHasNoErrors();
    }
    expect($vehicle->serviceEntries()->count())->toBe(2);
});
