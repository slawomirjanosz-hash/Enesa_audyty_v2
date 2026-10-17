<?php

use App\Mail\HrVehicleReminder;
use App\Models\CompanySettings;
use App\Models\HrVehicle;
use App\Models\HrVehicleLocation;
use App\Models\User;
use App\Services\HrVehicleReminderSelection;
use App\Services\UserPermissions;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function fleetUser(string $role = 'fleet_employee'): User
{
    $user = User::factory()->create();
    $role = Role::findOrCreate($role);
    $role->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $user->assignRole($role);

    return $user;
}

function fleetResponsibility(User $user): void
{
    $role = Role::findOrCreate('fleet_responsible');
    $role->givePermissionTo(Permission::findOrCreate('hr.vehicles.responsible'));
    $user->assignRole($role);
}

function fleetVehicle(array $attributes = []): HrVehicle
{
    return HrVehicle::create($attributes + ['type' => 'company', 'name' => 'Auto testowe', 'registration_number' => fake()->unique()->bothify('TEST###')]);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-19 08:00', 'Europe/Warsaw'));
    config(['mail.default' => 'smtp', 'mail.from.address' => 'fleet@test.invalid']);
    Mail::fake();
});

test('readers cannot manage location dictionary and renaming preserves assignments', function () {
    $admin = fleetUser('admin');
    $reader = fleetUser();
    $this->actingAs($reader)->post(route('hr.vehicle-locations.store'), ['name' => 'Biuro'])->assertForbidden();
    $this->actingAs($admin)->post(route('hr.vehicle-locations.store'), ['name' => 'Biuro'])->assertSessionHas('success');
    $location = HrVehicleLocation::firstOrFail();
    $vehicle = fleetVehicle(['usage_location' => 'Biuro']);
    $this->actingAs($reader)->patch(route('hr.vehicles.location', $vehicle), ['usage_location' => 'Biuro'])->assertForbidden();
    $this->actingAs($reader)->put(route('hr.vehicle-locations.update', $location), ['name' => 'Hala'])->assertForbidden();
    $this->delete(route('hr.vehicle-locations.destroy', $location))->assertForbidden();
    $this->actingAs($admin)->put(route('hr.vehicle-locations.update', $location), ['name' => 'Hala'])->assertSessionHas('success');
    expect($vehicle->fresh()->usage_location)->toBe('Hala');
    $this->patch(route('hr.vehicles.location', $vehicle), ['usage_location' => 'Nieznana'])->assertSessionHasErrors('usage_location');
    $this->patch(route('hr.vehicles.location', $vehicle), ['usage_location' => 'Hala'])->assertSessionHas('success');
    $this->delete(route('hr.vehicle-locations.destroy', $location))->assertSessionHasErrors('name');
    $vehicle->update(['usage_location' => null]);
    $this->delete(route('hr.vehicle-locations.destroy', $location))->assertSessionHas('success');
    $this->assertDatabaseCount('hr_vehicle_locations', 0);
});

test('operational and HR team managers see settings and can manage settings and locations', function (string $permission) {
    $manager = fleetUser();
    $manager->givePermissionTo(Permission::findOrCreate($permission));
    $this->actingAs($manager)->get(route('hr.index', ['tab' => 'vehicles']))
        ->assertOk()->assertSee('Ustawienia HR');
    $this->get(route('hr.settings', ['section' => 'vehicles']))->assertOk()->assertSee('Odpowiedzialność za samochody')->assertSee('Lokalizacje samochodów');
    $this->put(route('hr.settings.update'), ['hr_km_rate' => 1.15, 'hr_diet_rate' => 45, 'hr_vehicle_manager_ids' => [$manager->id]])->assertSessionHas('success');
    $this->post(route('hr.vehicle-locations.store'), ['name' => 'Biuro'])->assertSessionHas('success');
    $location = HrVehicleLocation::firstOrFail();
    $this->put(route('hr.vehicle-locations.update', $location), ['name' => 'Hala'])->assertSessionHas('success');
    $this->delete(route('hr.vehicle-locations.destroy', $location))->assertSessionHas('success');
})->with(['system.full_access', 'hr.team.view']);

test('ordinary HR reader does not see settings button or settings dialog', function () {
    $reader = fleetUser();
    $this->actingAs($reader)->get(route('hr.index', ['tab' => 'vehicles', 'settings' => 1]))
        ->assertOk()->assertDontSee('Ustawienia HR');
    $this->get(route('hr.settings'))->assertForbidden();
});

test('delegation settings do not change notification recipients', function () {
    $admin = fleetUser('admin');
    $person = fleetUser();
    $data = ['hr_km_rate' => 1.15, 'hr_diet_rate' => 45, 'hr_vehicle_manager_ids' => [$person->id]];
    $this->actingAs($person)->put(route('hr.settings.update'), $data)->assertForbidden();
    $this->actingAs($admin)->put(route('hr.settings.update'), $data)->assertRedirect(route('hr.settings', ['section' => 'delegations']));
    expect(CompanySettings::first()->hr_vehicle_manager_ids)->toBeNull();
    expect(app(HrVehicleReminderSelection::class)->groups())->toBeEmpty();
});

test('vehicle accepts dictionary locations and either linked or manual responsible person', function () {
    $admin = fleetUser('admin');
    $person = fleetUser();
    HrVehicleLocation::create(['name' => 'Cieszyn']);
    $data = ['type' => 'company', 'name' => 'Auto', 'registration_number' => 'ABC123', 'usage_location' => 'Cieszyn', 'responsible_user_id' => $person->id, 'responsible_person' => 'Ignored'];
    $this->actingAs($admin)->post(route('hr.vehicles.store'), $data)->assertSessionHas('success');
    $vehicle = HrVehicle::firstOrFail();
    expect($vehicle->responsible_user_id)->toBe($person->id)->and($vehicle->responsible_person)->toBe($person->name);
    $this->put(route('hr.vehicles.update', $vehicle), array_replace($data, ['usage_location' => 'Invented']))->assertSessionHasErrorsIn('vehicle', 'usage_location');
    $this->put(route('hr.vehicles.update', $vehicle), array_replace($data, ['responsible_user_id' => null, 'responsible_person' => 'Osoba zewnętrzna']))->assertSessionHas('success');
    expect($vehicle->fresh()->responsible_user_id)->toBeNull()->and($vehicle->fresh()->responsible_person)->toBe('Osoba zewnętrzna');
    $this->post(route('hr.vehicles.store'), array_replace($data, ['type' => 'private', 'registration_number' => 'PRIV123']))->assertForbidden();
    $html = $this->get(route('hr.index', ['tab' => 'vehicles']))->assertOk()->getContent();
    expect($html)->toContain('data-sort-value="Cieszyn"', 'id="vehicle-usage_location"');
});

test('location import is restricted to PRINZ and does not publish private locations', function () {
    $company = CompanySettings::create(['name' => 'ENESA']);
    fleetVehicle(['usage_location' => ' Cieszyn ']);
    fleetVehicle(['type' => 'private', 'usage_location' => 'Sekret']);
    $this->artisan('hr:import-prinz-vehicle-locations')->assertFailed();
    $this->assertDatabaseCount('hr_vehicle_locations', 0);
    $company->update(['name' => 'PRINŻ Cieszyn']);
    $this->artisan('hr:import-prinz-vehicle-locations --dry-run')->assertSuccessful();
    $this->assertDatabaseCount('hr_vehicle_locations', 0);
    $this->artisan('hr:import-prinz-vehicle-locations')->assertSuccessful();
    $this->artisan('hr:import-prinz-vehicle-locations')->assertSuccessful();
    expect(HrVehicleLocation::pluck('name')->all())->toBe(['Cieszyn']);
});

test('deployment migration imports existing locations only for PRINZ', function () {
    $migration = require database_path('migrations/2026_10_15_160000_add_hr_fleet_settings.php');
    $migration->down();
    CompanySettings::create(['name' => 'PRINŻ CIESZYN SP. Z O. O.']);
    fleetVehicle(['usage_location' => 'Biuro Cieszyn']);
    fleetVehicle(['type' => 'private', 'usage_location' => 'Dom']);
    $migration->up();
    expect(HrVehicleLocation::pluck('name')->all())->toBe(['Biuro Cieszyn']);
});

test('reminders go to managers and linked owners without leaking private cars or notifying manual owners', function () {
    $manager = fleetUser('admin');
    $owner = fleetUser();
    $outsider = fleetUser();
    CompanySettings::create(['name' => 'Fleet']);
    fleetResponsibility($manager);
    fleetResponsibility($owner);
    $companyCar = fleetVehicle(['inspection_due_at' => today()->subDay()]);
    $companyCar->forceFill(['responsible_user_id' => $owner->id])->save();
    $private = fleetVehicle(['type' => 'private', 'created_by' => $outsider->id, 'user_id' => $outsider->id, 'insurance_expires_at' => today()->subDay(), 'responsible_person' => 'Sekret']);
    $private->forceFill(['responsible_user_id' => $outsider->id])->save();
    $manual = fleetVehicle(['responsible_person' => 'Osoba zewnętrzna', 'insurance_expires_at' => today()->subDay()]);
    $groups = app(HrVehicleReminderSelection::class)->groups();
    expect($groups->get($manager->id)->pluck('vehicle.id')->unique()->values()->all())->toBe([$companyCar->id, $manual->id]);
    expect($groups->get($owner->id)->where('vehicle.id', $companyCar->id)->count())->toBe(2);
    expect($groups->get($outsider->id)->pluck('vehicle.id')->unique()->values()->all())->toBe([$private->id]);
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(HrVehicleReminder::class, 3);
    Mail::assertSent(HrVehicleReminder::class, fn ($mail) => $mail->hasTo($manager->email) && ! str_contains($mail->render(), 'Sekret'));
});

test('approaching deadlines send once per stage overdue daily and missing fields only Mondays', function () {
    $manager = fleetUser('admin');
    CompanySettings::create(['name' => 'Fleet']);
    fleetResponsibility($manager);
    $this->travelTo(Carbon::parse('2026-10-20 08:00', 'Europe/Warsaw'));
    $car = fleetVehicle(['insurance_expires_at' => '2026-11-03']);
    expect(app(HrVehicleReminderSelection::class)->groups()->count())->toBe(1);
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    $this->travelTo(Carbon::parse('2026-10-21 08:00', 'Europe/Warsaw'));
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(HrVehicleReminder::class, 1);
    $this->travelTo(Carbon::parse('2026-10-27 08:00', 'Europe/Warsaw'));
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(HrVehicleReminder::class, 2);
    $this->travelTo(Carbon::parse('2026-11-04 08:00', 'Europe/Warsaw'));
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    $this->travelTo(Carbon::parse('2026-11-05 08:00', 'Europe/Warsaw'));
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(HrVehicleReminder::class, 4);
    $car->update(['insurance_expires_at' => '2027-11-05']);
    $this->travelTo(Carbon::parse('2026-11-06 08:00', 'Europe/Warsaw'));
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(HrVehicleReminder::class, 4);
    $this->travelTo(Carbon::parse('2026-11-09 08:00', 'Europe/Warsaw'));
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(HrVehicleReminder::class, 5);
    Mail::assertSent(HrVehicleReminder::class, fn ($mail) => str_contains($mail->render(), 'Brakujące pola:'));
    $car->update(['is_active' => false]);
    $this->travelTo(Carbon::parse('2026-11-16 08:00', 'Europe/Warsaw'));
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(HrVehicleReminder::class, 5);
});

test('false and zero are complete values and missing service book does not require its date', function () {
    $car = new HrVehicle(['vat_percent' => 50, 'cit_percent' => 0, 'vat_26' => false, 'gps' => false, 'registration_document_present' => false, 'service_book_status' => 'missing']);
    expect(array_keys($car->missingFields()))->not->toContain('vat_percent', 'cit_percent', 'vat_26', 'gps', 'registration_document_present', 'service_book_entry_at');
});

test('settings are separate pages with four sections and no recipient picker', function () {
    $admin = fleetUser('admin');
    $this->actingAs($admin)->get(route('hr.index', ['tab' => 'vehicles', 'settings' => 1]))->assertRedirect(route('hr.settings', ['section' => 'vehicles']));
    foreach (['delegations', 'leaves', 'attendance', 'vehicles'] as $section) {
        $response = $this->actingAs($admin)->get(route('hr.settings', ['section' => $section]))->assertOk();
        $response->assertSee('Sekcje ustawień HR')->assertSee('Delegacje')->assertSee('Urlopy')->assertSee('Lista obecności')->assertSee('Samochody');
        $response->assertDontSee('name="hr_vehicle_manager_ids[]"', false)->assertDontSee('id="hr-settings-modal"', false);
        if ($section === 'vehicles') {
            $response->assertSee('Lokalizacje samochodów')->assertSee('Odpowiedzialność za samochody');
        } else {
            $response->assertDontSee('Nowa lokalizacja');
        }
    }
});

test('role responsibility controls recipients without implicit admin or full access subscriptions', function () {
    $admin = fleetUser('admin');
    $admin->givePermissionTo(Permission::findOrCreate('system.full_access'));
    $person = fleetUser();
    $client = fleetUser('client_user');
    fleetResponsibility($person);
    fleetResponsibility($client);
    $inactive = fleetUser();
    fleetResponsibility($inactive);
    $inactive->update(['is_active' => false]);
    CompanySettings::create(['name' => 'Fleet', 'hr_vehicle_manager_ids' => [$admin->id]]);
    fleetVehicle(['insurance_expires_at' => '2026-10-01']);
    expect(app(HrVehicleReminderSelection::class)->groups()->keys()->all())->toBe([$person->id]);
    $person->removeRole('fleet_responsible');
    expect(app(HrVehicleReminderSelection::class)->groups())->toBeEmpty();
});

test('migration preserves selected recipients without changing their shared roles', function () {
    $one = fleetUser();
    $other = fleetUser();
    CompanySettings::create(['name' => 'Fleet', 'hr_vehicle_manager_ids' => [$one->id]]);
    $migration = require database_path('migrations/2026_10_15_180000_add_hr_vehicle_responsibility_permission.php');
    $migration->up();
    $migration->up();
    expect($one->fresh()->hasRole('hr_vehicle_responsible'))->toBeTrue();
    expect($other->fresh()->hasRole('hr_vehicle_responsible'))->toBeFalse();
});

test('role responsibility is a template for new users without subscribing existing role members', function () {
    $admin = fleetUser('superadmin');
    CompanySettings::create(['name' => 'Fleet']);
    $role = Role::findOrCreate('car_caretakers');
    $person = fleetUser();
    $person->assignRole($role);
    $this->actingAs($admin)->get(route('settings.roles.index'))->assertOk()->assertSee('Odpowiedzialność za samochody');
    $this->put(route('settings.roles.update', $role), ['name' => $role->name, 'display_name' => 'Opiekunowie aut', 'permissions' => ['hr.delegations.view', 'hr.vehicles.responsible']])->assertSessionHas('success');
    fleetVehicle(['insurance_expires_at' => '2026-10-01']);
    expect(app(HrVehicleReminderSelection::class)->groups())->toBeEmpty();
    $newPerson = fleetUser();
    $newPerson->assignRole($role);
    app(UserPermissions::class)->initialize($newPerson);
    expect(app(HrVehicleReminderSelection::class)->groups()->keys()->all())->toBe([$newPerson->id]);
});

test('superadmin responsibility can be selected independently of protected access permissions', function () {
    $admin = fleetUser('superadmin');
    $role = $admin->roles->first();
    $this->actingAs($admin)->put(route('settings.roles.update', $role), ['name' => 'superadmin', 'display_name' => 'Super Admin', 'permissions' => ['hr.vehicles.responsible']])->assertSessionHas('success');
    expect($role->fresh()->hasPermissionTo('hr.vehicles.responsible'))->toBeTrue();
    $this->put(route('settings.roles.update', $role), ['name' => 'superadmin', 'display_name' => 'Super Admin', 'permissions' => []])->assertSessionHas('success');
    expect($role->fresh()->hasPermissionTo('hr.vehicles.responsible'))->toBeFalse();
    expect($role->fresh()->hasPermissionTo('settings.roles.manage'))->toBeTrue();
});

test('scheduled guard dry run module switch and failed deliveries do not send duplicates', function () {
    $manager = fleetUser('admin');
    $settings = CompanySettings::create(['name' => 'Fleet']);
    fleetResponsibility($manager);
    fleetVehicle(['insurance_expires_at' => '2026-10-01']);
    $this->artisan('hr:send-vehicle-reminders --dry-run')->assertSuccessful();
    $this->assertDatabaseCount('hr_vehicle_reminder_deliveries', 0);
    $this->travelTo(Carbon::parse('2026-10-19 07:59', 'Europe/Warsaw'));
    $this->artisan('hr:send-vehicle-reminders --scheduled')->assertSuccessful();
    Mail::assertNothingSent();
    $this->travelTo(Carbon::parse('2026-10-19 08:00', 'Europe/Warsaw'));
    $settings->update(['enabled_modules' => ['dashboard']]);
    $this->artisan('hr:send-vehicle-reminders')->assertSuccessful();
    Mail::assertNothingSent();
    $settings->update(['enabled_modules' => ['hr']]);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('provider failure'));
    $this->artisan('hr:send-vehicle-reminders')->assertFailed();
    $this->artisan('hr:send-vehicle-reminders')->assertSuccessful();
    expect(DB::table('hr_vehicle_reminder_deliveries')->where('status', 'failed')->count())->toBeGreaterThan(0);
});
