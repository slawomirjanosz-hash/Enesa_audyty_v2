<?php

use App\Models\Company;
use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Models\ImportantContact;
use App\Models\Offer;
use App\Models\Project;
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

test('authorized user manages important contacts and CRM places them after suppliers', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('crm.companies.manage'));

    $this->actingAs($admin)->post(route('crm.important-contacts.store'), [
        'first_name' => 'Anna',
        'last_name' => 'Projektowa',
        'company_name' => 'Biuro Konstrukcji',
        'position' => 'Projektantka',
        'specialization' => 'Instalacje przemysłowe',
        'activity_description' => 'Projektuje instalacje dla zakładów produkcyjnych.',
        'help_description' => 'Może zweryfikować koncepcję i wskazać wykonawców.',
        'email' => 'anna@example.com',
    ])->assertRedirect(route('crm.index', ['tab' => 'contacts']));

    $contact = ImportantContact::firstOrFail();
    expect($contact->created_by)->toBe($admin->id);

    $this->actingAs($admin)->get(route('crm.index', ['tab' => 'contacts']))
        ->assertOk()
        ->assertSeeInOrder(['Zadania', 'Dostawcy', 'Ważne kontakty'])
        ->assertSee('Anna Projektowa')
        ->assertSee('Biuro Konstrukcji')
        ->assertSee('Może zweryfikować koncepcję i wskazać wykonawców.')
        ->assertSee('Szukaj osoby, firmy, specjalizacji');

    $this->actingAs($admin)->put(route('crm.important-contacts.update', $contact), [
        'first_name' => 'Anna',
        'last_name' => 'Projektowa',
        'company_name' => 'Nowe Biuro',
        'help_description' => 'Pomaga w odbiorach technicznych.',
    ])->assertRedirect(route('crm.index', ['tab' => 'contacts']));

    expect($contact->fresh()->company_name)->toBe('Nowe Biuro');

    $this->actingAs($admin)->delete(route('crm.important-contacts.destroy', $contact))
        ->assertRedirect(route('crm.index', ['tab' => 'contacts']));
    $this->assertDatabaseMissing('important_contacts', ['id' => $contact->id]);
});

test('CRM viewer sees important contacts but cannot manage them', function () {
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('crm_viewer');
    $role->givePermissionTo(Permission::findOrCreate('crm.view'));
    $viewer->assignRole($role);
    ImportantContact::create([
        'first_name' => 'Jan',
        'last_name' => 'Inżynier',
        'help_description' => 'Pomoc techniczna przy projekcie.',
    ]);

    $this->actingAs($viewer)->get(route('crm.index', ['tab' => 'contacts']))
        ->assertOk()
        ->assertSee('Jan Inżynier')
        ->assertDontSee('>Nowy ważny kontakt<', false);

    $this->actingAs($viewer)->post(route('crm.important-contacts.store'), [
        'first_name' => 'Bez',
        'last_name' => 'Uprawnień',
        'help_description' => 'Nie powinien zostać zapisany.',
    ])->assertForbidden();
});

test('staff with crm view permission sees shared CRM data created by another user', function () {
    $admin = User::factory()->create();
    $employee = User::factory()->create();
    $role = Role::findOrCreate('Użytkownik_Senior');
    $role->givePermissionTo([
        Permission::findOrCreate('dashboard.view'),
        Permission::findOrCreate('crm.view'),
        Permission::findOrCreate('crm.companies.manage'),
        Permission::findOrCreate('crm.leads.manage'),
        Permission::findOrCreate('crm.tasks.manage'),
    ]);
    $employee->assignRole($role);

    $company = Company::create([
        'name' => 'Wspólny klient CRM',
        'company_type' => 'client',
        'status' => 'active',
        'show_in_dashboard' => true,
    ]);
    CrmOpportunity::create([
        'title' => 'Wspólny lead CRM',
        'company_id' => $company->id,
        'stage' => 'new_lead',
        'created_by' => $admin->id,
        'assigned_to' => $admin->id,
    ]);

    $this->actingAs($employee)->get(route('crm.index', ['tab' => 'companies']))
        ->assertOk()
        ->assertSee('Wspólny klient CRM');

    $this->actingAs($employee)->get(route('crm.index', ['tab' => 'pipeline']))
        ->assertOk()
        ->assertSee('Wspólny lead CRM');

    $this->actingAs($employee)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Wspólny klient CRM');
});

test('superadmin sees tasks assigned to other users in the team task table', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole(Role::findOrCreate('superadmin'));
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Task::create([
        'title' => 'Zadanie admina widoczne dla superadmina',
        'assigned_to' => $admin->id,
        'created_by' => $superadmin->id,
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => now()->subDay(),
    ]);

    $this->actingAs($superadmin)->get(route('crm.index', ['tab' => 'tasks']))
        ->assertOk()
        ->assertSee('Zadania zespołu')
        ->assertSee('Zadanie admina widoczne dla superadmina')
        ->assertSee('<span class="badge badge-red">Do zrobienia</span>', false)
        ->assertDontSee('Wszystkie szanse')
        ->assertDontSee('Brak szans w tym etapie');
});

test('crm does not show or manage tasks belonging to projects', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/CRM/001', 'name' => 'Projekt poza CRM',
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 0,
        'created_by' => $admin->id,
    ]);
    $project->members()->attach($admin);
    $projectTask = $project->tasks()->create([
        'title' => 'Zadanie widoczne wyłącznie w projekcie',
        'assigned_to' => $admin->id, 'created_by' => $admin->id,
        'status' => 'todo', 'priority' => 'high',
    ]);
    Task::create([
        'title' => 'Zwykłe zadanie CRM',
        'assigned_to' => $admin->id, 'created_by' => $admin->id,
        'status' => 'todo', 'priority' => 'medium',
    ]);

    $this->actingAs($admin)->get(route('crm.index', ['tab' => 'tasks']))
        ->assertOk()
        ->assertSee('Zwykłe zadanie CRM')
        ->assertDontSee('Zadanie widoczne wyłącznie w projekcie')
        ->assertSee('<span class="tab-count">1</span>', false);

    $this->actingAs($admin)->put(route('crm.tasks.update', $projectTask), [])->assertNotFound();
    $this->actingAs($admin)->delete(route('crm.tasks.destroy', $projectTask))->assertNotFound();
});

test('own and team CRM task permissions have separate visibility and management scope', function () {
    $owner = User::factory()->create(['name' => 'Właściciel zadania']);
    $other = User::factory()->create(['name' => 'Drugi pracownik']);
    $ownRole = Role::findOrCreate('zadania_wlasne');
    $ownRole->givePermissionTo([
        Permission::findOrCreate('crm.tasks.own.manage'),
        Permission::findOrCreate('calendar.view'),
    ]);
    $owner->assignRole($ownRole);
    $other->assignRole('auditor');

    $ownTask = Task::create([
        'title' => 'Moje zadanie do edycji', 'assigned_to' => $owner->id,
        'created_by' => $other->id, 'status' => 'todo', 'priority' => 'medium',
    ]);
    $otherTask = Task::create([
        'title' => 'Cudze zadanie ukryte', 'assigned_to' => $other->id,
        'created_by' => $other->id, 'status' => 'todo', 'priority' => 'medium',
    ]);

    $this->actingAs($owner)->get(route('crm.index', ['tab' => 'tasks']))
        ->assertOk()
        ->assertSee('Moje zadanie do edycji')
        ->assertDontSee('Cudze zadanie ukryte')
        ->assertDontSee('Zadania zespołu');

    $this->actingAs($owner)->put(route('crm.tasks.update', $ownTask), [
        'title' => 'Moje zadanie zmienione', 'assigned_to' => $owner->id,
        'status' => 'in_progress', 'priority' => 'high',
    ])->assertRedirect();
    $this->actingAs($owner)->put(route('crm.tasks.update', $otherTask), [
        'title' => 'Niedozwolona zmiana', 'assigned_to' => $other->id,
        'status' => 'done', 'priority' => 'low',
    ])->assertForbidden();

    $this->actingAs($owner)->post(route('crm.tasks.store'), [
        'title' => 'Próba przypisania innej osobie', 'assigned_to' => $other->id,
        'status' => 'todo', 'priority' => 'medium',
    ])->assertRedirect();
    expect(Task::where('title', 'Próba przypisania innej osobie')->firstOrFail()->assigned_to)->toBe($owner->id);

    $manager = User::factory()->create(['name' => 'Kierownik zadań CRM']);
    $teamRole = Role::findOrCreate('zadania_zespolu');
    $teamRole->givePermissionTo(Permission::findOrCreate('crm.tasks.team.manage'));
    $manager->assignRole($teamRole);

    $this->actingAs($manager)->get(route('crm.index', ['tab' => 'tasks']))
        ->assertOk()
        ->assertSee('Zadania zespołu')
        ->assertSee('Cudze zadanie ukryte');
    $this->actingAs($manager)->put(route('crm.tasks.update', $otherTask), [
        'title' => 'Cudze zadanie zmienione przez kierownika', 'assigned_to' => $other->id,
        'status' => 'in_progress', 'priority' => 'high',
    ])->assertRedirect();
});

test('task can be linked to an opportunity and remains visible in the CRM task list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create([
        'name' => 'Klient z zadaniem w szansie',
        'company_type' => 'client',
        'status' => 'active',
    ]);
    $opportunity = CrmOpportunity::create([
        'title' => 'Szansa z podpiętym zadaniem',
        'company_id' => $company->id,
        'created_by' => $admin->id,
        'stage' => 'new_lead',
    ]);

    $this->actingAs($admin)->post(route('crm.tasks.store'), [
        'title' => 'Zadanie widoczne w szansie i na liście',
        'company_id' => $company->id,
        'crm_opportunity_id' => $opportunity->id,
        'assigned_to' => $admin->id,
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => '2026-08-30',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('tasks', [
        'title' => 'Zadanie widoczne w szansie i na liście',
        'crm_opportunity_id' => $opportunity->id,
    ]);

    $this->actingAs($admin)->get(route('crm.index', ['tab' => 'pipeline']))
        ->assertOk()
        ->assertSee('Powiązane zadania')
        ->assertSee('Zadanie widoczne w szansie', false);

    $this->actingAs($admin)->get(route('crm.index', ['tab' => 'tasks']))
        ->assertOk()
        ->assertSee('Szansa CRM')
        ->assertSee('Szansa z podpiętym zadaniem')
        ->assertSee('Zadanie widoczne w szansie');
});

test('clicking a pipeline opportunity opens details with explicit client and edit actions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Klient szansy', 'status' => 'active']);
    CrmOpportunity::create([
        'title' => 'Szansa otwierana w oknie',
        'company_id' => $company->id,
        'stage' => 'contact',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->get(route('crm.index', ['tab' => 'pipeline']))
        ->assertOk()
        ->assertSee('Szczegóły szansy')
        ->assertSee('Przejdź do karty klienta')
        ->assertSee('Edytuj szansę')
        ->assertDontSee('Czy przejść do jego karty?');
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

    $task = Task::create([
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
        ->assertSee('openCompanyTaskEditModal('.$task->id, false)
        ->assertSee('Edytuj')
        ->assertDontSee('Zadanie innej firmy');
});

test('admin edits a client task directly from the client CRM card', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('system.full_access'));
    $company = Company::create(['name' => 'Klient edytowanego zadania', 'company_type' => 'client', 'status' => 'active']);
    $lead = CrmOpportunity::create([
        'title' => 'Lead po edycji',
        'company_id' => $company->id,
        'created_by' => $admin->id,
        'stage' => 'new_lead',
    ]);
    $task = Task::create([
        'title' => 'Pierwotne zadanie',
        'company_id' => $company->id,
        'created_by' => $admin->id,
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($admin)
        ->put(route('crm.tasks.update', $task), [
            'title' => 'Zadanie po edycji',
            'description' => 'Uzupełniony opis',
            'company_id' => $company->id,
            'company_context_id' => $company->id,
            'crm_opportunity_id' => $lead->id,
            'assigned_to' => $admin->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => '2026-08-28',
        ])
        ->assertRedirect(route('companies.show', $company).'#crm')
        ->assertSessionHas('success');

    expect($task->refresh()->title)->toBe('Zadanie po edycji')
        ->and($task->crm_opportunity_id)->toBe($lead->id)
        ->and($task->status)->toBe('in_progress')
        ->and($task->priority)->toBe('high');
});

test('deleted CRM task is visible in trash statistics and can be restored', function () {
    $admin = User::factory()->create(['name' => 'Administrator CRM']);
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('system.full_access'));
    $employee = User::factory()->create(['name' => 'Jan Zadaniowy']);
    $employee->assignRole('auditor');
    $company = Company::create(['name' => 'Klient zadania w koszu', 'company_type' => 'client', 'status' => 'active']);
    $task = Task::create([
        'title' => 'Zadanie do zachowania w historii',
        'company_id' => $company->id,
        'assigned_to' => $employee->id,
        'created_by' => $admin->id,
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => '2026-08-30',
    ]);

    $this->actingAs($admin)
        ->delete(route('crm.tasks.destroy', $task))
        ->assertRedirect(route('crm.index', ['tab' => 'tasks']))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('tasks', ['id' => $task->id, 'deleted_by' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('crm.index', ['tab' => 'trash']))
        ->assertOk()
        ->assertSee('Zadanie do zachowania w historii')
        ->assertSee('Jan Zadaniowy')
        ->assertSee('Administrator CRM')
        ->assertSee('Przywróć');

    $this->actingAs($admin)
        ->patch(route('crm.tasks.restore', $task->id))
        ->assertRedirect(route('crm.index', ['tab' => 'trash']))
        ->assertSessionHas('success');

    expect(Task::find($task->id))->not->toBeNull()
        ->and(Task::find($task->id)?->deleted_by)->toBeNull();
});

test('admin adds a client task with an optional related lead from the client card', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(Permission::findOrCreate('system.full_access'));
    $company = Company::create(['name' => 'Klient z nowym zadaniem', 'company_type' => 'client', 'status' => 'active']);
    $otherCompany = Company::create(['name' => 'Inny klient z leadem', 'company_type' => 'client', 'status' => 'active']);
    $lead = CrmOpportunity::create([
        'title' => 'Lead klienta',
        'company_id' => $company->id,
        'created_by' => $admin->id,
        'stage' => 'new_lead',
    ]);
    $foreignLead = CrmOpportunity::create([
        'title' => 'Obcy lead',
        'company_id' => $otherCompany->id,
        'created_by' => $admin->id,
        'stage' => 'new_lead',
    ]);

    $this->actingAs($admin)
        ->get(route('companies.show', $company).'#crm')
        ->assertOk()
        ->assertSee('Dodaj zadanie')
        ->assertSee('id="companyTaskModal"', false)
        ->assertSee('name="company_id" value="'.$company->id.'"', false)
        ->assertSee('Powiązany lead (opcjonalnie)')
        ->assertSee('Lead klienta')
        ->assertDontSee('Obcy lead');

    $this->actingAs($admin)
        ->post(route('crm.tasks.store'), [
            'title' => 'Przygotować rozmowę',
            'company_id' => $company->id,
            'company_context_id' => $company->id,
            'crm_opportunity_id' => $lead->id,
            'assigned_to' => $admin->id,
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => '2026-08-25',
        ])
        ->assertRedirect(route('companies.show', $company).'#crm')
        ->assertSessionHas('success');

    $task = Task::where('title', 'Przygotować rozmowę')->firstOrFail();
    expect($task->company_id)->toBe($company->id)
        ->and($task->crm_opportunity_id)->toBe($lead->id)
        ->and($task->assigned_to)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('crm.tasks.store'), [
            'title' => 'Niepoprawne powiązanie',
            'company_id' => $company->id,
            'company_context_id' => $company->id,
            'crm_opportunity_id' => $foreignLead->id,
            'status' => 'todo',
            'priority' => 'medium',
        ])
        ->assertSessionHasErrors('crm_opportunity_id', null, 'taskCreate');

    expect(Task::where('title', 'Niepoprawne powiązanie')->exists())->toBeFalse();
});
