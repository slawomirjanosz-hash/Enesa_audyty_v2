<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Cylinder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    CompanySettings::create(['name' => 'Rejestr', 'enabled_modules' => ['cylinders', 'client_zone']]);
    $this->inspector = User::factory()->create();
    $this->inspector->assignRole(Role::findOrCreate('superadmin'));
    $this->clientCompany = Company::create(['name' => 'Klient A', 'company_type' => 'client', 'status' => 'active']);
    $this->otherCompany = Company::create(['name' => 'Klient B', 'company_type' => 'client', 'status' => 'active']);
    $this->payload = ['company_id' => $this->clientCompany->id, 'name' => 'Butla azotowa', 'manufacturer_mark' => ' ACME ', 'serial_number' => ' sn-001 '];
});

test('cylinder registration saves fixed parameters without a type selector', function () {
    $this->actingAs($this->inspector)->get(route('cylinders.create'))->assertOk()
        ->assertSee('Ciśnienie próbne')->assertSee('Masa netto')->assertSee('Osprzęt')
        ->assertDontSee('name="device_type"', false)->assertDontSee('name="type"', false);
    $this->post(route('cylinders.store'), $this->payload + [
        'inventory_number' => 'EW-01', 'working_medium' => 'Azot', 'temperature_min_c' => '-20,5',
        'temperature_max_c' => '65', 'capacity_litres' => '40', 'working_pressure_bar' => '200',
        'test_pressure_bar' => '300', 'tare_or_gross_mass_kg' => '55,125', 'net_mass_kg' => '10',
        'stamped_empty_mass_kg' => '45', 'filling_mass_symbol' => 'N2', 'equipment_type' => 'Zawór',
        'equipment_mark' => 'CE 1234', 'manufactured_year' => '2020',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $cylinder = Cylinder::sole();
    expect($cylinder->device_type)->toBe('butla')->and($cylinder->serial_number)->toBe('SN-001')
        ->and($cylinder->manufacturer_mark)->toBe('ACME')->and($cylinder->name)->toBe('Butla azotowa')
        ->and((float) $cylinder->temperature_min_c)->toBe(-20.5)
        ->and((float) $cylinder->tare_or_gross_mass_kg)->toBe(55.125)
        ->and($cylinder->equipment_mark)->toBe('CE 1234');
    $this->put(route('cylinders.update', $cylinder), array_diff_key($this->payload, ['company_id' => true]) + ['notes' => 'Aktualizacja'])->assertSessionHasNoErrors();
    expect($cylinder->fresh()->notes)->toBe('Aktualizacja');
});

test('manufacturer mark and serial are unique globally while another mark may reuse serial', function () {
    $this->actingAs($this->inspector)->post(route('cylinders.store'), $this->payload)->assertSessionHasNoErrors();
    $this->post(route('cylinders.store'), array_replace($this->payload, ['company_id' => $this->otherCompany->id, 'manufacturer_mark' => 'acme']))->assertSessionHasErrors('serial_number');
    $this->post(route('cylinders.store'), array_replace($this->payload, ['manufacturer_mark' => 'OTHER']))->assertSessionHasNoErrors();
    expect(Cylinder::count())->toBe(2);
    expect(fn () => DB::table('cylinders')->insert(['company_id' => $this->otherCompany->id, 'serial_number' => 'SN-001', 'manufacturer_mark' => 'ACME', 'type' => 'Legacy']))->toThrow(QueryException::class);
});

test('cylinder parameter validation rejects missing identity invalid ranges and forged type', function () {
    $this->actingAs($this->inspector)->post(route('cylinders.store'), ['company_id' => $this->clientCompany->id])->assertSessionHasErrors(['name', 'manufacturer_mark', 'serial_number']);
    $this->post(route('cylinders.store'), $this->payload + ['device_type' => 'other', 'temperature_min_c' => '30', 'temperature_max_c' => '-10', 'net_mass_kg' => '-1', 'capacity_litres' => '0'])->assertSessionHasErrors(['device_type', 'temperature_max_c', 'net_mass_kg', 'capacity_litres']);
    expect(Cylinder::count())->toBe(0);
});

test('register filters and sorts all data columns before pagination and scopes client choices', function () {
    $this->actingAs($this->inspector);
    foreach (range(1, 31) as $i) {
        Cylinder::create(['company_id' => $this->clientCompany->id, 'type' => 'Legacy', 'name' => sprintf('Butla %02d', $i), 'manufacturer_mark' => sprintf('M%02d', $i), 'serial_number' => sprintf('SN%02d', $i), 'manufactured_year' => 1970 + $i])
            ->inspections()->create(['inspected_at' => sprintf('2025-01-%02d', $i), 'result' => 'no_findings', 'inspector_name' => 'Inspektor', 'observations' => 'Test']);
    }
    $foreign = Cylinder::create(['company_id' => $this->otherCompany->id, 'type' => 'Inny', 'name' => 'Obca butla', 'manufacturer_mark' => 'FOREIGN', 'serial_number' => 'FOREIGN']);
    foreach (['name', 'manufacturer', 'year', 'serial', 'last'] as $column) {
        foreach (['asc' => 'SN01', 'desc' => 'SN31'] as $direction => $expected) {
            $response = $this->get(route('cylinders.index', ['company_id' => $this->clientCompany->id, 'table_sort' => $column, 'table_direction' => $direction]))->assertOk();
            expect($response->viewData('cylinders')->first()->serial_number)->toBe($expected);
            expect($response->viewData('cylinders')->count())->toBe(30);
            $response->assertDontSee('FOREIGN')->assertSee('data-cylinder-select-all', false)->assertSee('data-sortable="false"', false);
        }
    }
    $this->get(route('cylinders.index', ['q' => 'Butla 12']))->assertOk()->assertSee('SN12')->assertDontSee('SN13');
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->companies()->attach($this->clientCompany);
    $this->actingAs($client)->get(route('client.cylinders.index'))->assertOk()->assertDontSee('Klient B')->assertDontSee('data-cylinder-select-all', false);
    $response = $this->get(route('client.cylinders.index', ['company_id' => $this->otherCompany->id]))->assertOk()->assertDontSee('FOREIGN');
    expect($response->viewData('cylinders')->total())->toBe(0);
    $this->get(route('client.cylinders.show', $foreign))->assertNotFound();
    $this->post(route('cylinders.store'), $this->payload)->assertForbidden();
});

test('parameter migration preserves legacy ownership description and inspection history', function () {
    $migration = require database_path('migrations/2026_10_08_120000_extend_cylinder_parameters.php');
    $migration->down();
    $id = DB::table('cylinders')->insertGetId(['company_id' => $this->clientCompany->id, 'serial_number' => 'OLD', 'manufacturer' => 'Dawny producent', 'type' => 'Stary opis', 'notes' => 'Stare uwagi']);
    DB::table('cylinder_inspections')->insert(['cylinder_id' => $id, 'inspector_name' => 'Inspektor', 'inspected_at' => '2025-01-01', 'result' => 'no_findings', 'observations' => 'Zachować']);
    $migration->up();
    $cylinder = Cylinder::findOrFail($id);
    expect($cylinder->name)->toBe('Stary opis')->and($cylinder->device_type)->toBe('butla')
        ->and($cylinder->manufacturer_mark)->toBeNull()->and($cylinder->manufacturer)->toBe('Dawny producent')
        ->and($cylinder->company_id)->toBe($this->clientCompany->id)
        ->and($cylinder->latestInspection->observations)->toBe('Zachować');
});
