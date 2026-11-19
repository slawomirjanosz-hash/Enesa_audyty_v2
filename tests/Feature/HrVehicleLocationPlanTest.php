<?php

use App\Models\CompanySettings;
use App\Models\HrVehicle;
use App\Models\User;
use App\Services\HrVehicleLocationPlan;
use App\Services\HrVehicleReminderSelection;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function locationPlanPerson(bool $manager = true): User
{
    $role = Role::findOrCreate($manager ? 'admin' : 'location_employee');
    $role->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $u = User::factory()->create();
    $u->assignRole($role);

    return $u;
}

beforeEach(function () {
    CompanySettings::create(['name' => 'Plan', 'enabled_modules' => ['hr']]);
    $this->travelTo(Carbon::parse('2026-11-02 08:00', 'Europe/Warsaw'));
});

test('intervals switch on inclusive dates and history is preserved when location changes', function () {
    $user = locationPlanPerson();
    $car = HrVehicle::create(['name' => 'Planowane auto', 'registration_number' => 'PLAN-1', 'type' => 'company', 'usage_location' => 'Dotychczasowa']);
    $url = route('hr.vehicles.location-plan.save', $car);
    $this->actingAs($user)->get(route('hr.vehicles.location-plan', $car))->assertOk()->assertSee('Historia zakończonych przypisań');
    $this->put($url, ['revision' => 0, 'periods' => [
        ['location' => 'A', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-20'],
    ]])->assertSessionHasNoErrors();
    expect($car->fresh()->usage_location)->toBe('A');
    $this->travelTo(Carbon::parse('2026-11-10 08:00', 'Europe/Warsaw'));
    $this->put($url, ['revision' => 1, 'periods' => [['location' => 'B', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-20']]])->assertSessionHasNoErrors();
    expect($car->fresh()->usage_location)->toBe('B');
    expect($car->fresh()->locationPeriods->firstWhere('location', 'A')->ends_on->toDateString())->toBe('2026-11-09');
    $this->travelTo(Carbon::parse('2026-11-20 23:59', 'Europe/Warsaw'));
    expect($car->fresh()->usage_location)->toBe('B');
    $this->travelTo(Carbon::parse('2026-11-21 00:01', 'Europe/Warsaw'));
    expect($car->fresh()->usage_location)->toBeNull();
    $this->get(route('hr.vehicles.location-plan', $car))->assertOk()->assertSee('09.11.2026')->assertSee('20.11.2026');
    $this->put($url, ['revision' => 1])->assertStatus(409);
});

test('overlaps are rejected and scopes protect plans and private cars', function () {
    $admin = locationPlanPerson();
    $staff = locationPlanPerson(false);
    $car = HrVehicle::create(['name' => 'Auto', 'registration_number' => 'PLAN-2', 'type' => 'company']);
    $private = HrVehicle::create(['name' => 'Prywatne', 'registration_number' => 'PLAN-3', 'type' => 'private', 'created_by' => $admin->id]);
    $this->actingAs($staff)->get(route('hr.vehicles.location-plan', $private))->assertForbidden();
    $this->put(route('hr.vehicles.location-plan.save', $car), ['revision' => 0])->assertForbidden();
    $staff->givePermissionTo(Permission::findOrCreate('hr.vehicles.responsible'));
    $this->put(route('hr.vehicles.location-plan.save', $car), ['revision' => 0, 'periods' => [
        ['location' => 'A', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-15'],
        ['location' => 'B', 'starts_on' => '2026-11-15', 'ends_on' => '2026-11-30'],
    ]])->assertSessionHasErrors('periods');
    $this->put(route('hr.vehicles.location-plan.save', $car), ['revision' => 0, 'periods' => [
        ['location' => 'A', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-15'],
        ['location' => 'B', 'starts_on' => '2026-11-16', 'ends_on' => '2026-11-30'],
    ]])->assertSessionHasNoErrors();
    $this->travelTo(Carbon::parse('2026-11-16 08:00', 'Europe/Warsaw'));
    expect($car->fresh()->usage_location)->toBe('B');
    $this->get(route('hr.vehicles.edit', $car))->assertOk();
});

test('expired locations notify managers while permanent assignments never expire', function () {
    $manager = locationPlanPerson(false);
    $manager->givePermissionTo(Permission::findOrCreate('hr.vehicles.responsible'));
    $car = HrVehicle::create(['name' => 'Auto', 'registration_number' => 'PLAN-4', 'type' => 'company']);
    app(HrVehicleLocationPlan::class)->save($car, [['location' => 'A', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-02']], 0);
    $this->travelTo(Carbon::parse('2026-11-03 08:00', 'Europe/Warsaw'));
    $items = app(HrVehicleReminderSelection::class)->groups()->get($manager->id);
    expect($items->pluck('message')->implode(' '))->toContain('Lokalizacja nieokreślona');
    app(HrVehicleLocationPlan::class)->save($car, [['location' => 'B', 'starts_on' => '2026-11-03', 'ends_on' => null]], 1);
    $this->travelTo(Carbon::parse('2027-11-03 08:00', 'Europe/Warsaw'));
    expect($car->fresh()->usage_location)->toBe('B');
    expect(app(HrVehicleReminderSelection::class)->groups()->get($manager->id, collect())->pluck('message')->implode(' '))->not->toContain('Lokalizacja nieokreślona');
});

test('a planned future move preserves the current location until the move and deleting plans never deletes history', function () {
    $car = HrVehicle::create(['name' => 'Auto', 'registration_number' => 'PLAN-FUTURE', 'type' => 'company']);
    $plans = app(HrVehicleLocationPlan::class);
    $plans->save($car, [['location' => 'A', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-20']], 0);
    $id = $car->fresh()->locationPeriods->first()->id;
    $plans->save($car, [['id' => $id, 'location' => 'B', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-20']], 1);
    expect($car->fresh()->usage_location)->toBe('A');
    $this->travelTo(Carbon::parse('2026-11-10 08:00', 'Europe/Warsaw'));
    expect($car->fresh()->usage_location)->toBe('B');
    $plans->save($car, [], 2);
    expect($car->fresh()->usage_location)->toBeNull();
    expect($car->fresh()->locationPeriods->firstWhere('location', 'A')->ends_on->toDateString())->toBe('2026-11-09');
});

test('location dialog loads a fragment and saves without leaving the vehicle table', function () {
    $user = locationPlanPerson();
    $car = HrVehicle::create(['name' => 'Dialog', 'registration_number' => 'MODAL', 'type' => 'company']);
    $this->actingAs($user)->withHeader('X-Vehicle-Location-Modal', '1')
        ->get(route('hr.vehicles.location-plan', $car))->assertOk()->assertSee('data-location-plan-content', false)
        ->assertSee('data-close-location-modal', false)->assertDontSee('<html', false)
        ->assertSee('data-location-select', false)->assertSee('data-custom-location', false)
        ->assertDontSee('<datalist', false);
    $this->putJson(route('hr.vehicles.location-plan.save', $car), ['revision' => 0, 'periods' => [
        ['location' => 'Baza', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30'],
    ]])->assertOk()->assertJsonPath('location', 'Baza');
    $this->putJson(route('hr.vehicles.location-plan.save', $car), ['revision' => 1, 'periods' => [
        ['location' => 'Baza', 'starts_on' => '2026-11-30', 'ends_on' => '2026-11-01'],
    ]])->assertUnprocessable()->assertJsonValidationErrors('periods');
});
