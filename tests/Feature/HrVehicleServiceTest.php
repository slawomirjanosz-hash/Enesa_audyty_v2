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

test('assigned company car user can maintain service book without editing vehicle data', function () {
    $reader = serviceBookUser();
    $vehicle = HrVehicle::create(['type' => 'company', 'name' => 'Auto', 'registration_number' => 'SRV124']);
    $vehicle->forceFill(['responsible_user_id' => $reader->id])->save();
    $this->actingAs($reader)->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertSee('Dodaj wykonany serwis');
    $data = ['type' => 'engine_oil', 'performed_on' => today()->format('Y-m-d'), 'mileage' => 100];
    $this->post(route('hr.vehicles.service.store', $vehicle), $data)->assertRedirect(route('hr.vehicles.service.index', $vehicle))->assertSessionHasNoErrors();
    expect($vehicle->serviceEntries()->firstOrFail()->created_by)->toBe($reader->id);
    $this->get(route('hr.vehicles.edit', $vehicle))->assertForbidden();
    $this->put(route('hr.vehicles.update', $vehicle), ['name' => 'Zmiana'])->assertForbidden();
    $this->delete(route('hr.vehicles.destroy', $vehicle))->assertForbidden();
    $this->get(route('hr.index', ['tab' => 'vehicles']))->assertOk()->assertSee(route('hr.vehicles.service.index', $vehicle), false);
    $replacement = serviceBookUser();
    $vehicle->forceFill(['responsible_user_id' => $replacement->id])->save();
    $this->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertDontSee('Dodaj wykonany serwis');
    $this->post(route('hr.vehicles.service.store', $vehicle), $data)->assertForbidden();
    $this->actingAs($replacement)->post(route('hr.vehicles.service.store', $vehicle), $data)->assertSessionHasNoErrors();
});

test('HR team permission permits company service entries but notification permission does not', function () {
    $user = serviceBookUser();
    $vehicle = HrVehicle::create(['type' => 'company', 'name' => 'Auto zespołu', 'registration_number' => 'SRV127']);
    $data = ['type' => 'timing', 'performed_on' => today()->format('Y-m-d'), 'mileage' => 200000];
    $user->givePermissionTo(Permission::findOrCreate('hr.vehicles.responsible'));
    $this->actingAs($user)->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertDontSee('Dodaj wykonany serwis');
    $this->post(route('hr.vehicles.service.store', $vehicle), $data)->assertForbidden();
    $user->givePermissionTo(Permission::findOrCreate('hr.team.view'));
    $this->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertSee('Dodaj wykonany serwis');
    $this->post(route('hr.vehicles.service.store', $vehicle), $data)->assertSessionHasNoErrors();
});

test('private history is visible and writable only to its creator and inactive cars reject writes', function () {
    $owner = serviceBookUser();
    $other = serviceBookUser('admin');
    $vehicle = HrVehicle::create(['type' => 'private', 'name' => 'Prywatne', 'registration_number' => 'SRV125', 'created_by' => $owner->id, 'user_id' => $owner->id]);
    $data = ['type' => 'engine_oil', 'performed_on' => today()->format('Y-m-d'), 'mileage' => 500];
    $this->actingAs($other)->get(route('hr.vehicles.service.index', $vehicle))->assertNotFound();
    $this->post(route('hr.vehicles.service.store', $vehicle), $data)->assertForbidden();
    $vehicle->forceFill(['responsible_user_id' => $other->id])->save();
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

test('administrators and authorized managers can delete service entries with audit trail', function (string $access) {
    $user = serviceBookUser(in_array($access, ['admin', 'superadmin']) ? $access : 'service_manager');
    if (! in_array($access, ['admin', 'superadmin'])) {
        $user->givePermissionTo(Permission::findOrCreate($access));
    }
    $vehicle = HrVehicle::create(['type' => 'company', 'name' => 'Auto', 'registration_number' => 'DEL001']);
    $entry = $vehicle->serviceEntries()->create(['type' => 'engine_oil', 'performed_on' => today()->format('Y-m-d'), 'mileage' => 15000]);
    $html = $this->actingAs($user)->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertSee('Usuń wpis serwisowy')->getContent();
    expect($html)->toContain('data-sortable="false">Akcje', 'data-sort-value="15000"');
    $this->delete(route('hr.vehicles.service.destroy', [$vehicle, $entry]))->assertRedirect(route('hr.vehicles.service.index', $vehicle));
    $this->assertDatabaseMissing('hr_vehicle_service_entries', ['id' => $entry->id]);
    $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'deleted', 'auditable_type' => HrVehicleServiceEntry::class, 'auditable_id' => $entry->id]);
    $this->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertSee('colspan="6"', false);
})->with(['admin', 'superadmin', 'hr.team.view', 'system.full_access']);

test('assigned users private owners and notification recipients cannot delete history', function () {
    $owner = serviceBookUser();
    $owner->givePermissionTo(Permission::findOrCreate('hr.vehicles.responsible'));
    $vehicle = HrVehicle::create(['type' => 'company', 'name' => 'Auto', 'registration_number' => 'DEL002']);
    $vehicle->forceFill(['responsible_user_id' => $owner->id])->save();
    $entry = $vehicle->serviceEntries()->create(['type' => 'timing', 'performed_on' => today()->format('Y-m-d'), 'mileage' => 150000]);
    $this->actingAs($owner)->get(route('hr.vehicles.service.index', $vehicle))->assertOk()->assertSee('Dodaj wykonany serwis')->assertDontSee('Usuń wpis serwisowy');
    $this->delete(route('hr.vehicles.service.destroy', [$vehicle, $entry]))->assertForbidden();
    $vehicle->update(['type' => 'private', 'created_by' => $owner->id, 'user_id' => $owner->id]);
    $this->delete(route('hr.vehicles.service.destroy', [$vehicle, $entry]))->assertForbidden();
    $this->assertDatabaseHas('hr_vehicle_service_entries', ['id' => $entry->id]);
});

test('deleting service history enforces vehicle relation private scope and active vehicle', function () {
    $admin = serviceBookUser('admin');
    $other = serviceBookUser();
    $vehicle = HrVehicle::create(['type' => 'company', 'name' => 'Auto', 'registration_number' => 'DEL003']);
    $second = HrVehicle::create(['type' => 'company', 'name' => 'Drugie auto', 'registration_number' => 'DEL004']);
    $entry = $vehicle->serviceEntries()->create(['type' => 'timing', 'performed_on' => today()->format('Y-m-d'), 'mileage' => 150000]);
    $this->actingAs($admin)->delete(route('hr.vehicles.service.destroy', [$second, $entry]))->assertNotFound();
    $vehicle->update(['type' => 'private', 'created_by' => $other->id, 'user_id' => $other->id]);
    $this->delete(route('hr.vehicles.service.destroy', [$vehicle, $entry]))->assertNotFound();
    $vehicle->update(['type' => 'company', 'is_active' => false]);
    $this->delete(route('hr.vehicles.service.destroy', [$vehicle, $entry]))->assertNotFound();
    $this->assertDatabaseHas('hr_vehicle_service_entries', ['id' => $entry->id]);
    $vehicle->update(['is_active' => true]);
    $this->delete(route('hr.vehicles.service.destroy', [$vehicle, $entry]))->assertRedirect();
    $this->delete(route('hr.vehicles.service.destroy', [$vehicle, $entry]))->assertNotFound();
});
