<?php

use App\Models\Company;
use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Models\Offer;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['admin', 'auditor'] as $role) {
        Role::findOrCreate($role);
    }
});

test('crm companies and pipeline tabs render without loading errors', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Company::create(['name' => 'Firma CRM', 'company_type' => 'client', 'status' => 'active']);

    $this->actingAs($admin)->get(route('crm.index', ['tab' => 'companies']))
        ->assertOk()->assertSee('Firma CRM');
    $this->actingAs($admin)->get(route('crm.index', ['tab' => 'pipeline']))
        ->assertOk()->assertSee('Leady związane ze mną');
});

test('lead can include users without full operational access and be filtered as related to them', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $employee = User::factory()->create(['name' => 'Pracownik powiązany']);
    $employee->assignRole('auditor');
    $company = Company::create(['name' => 'Firma powiązana', 'company_type' => 'client', 'status' => 'active']);

    $this->actingAs($admin)->post(route('crm.opportunities.store'), [
        'title' => 'Lead z zespołem',
        'company_id' => $company->id,
        'stage' => 'new_lead',
        'assigned_to' => $admin->id,
        'related_users' => [$employee->id],
    ])->assertRedirect(route('crm.index', ['tab' => 'pipeline']));

    $lead = CrmOpportunity::where('title', 'Lead z zespołem')->firstOrFail();
    expect($lead->relatedUsers()->whereKey($employee->id)->exists())->toBeTrue();

    CrmOpportunity::create([
        'title' => 'Lead niezwiązany',
        'company_id' => $company->id,
        'stage' => 'new_lead',
        'created_by' => User::factory()->create()->id,
    ]);

    $this->actingAs($admin)->get(route('crm.index', ['tab' => 'pipeline', 'related_to_me' => 1]))
        ->assertOk()->assertSee('Lead z zespołem')->assertDontSee('Lead niezwiązany');

    $this->actingAs($employee)->get(route('crm.index', ['tab' => 'pipeline']))
        ->assertOk()->assertSee('Lead z zespołem')->assertDontSee('Lead niezwiązany');
});

test('admin can attach an unlinked offer to a lead of the same company', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Firma A']);
    $opportunity = CrmOpportunity::create([
        'title' => 'Audyt dla Firmy A',
        'company_id' => $company->id,
        'stage' => 'new_lead',
        'created_by' => $admin->id,
    ]);
    $offer = Offer::create([
        'offer_number' => 'OF_TEST_LINK_001',
        'offer_full_number' => 'OF_TEST_LINK_001',
        'company_id' => $company->id,
        'status' => 'w_toku',
        'created_by_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('crm.opportunities.attach-offer', $opportunity), ['offer_id' => $offer->id])
        ->assertRedirect(route('crm.index', ['tab' => 'pipeline']));

    expect($offer->refresh()->crm_opportunity_id)->toBe($opportunity->id)
        ->and($opportunity->refresh()->stage)->toBe('offer')
        ->and(CrmActivity::where('company_id', $company->id)->where('type', 'offer_linked')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('offers.status', $offer), ['status' => 'wygrana'])
        ->assertSessionHas('success');

    expect($opportunity->refresh()->stage)->toBe('realization')
        ->and(CrmActivity::where('company_id', $company->id)->where('type', 'offer_status_changed')->exists())->toBeTrue();
});

test('offer cannot be attached to a lead of another company', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $leadCompany = Company::create(['name' => 'Firma lead']);
    $otherCompany = Company::create(['name' => 'Inna firma']);
    $opportunity = CrmOpportunity::create([
        'title' => 'Lead Firmy',
        'company_id' => $leadCompany->id,
        'stage' => 'contact',
        'created_by' => $admin->id,
    ]);
    $offer = Offer::create([
        'offer_number' => 'OF_TEST_LINK_002',
        'offer_full_number' => 'OF_TEST_LINK_002',
        'company_id' => $otherCompany->id,
        'status' => 'w_toku',
        'created_by_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('crm.opportunities.attach-offer', $opportunity), ['offer_id' => $offer->id])
        ->assertSessionHas('error');

    expect($offer->refresh()->crm_opportunity_id)->toBeNull()
        ->and($opportunity->refresh()->stage)->toBe('contact');
});

test('admin adds a lead directly from the client card with company preselected', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create([
        'name' => 'Klient dla leada',
        'company_type' => 'client',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('companies.show', $company))
        ->assertOk()
        ->assertSee('Dodaj lead')
        ->assertSee('id="companyLeadModal"', false)
        ->assertSee('name="company_id" value="'.$company->id.'"', false)
        ->assertSee('name="company_context_id" value="'.$company->id.'"', false)
        ->assertSee('name="stage" value="new_lead"', false);

    $this->actingAs($admin)
        ->post(route('crm.opportunities.store'), [
            'title' => 'Modernizacja instalacji klienta',
            'description' => 'Lead utworzony z karty klienta',
            'company_id' => $company->id,
            'company_context_id' => $company->id,
            'stage' => 'new_lead',
            'assigned_to' => $admin->id,
            'value' => 125000,
        ])
        ->assertRedirect(route('companies.show', $company).'#crm')
        ->assertSessionHas('success');

    $lead = CrmOpportunity::where('title', 'Modernizacja instalacji klienta')->firstOrFail();
    expect($lead->company_id)->toBe($company->id)
        ->and($lead->stage)->toBe('new_lead')
        ->and($lead->assigned_to)->toBe($admin->id);
});

test('client card opens a CRM opportunity preview and saves edits back to the card', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('system.full_access'));
    $company = Company::create(['name' => 'Klient z szansą', 'company_type' => 'client', 'status' => 'active']);
    $opportunity = CrmOpportunity::create([
        'title' => 'Modernizacja instalacji',
        'description' => 'Opis szansy widoczny w podglądzie',
        'company_id' => $company->id,
        'assigned_to' => $admin->id,
        'created_by' => $admin->id,
        'stage' => 'contact',
        'value' => 125000,
        'expected_close_date' => '2026-10-15',
        'notes' => 'Ważna notatka CRM',
    ]);

    $this->actingAs($admin)
        ->get(route('companies.show', $company).'#crm')
        ->assertOk()
        ->assertSee('openCompanyOpportunityById('.$opportunity->id.')', false)
        ->assertSee('companyOpportunityModal', false)
        ->assertSee('Opis szansy widoczny w podglądzie')
        ->assertSee('Modernizacja instalacji');

    $this->actingAs($admin)
        ->patch(route('crm.opportunities.update', $opportunity), [
            'title' => 'Modernizacja instalacji po edycji',
            'company_id' => $company->id,
            'company_context_id' => $company->id,
            'stage' => 'offer',
            'value' => 130000,
        ])
        ->assertRedirect(route('companies.show', $company).'#crm');

    expect($opportunity->refresh()->title)->toBe('Modernizacja instalacji po edycji')
        ->and($opportunity->stage)->toBe('offer');
});

test('client CRM card shows tasks related to that company', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('system.full_access'));
    $company = Company::create(['name' => 'Klient z zadaniami', 'company_type' => 'client', 'status' => 'active']);
    $otherCompany = Company::create(['name' => 'Inny klient', 'company_type' => 'client', 'status' => 'active']);

    Task::create([
        'title' => 'Telefon do klienta',
        'company_id' => $company->id,
        'assigned_to' => $admin->id,
        'created_by' => $admin->id,
        'status' => 'todo',
        'priority' => 'high',
        'due_date' => '2026-08-20',
    ]);
    Task::create([
        'title' => 'Zadanie innej firmy',
        'company_id' => $otherCompany->id,
        'created_by' => $admin->id,
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($admin)
        ->get(route('companies.show', $company).'#crm')
        ->assertOk()
        ->assertSee('Powiązane zadania CRM')
        ->assertSee('Telefon do klienta')
        ->assertDontSee('Zadanie innej firmy');
});
