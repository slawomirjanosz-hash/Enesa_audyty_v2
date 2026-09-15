<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Cylinder;
use App\Models\User;
use Spatie\Permission\Models\Role;

function cylinderStaff(): User
{
    Role::findOrCreate('superadmin', 'web');
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    return $user;
}

function enableCylinders(): void
{
    CompanySettings::create(['name' => 'Firma przeglądów', 'enabled_modules' => ['dashboard', 'crm', 'client_zone', 'cylinders']]);
}

function registeredCylinder(string $serial = 'ABC-123'): Cylinder
{
    $company = Company::create(['name' => 'Właściciel '.$serial, 'company_type' => 'client', 'status' => 'active']);

    return Cylinder::create(['company_id' => $company->id, 'serial_number' => $serial, 'type' => 'Butla testowa']);
}

test('cylinders are opt in for missing null and existing module settings', function () {
    $user = cylinderStaff();
    expect(CompanySettings::moduleIsEnabled('cylinders'))->toBeFalse();
    $this->actingAs($user)->get(route('cylinders.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('cylinders.index'), false);
    $settings = CompanySettings::create(['name' => 'Starsze wdrożenie']);
    expect($settings->moduleEnabled('cylinders'))->toBeFalse()->and($settings->moduleEnabled('audits'))->toBeTrue();
    $this->get(route('cylinders.create'))->assertForbidden();
    $this->post(route('cylinders.store'), [])->assertForbidden();
    $this->get(route('settings.company'))->assertOk()->assertSee('Przeglądy butli');
    $settings->update(['enabled_modules' => ['dashboard', 'audits']]);
    expect($settings->moduleEnabled('cylinders'))->toBeFalse();
});

test('superadmin can explicitly enable cylinders in company settings and disable them again', function () {
    $this->actingAs(cylinderStaff())->post(route('settings.company.update'), [
        'name' => 'Firma przeglądów', 'primary_color' => '#123456', 'welcome_page_mode' => 'general', 'enabled_modules' => ['cylinders'],
    ])->assertRedirect();
    expect(CompanySettings::moduleIsEnabled('cylinders'))->toBeTrue();
    $this->get(route('cylinders.index'))->assertOk()->assertSee('Rejestr butli');
    CompanySettings::first()->update(['enabled_modules' => []]);
    $this->get(route('cylinders.index'))->assertForbidden();
});

test('staff registers edits archives a cylinder and keeps immutable inspection history', function () {
    enableCylinders();
    $staff = cylinderStaff();
    $company = Company::create(['name' => 'Zakład', 'company_type' => 'client', 'status' => 'active']);
    $this->actingAs($staff)->get(route('cylinders.create'))->assertOk();
    $this->post(route('cylinders.store'), ['company_id' => $company->id, 'serial_number' => 'SN-55', 'type' => 'Test'])->assertRedirect();
    $cylinder = Cylinder::firstOrFail();
    $this->get(route('cylinders.edit', $cylinder))->assertOk();
    $this->put(route('cylinders.update', $cylinder), ['serial_number' => 'SN-55', 'type' => 'Nowy opis'])->assertRedirect();
    $payload = ['inspected_at' => '2026-01-02', 'next_due_at' => '2027-01-02', 'result' => 'further_review', 'observations' => 'Oględziny — uwaga do sprawdzenia'];
    $this->post(route('cylinders.inspections.store', $cylinder), $payload + ['inspector_id' => 999, 'inspector_name' => 'Fałszywy'])->assertRedirect();
    expect($cylinder->inspections()->first()->inspector_id)->toBe($staff->id)
        ->and($cylinder->inspections()->first()->inspector_name)->toBe($staff->name);
    $this->get(route('cylinders.show', $cylinder))->assertOk()->assertSee('Oględziny');
    $this->get(route('cylinders.index', ['q' => 'SN-55']))->assertOk()->assertSee('02.01.2027');
    $this->patch(route('cylinders.archive', $cylinder), ['archived' => 1])->assertRedirect();
    $this->post(route('cylinders.inspections.store', $cylinder), $payload)->assertStatus(409);
    $this->get(route('cylinders.index'))->assertOk()->assertDontSee('SN-55');
    $this->get(route('cylinders.index', ['archived' => 1]))->assertOk()->assertSee('SN-55');
    $this->patch(route('cylinders.archive', $cylinder), ['archived' => 0])->assertRedirect();
    expect($cylinder->inspections()->count())->toBe(1)
        ->and(ActivityLog::where('auditable_type', Cylinder::class)->where('action', 'updated')->exists())->toBeTrue();
});

test('client and staff preview see only their selected company and cannot write', function () {
    enableCylinders();
    $own = registeredCylinder('OWN');
    $other = registeredCylinder('OTHER');
    Role::findOrCreate('client_user', 'web');
    $client = User::factory()->create();
    $client->assignRole('client_user');
    $client->companies()->attach($own->company_id);
    $this->actingAs($client)->get(route('client.cylinders.index'))->assertOk()->assertSee('OWN')->assertDontSee('OTHER');
    $this->get(route('client.cylinders.show', $own))->assertOk()->assertDontSee('Zapisz przegląd');
    $this->get(route('client.cylinders.show', $other))->assertNotFound();
    $this->post(route('cylinders.store'), [])->assertForbidden();
    $this->post(route('cylinders.inspections.store', $own), [])->assertForbidden();
    $this->actingAs(cylinderStaff())->withSession(['client_zone_company_id' => $own->company_id, 'client_zone_company_name' => 'Zakład'])
        ->get(route('client-zone.cylinders.index'))->assertOk()->assertSee('OWN')->assertDontSee('OTHER');
    $this->get(route('client-zone.cylinders.show', $other))->assertNotFound();
    CompanySettings::first()->update(['enabled_modules' => ['client_zone']]);
    $this->actingAs($client)->get(route('client.cylinders.index'))->assertForbidden();
});

test('cylinder permissions distinguish read access from inspector write access', function () {
    enableCylinders();
    Role::findOrCreate('Inspektor', 'web');
    $staff = User::factory()->create();
    $staff->assignRole('Inspektor');
    $this->actingAs($staff)->get(route('cylinders.index'))->assertForbidden();
    $staff->givePermissionTo('cylinders.view');
    $this->get(route('cylinders.index'))->assertOk();
    $this->get(route('cylinders.create'))->assertForbidden();
    $staff->givePermissionTo('cylinders.manage');
    $this->get(route('cylinders.create'))->assertOk();
});

test('validation prevents moving history and invalid inspection dates', function () {
    enableCylinders();
    $cylinder = registeredCylinder();
    $this->actingAs(cylinderStaff())->put(route('cylinders.update', $cylinder), ['company_id' => 999, 'serial_number' => 'ABC', 'type' => 'Test'])->assertSessionHasErrors('company_id');
    $this->post(route('cylinders.store'), ['company_id' => $cylinder->company_id, 'serial_number' => 'ABC-123', 'type' => 'Test'])->assertSessionHasErrors('serial_number');
    $this->post(route('cylinders.inspections.store', $cylinder), ['inspected_at' => '2026-01-01', 'next_due_at' => '2025-01-01', 'result' => 'automatic_approval', 'observations' => 'Test'])->assertSessionHasErrors(['next_due_at', 'result']);
    expect($cylinder->inspections()->count())->toBe(0);
});

test('backdated inspection does not replace the latest actual inspection date', function () {
    enableCylinders();
    $cylinder = registeredCylinder();
    $this->actingAs(cylinderStaff());
    foreach (['2026-02-01' => '2027-02-01', '2026-01-01' => '2027-01-01'] as $date => $due) {
        $this->post(route('cylinders.inspections.store', $cylinder), ['inspected_at' => $date, 'next_due_at' => $due, 'result' => 'further_review', 'observations' => 'Test'])->assertRedirect();
    }
    $this->get(route('cylinders.index'))->assertOk()->assertSee('01.02.2027')->assertDontSee('01.01.2027');
});
