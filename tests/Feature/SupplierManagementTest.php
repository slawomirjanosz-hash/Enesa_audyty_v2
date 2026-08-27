<?php

use App\Mail\ClientRegistered;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['superadmin', 'admin', 'auditor_senior', 'auditor', 'client_admin', 'client_user'] as $role) {
        Role::findOrCreate($role);
    }
    Mail::fake();
});

test('admin creates a supplier instead of a client and sees it in supplier views', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('suppliers.index'))
        ->assertOk()
        ->assertSee('Dodaj dostawcę')
        ->assertSee('name="company_type"', false)
        ->assertSee('value="supplier" selected', false)
        ->assertSee(route('companies.store'), false);

    $response = $this->actingAs($admin)->post(route('companies.store'), [
        'company_type' => 'supplier',
        'name' => 'Hydro Dostawy',
        'nip' => '1234567890',
        'city' => 'Katowice',
        'email' => 'biuro@hydro.example',
        'supplier_capabilities' => 'Dostawy armatury i pomp przemysłowych',
        'supplier_materials' => "Pompy\nZawory\nRurociągi",
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $supplier = Company::where('name', 'Hydro Dostawy')->firstOrFail();
    $response->assertRedirect(route('suppliers.show', $supplier));
    expect($supplier->company_type)->toBe('supplier')
        ->and($supplier->status)->toBe('active');
    Mail::assertNotSent(ClientRegistered::class);

    $this->actingAs($admin)->get(route('suppliers.index', ['q' => 'Pompy']))
        ->assertOk()
        ->assertSee('Hydro Dostawy')
        ->assertSee('Kafelki');

    $this->actingAs($admin)->get(route('crm.index', ['tab' => 'suppliers']))
        ->assertOk()
        ->assertSee('Hydro Dostawy');

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Hydro Dostawy');
});

test('supplier profile lists materials and projects in which supplier participates', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $supplier = Company::create([
        'company_type' => 'supplier', 'name' => 'Elektro Hurt', 'status' => 'active',
        'supplier_capabilities' => 'Dostawy elektryczne', 'supplier_materials' => 'Kable, Rozdzielnice',
    ]);
    $client = Company::create(['company_type' => 'client', 'name' => 'Klient Projektu', 'status' => 'active']);
    $project = Project::create([
        'number' => 'PRJ/SUP/001', 'name' => 'Modernizacja rozdzielni', 'company_id' => $client->id,
        'manager_id' => $manager->id, 'status' => 'active', 'contract_value' => 50000, 'created_by' => $manager->id,
    ]);
    $project->members()->attach($manager);
    $financeOnlyProject = Project::create([
        'number' => 'PRJ/SUP/FIN', 'name' => 'Projekt tylko finansowy', 'company_id' => $client->id,
        'manager_id' => $manager->id, 'status' => 'active', 'contract_value' => 12000, 'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)->post(route('projects.requirements.store', $project), [
        'type' => 'material', 'name' => 'Kabel YKY', 'quantity' => 200, 'unit' => 'm',
        'estimated_cost' => 6000, 'status' => 'ordered', 'supplier_company_id' => $supplier->id,
    ])->assertSessionHas('success');

    $requirement = $project->requirements()->firstOrFail();
    expect($requirement->supplier_company_id)->toBe($supplier->id)
        ->and($requirement->supplier)->toBe('Elektro Hurt');

    $this->actingAs($manager)->post(route('projects.finances.store', $financeOnlyProject), [
        'type' => 'cost', 'name' => 'Zakup rozdzielnic', 'supplier_company_id' => $supplier->id,
        'entry_date' => '2026-08-07', 'amount' => 3500, 'status' => 'issued',
    ])->assertSessionHas('success');

    $this->actingAs($manager)->get(route('suppliers.show', $supplier))
        ->assertOk()
        ->assertSee('Modernizacja rozdzielni')
        ->assertSee('Projekt tylko finansowy')
        ->assertSee('Kabel YKY')
        ->assertSee('Rozdzielnice');
});

test('client cannot be assigned as a registered project supplier', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $client = Company::create(['company_type' => 'client', 'name' => 'Zwykły klient', 'status' => 'active']);
    $project = Project::create([
        'number' => 'PRJ/SUP/002', 'name' => 'Walidacja dostawcy', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 1000, 'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)->post(route('projects.requirements.store', $project), [
        'type' => 'material', 'name' => 'Materiał', 'quantity' => 1,
        'status' => 'requested', 'supplier_company_id' => $client->id,
    ])->assertSessionHasErrors('supplier_company_id');

    expect($project->requirements()->count())->toBe(0);
});

test('client and supplier details expose editing through explicit buttons', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $client = Company::create(['company_type' => 'client', 'name' => 'Klient do edycji', 'status' => 'active']);
    $supplier = Company::create(['company_type' => 'supplier', 'name' => 'Dostawca do edycji', 'status' => 'active']);

    $this->actingAs($admin)->get(route('companies.show', $client))
        ->assertOk()
        ->assertSee('Edytuj klienta')
        ->assertSee("createForm.style.display = mode === 'edit' ? 'none' : 'block'", false)
        ->assertSee("openUserModal('edit')", false);

    $this->actingAs($admin)->get(route('suppliers.show', $supplier))
        ->assertOk()
        ->assertSee('Edytuj dostawcę')
        ->assertDontSee('Edytuj profil dostawcy');

    $this->actingAs($admin)->put(route('suppliers.update', $supplier), [
        'name' => '', 'email' => 'niepoprawny-adres',
    ])->assertSessionHasErrors(['name', 'email'], null, 'supplierEdit');

    $this->actingAs($admin)->put(route('suppliers.update', $supplier), [
        'name' => 'Dostawca po edycji', 'supplier_materials' => 'Pompy, zawory',
    ])->assertRedirect(route('suppliers.show', $supplier));

    expect($supplier->refresh()->name)->toBe('Dostawca po edycji')
        ->and($supplier->supplier_materials)->toBe('Pompy, zawory');
});
