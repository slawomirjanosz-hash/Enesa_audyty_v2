<?php

use App\Exports\ProjectGanttExport;
use App\Exports\ProjectRequirementsListExport;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectFinancialEntry;
use App\Models\ProjectRequirement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['superadmin', 'admin', 'auditor_senior', 'auditor', 'client_admin', 'client_user'] as $role) {
        Role::findOrCreate($role);
    }
});

test('admin creates a project with manager and team', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $member = User::factory()->create();
    $member->assignRole('auditor');
    $company = Company::create(['name' => 'Klient projektu']);

    $this->actingAs($admin)->post(route('projects.store'), [
        'number' => 'PRJ/2026/001',
        'name' => 'Modernizacja instalacji',
        'company_id' => $company->id,
        'manager_id' => $manager->id,
        'member_ids' => [$member->id],
        'status' => 'active',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'contract_value' => 250000,
    ])->assertRedirect();

    $project = Project::firstOrFail();
    expect($project->manager_id)->toBe($manager->id)
        ->and($project->members->pluck('id'))->toContain($manager->id, $member->id);

    $this->actingAs($member)->get(route('projects.show', $project))->assertOk();
});

test('uploaded project document remains visible after reopening the project', function () {
    Storage::fake('local');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/DOC/001', 'name' => 'Projekt z dokumentem',
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 0,
        'created_by' => $admin->id,
    ]);
    $project->members()->attach($admin);

    $this->actingAs($admin)->post(route('projects.documents.store', $project), [
        'file' => UploadedFile::fake()->create('instrukcja projektu.pdf', 120, 'application/pdf'),
    ])->assertRedirect(route('projects.show', ['project' => $project, 'tab' => 'documents']))
        ->assertSessionHas('success');

    $document = Document::where('project_id', $project->id)->firstOrFail();
    Storage::disk('local')->assertExists($document->stored_path);

    $this->actingAs($admin)->get(route('projects.show', ['project' => $project, 'tab' => 'documents']))
        ->assertOk()
        ->assertSee('instrukcja projektu.pdf')
        ->assertSee(route('projects.documents.download', [$project, $document]));
});

test('only admin or superadmin can remove a project', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $operator = User::factory()->create();
    $operatorRole = Role::findOrCreate('operator_z_pelnym_dostepem');
    $operatorRole->givePermissionTo([
        Permission::findOrCreate('system.full_access'),
        Permission::findOrCreate('projects.delete'),
    ]);
    $operator->assignRole($operatorRole);
    $project = Project::create([
        'number' => 'PRJ/DELETE/001', 'name' => 'Projekt utworzony omyłkowo',
        'manager_id' => $admin->id, 'status' => 'planned', 'contract_value' => 0,
        'created_by' => $admin->id,
    ]);
    $project->members()->attach([$admin->id, $operator->id]);

    $this->actingAs($operator)->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee('Usuń projekt');
    $this->actingAs($operator)->delete(route('projects.destroy', $project))->assertForbidden();
    $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);

    $this->actingAs($admin)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Usuń projekt');
    $this->actingAs($admin)->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success');
    $this->assertSoftDeleted('projects', ['id' => $project->id]);
});

test('external project user sees only assigned projects and permitted project tabs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $external = User::factory()->create();
    $role = Role::findOrCreate('projektant_zewnetrzny');
    $role->givePermissionTo([
        Permission::findOrCreate('projects.view'),
        Permission::findOrCreate('projects.edit'),
        Permission::findOrCreate('projects.schedule.view'),
    ]);
    $external->assignRole($role);

    $visible = Project::create([
        'number' => 'PRJ/EXT/001', 'name' => 'Projekt dostępny dla projektanta',
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 50000,
        'created_by' => $admin->id,
    ]);
    $visible->members()->attach([$admin->id, $external->id]);
    $hidden = Project::create([
        'number' => 'PRJ/EXT/002', 'name' => 'Projekt ukryty przed projektantem',
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 80000,
        'created_by' => $admin->id,
    ]);
    $hidden->members()->attach($admin);

    $this->actingAs($external)->get(route('projects.index'))
        ->assertOk()
        ->assertSee('Projekt dostępny dla projektanta')
        ->assertDontSee('Projekt ukryty przed projektantem')
        ->assertDontSee('50 000,00 zł');

    $this->actingAs($external)->get(route('projects.show', $visible))
        ->assertOk()
        ->assertSee('Harmonogram i zadania')
        ->assertDontSee('Finanse')
        ->assertDontSee('Materiały i usługi')
        ->assertDontSee('Dokumenty projektu')
        ->assertDontSee('50 000,00 zł');

    $this->actingAs($external)->get(route('projects.show', $hidden))->assertForbidden();

    $this->actingAs($external)->put(route('projects.update', $visible), [
        'number' => $visible->number,
        'name' => 'Projekt dostępny po edycji',
        'manager_id' => $admin->id,
        'member_ids' => [$external->id],
        'status' => 'active',
    ])->assertRedirect(route('projects.show', $visible));

    expect((float) $visible->refresh()->contract_value)->toBe(50000.0);
});

test('assigned user with schedule management can add gantt tasks without project edit permission', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $scheduler = User::factory()->create();
    $outsider = User::factory()->create();
    $role = Role::findOrCreate('planista_gantta');
    $role->givePermissionTo([
        Permission::findOrCreate('projects.view'),
        Permission::findOrCreate('projects.schedule.manage'),
    ]);
    $scheduler->assignRole($role);
    $project = Project::create([
        'number' => 'PRJ/GANTT/ROLE', 'name' => 'Harmonogram planisty',
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 0,
        'created_by' => $admin->id,
    ]);
    $project->members()->attach([$admin->id, $scheduler->id]);
    $foreignProject = Project::create([
        'number' => 'PRJ/GANTT/FOREIGN', 'name' => 'Obcy harmonogram',
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 0,
        'created_by' => $admin->id,
    ]);
    $foreignProject->members()->attach($admin);

    $this->actingAs($scheduler)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('id="gantt-add-task"', false)
        ->assertSee('id="gantt-task-modal"', false)
        ->assertSee('progress-11-25', false)->assertSee('progress-26-50', false)
        ->assertSee('progress-51-75', false)->assertSee('progress-76-99', false)
        ->assertSee('progress-100', false)->assertSee('overflow-x:hidden', false);

    $taskData = [
        'title' => 'Zadanie dodane przez planistę', 'start_date' => '2026-08-24',
        'due_date' => '2026-08-26', 'status' => 'todo', 'priority' => 'medium', 'progress' => 0,
    ];
    $this->actingAs($scheduler)->post(route('projects.tasks.store', $project), $taskData)
        ->assertSessionHas('success');
    $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => $taskData['title']]);

    $this->actingAs($scheduler)->post(route('projects.tasks.store', $project), array_merge($taskData, [
        'title' => 'Zadanie dla osoby spoza projektu',
        'assigned_to' => $outsider->id,
    ]))->assertSessionHasErrors('assigned_to');
    $this->assertDatabaseMissing('tasks', ['project_id' => $project->id, 'title' => 'Zadanie dla osoby spoza projektu']);

    $this->actingAs($scheduler)->post(route('projects.tasks.store', $foreignProject), $taskData)
        ->assertForbidden();
});

test('project role can see material prices without seeing service prices', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('materialowiec_zewnetrzny');
    $role->givePermissionTo([
        Permission::findOrCreate('system.full_access'),
        Permission::findOrCreate('projects.view'),
        Permission::findOrCreate('projects.edit'),
        Permission::findOrCreate('projects.requirements.view'),
        Permission::findOrCreate('projects.requirements.manage'),
        Permission::findOrCreate('projects.requirements.material_prices.view'),
    ]);
    $viewer->assignRole($role);
    $project = Project::create([
        'number' => 'PRJ/CENY/001', 'name' => 'Projekt z oddzielnymi cenami',
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 100000,
        'created_by' => $admin->id,
    ]);
    $project->members()->attach([$admin->id, $viewer->id]);
    ProjectRequirement::create([
        'project_id' => $project->id, 'type' => 'material', 'name' => 'Materiał z widoczną ceną',
        'quantity' => 1, 'unit' => 'szt.', 'estimated_cost' => 1234.56, 'supplier' => 'Dostawca materiałów', 'status' => 'planned',
        'created_by' => $admin->id,
    ]);
    $service = ProjectRequirement::create([
        'project_id' => $project->id, 'type' => 'service', 'name' => 'Usługa z ukrytą ceną',
        'quantity' => 1, 'unit' => 'usł.', 'estimated_cost' => 9876.54, 'supplier' => 'Firma usługowa', 'status' => 'planned',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($viewer)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Materiał z widoczną ceną')
        ->assertSee('1 234,56 zł')
        ->assertSee('Dostawca materiałów · 1 poz.')
        ->assertSee('data-requirement-summary="planned"', false)
        ->assertSee('data-supplier="Dostawca materiałów"', false)
        ->assertSee('data-requirement-status="planned"', false)
        ->assertSee('Usługa z ukrytą ceną')
        ->assertSee('Brak dostępu')
        ->assertSee('Podsumowanie obejmuje wyłącznie ceny materiałów')
        ->assertDontSee('Firma usługowa · 1 poz.')
        ->assertDontSee('9 876,54 zł');

    $this->actingAs($viewer)->patch(route('projects.requirements.update', [$project, $service]), [
        'type' => 'service', 'name' => $service->name, 'quantity' => 1, 'unit' => 'usł.',
        'unit_cost' => 1, 'status' => 'planned',
    ])->assertRedirect();

    expect((float) $service->refresh()->estimated_cost)->toBe(9876.54);
});

test('project editor changes an internal project into a client project', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $client = Company::create(['name' => 'Klient przypisany', 'company_type' => 'client']);
    $supplier = Company::create(['name' => 'Dostawca niedozwolony', 'company_type' => 'supplier']);
    $project = Project::create([
        'number' => 'PRJ/2026/EDIT', 'name' => 'Projekt wewnętrzny', 'manager_id' => $admin->id,
        'status' => 'planned', 'contract_value' => 10000, 'created_by' => $admin->id,
    ]);
    $project->members()->attach($admin);
    $task = $project->tasks()->create([
        'title' => 'Zadanie projektu', 'assigned_to' => $admin->id, 'created_by' => $admin->id,
        'status' => 'todo', 'priority' => 'medium', 'progress' => 0,
        'start_date' => '2026-08-10', 'due_date' => '2026-08-12', 'project_position' => 1,
    ]);

    $this->actingAs($admin)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Edytuj projekt')
        ->assertSee("document.getElementById('project-edit-modal').classList.add('open')", false)
        ->assertSee('Projekt dla klienta: Klient przypisany');

    $this->actingAs($admin)->put(route('projects.update', $project), [
        'number' => $project->number, 'name' => 'Projekt klienta', 'company_id' => $client->id,
        'manager_id' => $admin->id, 'member_ids' => [$admin->id], 'status' => 'active',
        'start_date' => '2026-08-10', 'end_date' => '2026-09-30', 'contract_value' => 15000,
        'description' => 'Projekt zmieniony z wewnętrznego.',
    ])->assertRedirect(route('projects.show', $project));

    expect($project->refresh()->company_id)->toBe($client->id)
        ->and($project->name)->toBe('Projekt klienta')
        ->and($task->refresh()->company_id)->toBe($client->id);

    $this->actingAs($admin)->put(route('projects.update', $project), [
        'number' => $project->number, 'name' => $project->name, 'company_id' => $supplier->id,
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 15000,
    ])->assertSessionHasErrors(['company_id'], null, 'projectEdit');
});

test('project manager manages schedule tasks finances and requirements', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/002', 'name' => 'Projekt testowy', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 100000, 'created_by' => $manager->id,
    ]);
    $project->members()->attach($manager);

    $this->actingAs($manager)->post(route('projects.tasks.store', $project), [
        'title' => 'Zamontować rozdzielnię', 'assigned_to' => $manager->id,
        'start_date' => '2026-08-02', 'due_date' => '2026-08-10',
        'status' => 'todo', 'priority' => 'high', 'progress' => 0,
    ])->assertSessionHas('success');
    $task = $project->tasks()->firstOrFail();
    $this->actingAs($manager)->post(route('projects.tasks.store', $project), [
        'title' => 'Uruchomić instalację', 'assigned_to' => $manager->id,
        'depends_on_task_id' => $task->id, 'start_date' => '2026-08-11', 'due_date' => '2026-08-15',
        'status' => 'todo', 'priority' => 'medium', 'progress' => 0,
    ])->assertSessionHas('success');
    $dependentTask = $project->tasks()->where('title', 'Uruchomić instalację')->firstOrFail();
    $this->actingAs($manager)->post(route('projects.tasks.store', $project), [
        'title' => 'Odbiór końcowy', 'assigned_to' => $manager->id,
        'depends_on_task_id' => $dependentTask->id, 'start_date' => '2026-08-16', 'due_date' => '2026-08-20',
        'status' => 'todo', 'priority' => 'medium', 'progress' => 0,
    ])->assertSessionHas('success');
    $this->actingAs($manager)->patchJson(route('projects.tasks.update', [$project, $task]), [
        'start_date' => '2026-08-04', 'due_date' => '2026-08-14', 'progress' => 40,
    ])->assertOk()->assertJsonPath('status', 'in_progress');
    expect($dependentTask->refresh()->start_date->format('Y-m-d'))->toBe('2026-08-15')
        ->and($project->tasks()->where('title', 'Odbiór końcowy')->first()->start_date->format('Y-m-d'))->toBe('2026-08-20');
    $this->actingAs($manager)->patchJson(route('projects.tasks.update', [$project, $task]), [
        'depends_on_task_id' => $dependentTask->id, 'progress' => 40,
    ])->assertUnprocessable();
    $finalTask = $project->tasks()->where('title', 'Odbiór końcowy')->firstOrFail();
    $this->actingAs($manager)->postJson(route('projects.tasks.reorder', $project), [
        'order' => [$finalTask->id, $dependentTask->id, $task->id],
    ])->assertOk();
    expect($project->tasks()->pluck('id')->all())->toBe([$finalTask->id, $dependentTask->id, $task->id]);
    $this->actingAs($manager)->post(route('projects.finances.store', $project), [
        'type' => 'invoice', 'name' => 'Faktura zaliczkowa', 'entry_date' => '2026-08-05',
        'amount' => 30000, 'status' => 'issued',
    ])->assertSessionHas('success');
    $this->actingAs($manager)->post(route('projects.finances.store', $project), [
        'type' => 'cost', 'name' => 'Materiały', 'entry_date' => '2026-08-06',
        'amount' => 12000, 'status' => 'paid',
    ])->assertSessionHas('success');
    $this->actingAs($manager)->post(route('projects.requirements.store', $project), [
        'type' => 'material', 'name' => 'Przewód', 'quantity' => 100, 'unit' => 'm',
        'estimated_cost' => 2500, 'status' => 'requested',
    ])->assertSessionHas('success');

    expect($project->refresh()->tasks)->toHaveCount(3)
        ->and($task->refresh()->status)->toBe('in_progress')
        ->and($task->start_date->format('Y-m-d'))->toBe('2026-08-04')
        ->and($project->requirements)->toHaveCount(1)
        ->and($project->totalInvoiced())->toBe(30000.0)
        ->and($project->totalCosts())->toBe(12000.0)
        ->and($project->result())->toBe(18000.0);

    $publicUrl = $this->actingAs($manager)->postJson(route('projects.public-gantt.generate', $project))
        ->assertOk()->json('url');
    auth()->logout();
    $this->get($publicUrl)->assertOk()->assertSee('Publiczny harmonogram projektu');
});

test('client invoices use issued group without supplier and display million amounts in one line', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $supplier = Company::create(['name' => 'Dostawca testowy', 'company_type' => 'supplier']);
    $client = Company::create(['name' => 'Klient wyszukiwarki', 'company_type' => 'client', 'status' => 'active']);
    $project = Project::create([
        'number' => 'PRJ/2026/INVOICE', 'name' => 'Faktury klienta', 'manager_id' => $admin->id,
        'company_id' => $client->id, 'status' => 'active', 'contract_value' => 5000000, 'created_by' => $admin->id,
    ]);
    $wrongGroup = $project->financeGroups()->create(['name' => 'Koszty obce']);

    $this->actingAs($admin)->post(route('projects.finances.store', $project), [
        'type' => 'invoice',
        'name' => 'Faktura końcowa',
        'document_number' => 'FV/2026/99',
        'supplier' => 'Nie powinien pozostać',
        'supplier_company_id' => $supplier->id,
        'finance_group_id' => $wrongGroup->id,
        'entry_date' => '2026-08-24',
        'amount' => 3120000,
        'status' => 'issued',
    ])->assertSessionHas('success');

    $invoice = $project->financialEntries()->firstOrFail();
    expect($invoice->supplier)->toBeNull()
        ->and($invoice->supplier_company_id)->toBeNull()
        ->and($invoice->financeGroup?->name)->toBe('Wystawione');

    $this->actingAs($admin)
        ->get(route('projects.show', ['project' => $project, 'tab' => 'finances']))
        ->assertOk()
        ->assertSee('3 120 000,00 zł')
        ->assertSee('finance-amount-column', false)
        ->assertSee('id="finance-live-search"', false)
        ->assertSee('data-finance-search="Klient wyszukiwarki', false)
        ->assertSee('data-finance-status-search="issued Wystawiona / zaksięgowana"', false)
        ->assertSee('data-finance-supplier-field', false)
        ->assertSee('Faktury dla klienta trafią automatycznie do grupy „Wystawione”.');
});

test('finance and requirement statuses are saved through background endpoints', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/ASYNC', 'name' => 'Statusy bez przeładowania', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 50000, 'created_by' => $manager->id,
    ]);
    $project->members()->attach($manager);
    $entry = $project->financialEntries()->create([
        'type' => 'cost', 'name' => 'Koszt planowany', 'entry_date' => '2026-08-10',
        'amount' => 1200, 'status' => 'planned', 'created_by' => $manager->id,
    ]);
    $requirement = $project->requirements()->create([
        'type' => 'material', 'name' => 'Pompa', 'quantity' => 1, 'unit' => 'szt.',
        'estimated_cost' => 3000, 'status' => 'requested', 'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->patchJson(route('projects.finances.status', [$project, $entry]), ['status' => 'paid'])
        ->assertOk()
        ->assertJsonPath('status', 'paid')
        ->assertJsonPath('summary.costs', 1200);
    $this->actingAs($manager)
        ->patchJson(route('projects.requirements.status', [$project, $requirement]), ['status' => 'ordered'])
        ->assertOk()
        ->assertJsonPath('status', 'ordered')
        ->assertJsonPath('committed_requirements', 3000);

    expect($entry->refresh()->status)->toBe('paid')
        ->and($requirement->refresh()->status)->toBe('ordered');

    $purchaseResponse = $this->actingAs($manager)
        ->patchJson(route('projects.requirements.status', [$project, $requirement]), ['status' => 'purchased'])
        ->assertOk()
        ->assertJsonPath('status', 'purchased')
        ->assertJsonPath('financial_entry.name', 'Pompa')
        ->assertJsonPath('financial_entry.amount', 3000)
        ->assertJsonPath('financial_entry.type', 'cost')
        ->assertJsonPath('financial_entry.status', 'issued')
        ->assertJsonPath('summary.costs', 4200);

    $copiedEntryId = $purchaseResponse->json('financial_entry.id');
    expect($requirement->refresh()->status)->toBe('purchased')
        ->and($requirement->financialEntry?->id)->toBe($copiedEntryId)
        ->and($project->requirements()->whereKey($requirement)->exists())->toBeTrue()
        ->and($project->financialEntries()->where('project_requirement_id', $requirement->id)->count())->toBe(1);

    $this->actingAs($manager)
        ->patchJson(route('projects.requirements.status', [$project, $requirement]), ['status' => 'purchased'])
        ->assertOk()
        ->assertJsonPath('financial_entry.id', $copiedEntryId);
    expect($project->financialEntries()->where('project_requirement_id', $requirement->id)->count())->toBe(1);

    $this->actingAs($manager)
        ->patchJson(route('projects.requirements.status', [$project, $requirement]), ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('status', 'in_progress')
        ->assertJsonPath('financial_entry', null)
        ->assertJsonPath('removed_financial_entry_id', $copiedEntryId)
        ->assertJsonPath('summary.costs', 1200);
    expect($requirement->refresh()->status)->toBe('in_progress')
        ->and($project->financialEntries()->where('project_requirement_id', $requirement->id)->count())->toBe(0)
        ->and($project->financialEntries()->whereKey($entry)->exists())->toBeTrue();

    $this->actingAs($manager)
        ->patchJson(route('projects.requirements.status', [$project, $requirement]), ['status' => 'planned'])
        ->assertOk()
        ->assertJsonPath('status', 'planned')
        ->assertJsonPath('planned_requirements', 3000)
        ->assertJsonPath('planned_requirement_entry.amount', 3000)
        ->assertJsonPath('summary.costs', 1200)
        ->assertJsonPath('summary.planned_costs', 3000);
    expect($requirement->refresh()->status)->toBe('planned')
        ->and($project->refresh()->plannedCosts())->toBe(3000.0)
        ->and($project->financialEntries()->where('project_requirement_id', $requirement->id)->count())->toBe(0);

    $this->actingAs($manager)
        ->get(route('projects.show', ['project' => $project, 'tab' => 'finances']))
        ->assertOk()
        ->assertSee('project-async-status', false)
        ->assertSee(route('projects.finances.status', [$project, $entry]), false)
        ->assertSee(route('projects.requirements.status', [$project, $requirement]), false)
        ->assertSee('if (!context || !range) return;', false);
});

test('orphaned material costs are ignored and deleting a purchased item removes its cost', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/ORPHAN', 'name' => 'Koszty materiałów', 'manager_id' => $manager->id,
        'status' => 'active', 'created_by' => $manager->id,
    ]);
    $requirement = $project->requirements()->create([
        'type' => 'material', 'name' => 'Pozycja testowa', 'quantity' => 1, 'unit' => 'szt.',
        'estimated_cost' => 299.40, 'status' => 'purchased', 'created_by' => $manager->id,
    ]);
    $linkedEntryId = $requirement->financialEntry()->value('id');

    $this->actingAs($manager)
        ->delete(route('projects.requirements.destroy', [$project, $requirement]))
        ->assertRedirect(route('projects.show', ['project' => $project, 'tab' => 'requirements']));

    expect(ProjectFinancialEntry::whereKey($linkedEntryId)->exists())->toBeFalse();

    ProjectFinancialEntry::create([
        'project_id' => $project->id,
        'project_requirement_id' => null,
        'type' => 'cost',
        'name' => 'Osierocony koszt materiału',
        'entry_date' => '2026-08-11',
        'amount' => 299.40,
        'status' => 'issued',
        'source' => 'requirement',
    ]);

    expect($project->refresh()->totalCosts())->toBe(0.0)
        ->and($project->financialEntries()->count())->toBe(0);
});

test('project manager bulk deletes selected gantt tasks only', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/BULK', 'name' => 'Usuwanie grupowe', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 10000, 'created_by' => $manager->id,
    ]);
    $project->members()->attach($manager);
    $first = $project->tasks()->create([
        'title' => 'Pierwsze', 'created_by' => $manager->id, 'status' => 'todo', 'priority' => 'medium',
        'progress' => 0, 'start_date' => '2026-08-01', 'due_date' => '2026-08-02', 'project_position' => 0,
    ]);
    $remaining = $project->tasks()->create([
        'title' => 'Pozostające', 'created_by' => $manager->id, 'depends_on_task_id' => $first->id,
        'status' => 'todo', 'priority' => 'medium', 'progress' => 0,
        'start_date' => '2026-08-03', 'due_date' => '2026-08-04', 'project_position' => 1,
    ]);
    $last = $project->tasks()->create([
        'title' => 'Ostatnie', 'created_by' => $manager->id, 'status' => 'todo', 'priority' => 'medium',
        'progress' => 0, 'start_date' => '2026-08-05', 'due_date' => '2026-08-06', 'project_position' => 2,
    ]);

    $this->actingAs($manager)->deleteJson(route('projects.tasks.bulk-destroy', $project), [
        'task_ids' => [$first->id, $last->id],
    ])->assertOk()->assertJsonPath('deleted', 2);

    expect($project->tasks()->pluck('id')->all())->toBe([$remaining->id])
        ->and($remaining->refresh()->depends_on_task_id)->toBeNull()
        ->and($remaining->project_position)->toBe(0);

    $otherProject = Project::create([
        'number' => 'PRJ/2026/OTHER', 'name' => 'Inny projekt', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 10000, 'created_by' => $manager->id,
    ]);
    $foreignTask = $otherProject->tasks()->create([
        'title' => 'Obce zadanie', 'created_by' => $manager->id, 'status' => 'todo', 'priority' => 'medium',
        'progress' => 0, 'start_date' => '2026-08-01', 'due_date' => '2026-08-02', 'project_position' => 0,
    ]);
    $this->actingAs($manager)->deleteJson(route('projects.tasks.bulk-destroy', $project), [
        'task_ids' => [$remaining->id, $foreignTask->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('task_ids');
    expect($project->tasks()->whereKey($remaining)->exists())->toBeTrue()
        ->and($otherProject->tasks()->whereKey($foreignTask)->exists())->toBeTrue();
});

test('project manager performs bulk actions on selected requirements only', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $member = User::factory()->create();
    $supplier = Company::create([
        'name' => 'Dostawca grupowy', 'nip' => '9876543210', 'company_type' => 'supplier', 'status' => 'active',
    ]);
    $project = Project::create([
        'number' => 'PRJ/2026/REQ-BULK', 'name' => 'Grupowe materiały', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 25000, 'created_by' => $manager->id,
    ]);
    $project->members()->attach($member);
    $requirements = collect(['Pompa', 'Zawór', 'Montaż'])->map(fn ($name, $index) => $project->requirements()->create([
        'type' => $index === 2 ? 'service' : 'material', 'name' => $name, 'quantity' => 1, 'unit' => 'szt.',
        'estimated_cost' => 1000 + ($index * 500), 'status' => 'requested', 'created_by' => $manager->id,
    ]));

    $this->actingAs($manager)->get(route('projects.show', ['project' => $project, 'tab' => 'requirements']))
        ->assertOk()
        ->assertSee('requirements-select-all', false)
        ->assertSee(route('projects.requirements.bulk', $project), false)
        ->assertSee('Zmień dostawcę');

    $selectedIds = [$requirements[0]->id, $requirements[1]->id];
    $this->actingAs($manager)->post(route('projects.requirements.bulk', $project), [
        'requirement_ids' => $selectedIds, 'action' => 'set_supplier', 'supplier_company_id' => $supplier->id,
    ])->assertRedirect(route('projects.show', ['project' => $project, 'tab' => 'requirements']));
    expect($requirements[0]->refresh()->supplier_company_id)->toBe($supplier->id)
        ->and($requirements[1]->refresh()->supplier_company_id)->toBe($supplier->id)
        ->and($requirements[2]->refresh()->supplier_company_id)->toBeNull();

    $this->actingAs($manager)->post(route('projects.requirements.bulk', $project), [
        'requirement_ids' => $selectedIds, 'action' => 'set_responsible', 'responsible_id' => $member->id,
    ])->assertSessionHas('success');
    $this->actingAs($manager)->post(route('projects.requirements.bulk', $project), [
        'requirement_ids' => $selectedIds, 'action' => 'set_needed_by', 'needed_by' => '2026-09-30',
    ])->assertSessionHas('success');
    $this->actingAs($manager)->post(route('projects.requirements.bulk', $project), [
        'requirement_ids' => $selectedIds, 'action' => 'set_technology', 'technology' => 'Zawory',
    ])->assertSessionHas('success');
    $this->actingAs($manager)->post(route('projects.requirements.bulk', $project), [
        'requirement_ids' => $selectedIds, 'action' => 'set_status', 'status' => 'purchased',
    ])->assertSessionHas('success');

    expect($requirements[0]->refresh()->responsible_id)->toBe($member->id)
        ->and($requirements[0]->technology)->toBe('Zawory')
        ->and($requirements[1]->refresh()->technology)->toBe('Zawory')
        ->and($requirements[0]->needed_by->format('Y-m-d'))->toBe('2026-09-30')
        ->and($requirements[0]->status)->toBe('purchased')
        ->and($requirements[1]->refresh()->status)->toBe('purchased')
        ->and($project->financialEntries()->whereIn('project_requirement_id', $selectedIds)->count())->toBe(2);

    $otherProject = Project::create([
        'number' => 'PRJ/2026/REQ-OTHER', 'name' => 'Obcy projekt', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 1000, 'created_by' => $manager->id,
    ]);
    $foreignRequirement = $otherProject->requirements()->create([
        'type' => 'material', 'name' => 'Obca pozycja', 'quantity' => 1, 'status' => 'requested', 'created_by' => $manager->id,
    ]);
    $this->actingAs($manager)->post(route('projects.requirements.bulk', $project), [
        'requirement_ids' => [$requirements[2]->id, $foreignRequirement->id], 'action' => 'set_type', 'type' => 'material',
    ])->assertSessionHasErrors('requirement_ids');
    expect($requirements[2]->refresh()->type)->toBe('service');

    $this->actingAs($manager)->post(route('projects.requirements.bulk', $project), [
        'requirement_ids' => $selectedIds, 'action' => 'delete',
    ])->assertSessionHas('success');
    expect($project->requirements()->pluck('id')->all())->toBe([$requirements[2]->id])
        ->and($project->financialEntries()->count())->toBe(0);
});

test('project manager creates and edits a milestone with a single deadline', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/MILESTONE', 'name' => 'Projekt z etapami', 'manager_id' => $manager->id,
        'status' => 'active', 'start_date' => '2026-08-01', 'end_date' => '2026-10-31',
        'contract_value' => 10000, 'created_by' => $manager->id,
    ]);
    $project->members()->attach($manager);

    $response = $this->actingAs($manager)->postJson(route('projects.tasks.store', $project), [
        'title' => 'Odbiór etapu pierwszego', 'is_milestone' => true,
        'start_date' => '2026-08-20', 'due_date' => '2026-08-25',
        'status' => 'todo', 'priority' => 'high', 'progress' => 0,
    ])->assertCreated()->assertJsonPath('kind', 'milestone')->assertJsonPath('is_milestone', true);

    $milestone = $project->tasks()->findOrFail($response->json('db_id'));
    expect($milestone->is_milestone)->toBeTrue()
        ->and($milestone->start_date->format('Y-m-d'))->toBe('2026-08-20')
        ->and($milestone->due_date->format('Y-m-d'))->toBe('2026-08-20');

    $this->actingAs($manager)->patchJson(route('projects.tasks.update', [$project, $milestone]), [
        'start_date' => '2026-08-22', 'due_date' => '2026-08-30', 'progress' => 100,
    ])->assertOk()->assertJsonPath('kind', 'milestone');
    expect($milestone->refresh()->due_date->format('Y-m-d'))->toBe('2026-08-22');
    $export = new ProjectGanttExport($project);
    expect($export->headings()[0])->toContain('Typ pozycji')
        ->and($export->array()[0][3])->toBe('Kamień milowy');

    $this->actingAs($manager)->get(route('projects.show', ['project' => $project, 'tab' => 'gantt']))
        ->assertOk()
        ->assertSee('Dodaj kamień milowy')
        ->assertSee('Koniec projektu')
        ->assertSee('projectEndDate', false);
});

test('project manager edits material details and quantities are displayed cleanly', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/MATERIAL', 'name' => 'Projekt materiałowy', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 10000, 'created_by' => $manager->id,
    ]);
    $project->members()->attach($manager);

    $this->actingAs($manager)->post(route('projects.requirements.store', $project), [
        'type' => 'material', 'name' => 'Pompa Grundfos', 'technology' => 'Pomiary', 'quantity' => 1, 'unit' => '2',
        'unit_cost' => 2500, 'status' => 'requested', 'responsible_id' => $manager->id,
    ])->assertRedirect(route('projects.show', ['project' => $project, 'tab' => 'requirements']))
        ->assertSessionHas('success');
    $requirement = $project->requirements()->firstOrFail();
    expect($requirement->unit)->toBe('szt.')
        ->and($requirement->formattedQuantity())->toBe('1')
        ->and($requirement->displayUnit())->toBe('szt.');

    $this->actingAs($manager)->patch(route('projects.requirements.update', [$project, $requirement]), [
        'type' => 'material', 'name' => 'Pompa Grundfos TP', 'technology' => 'Pomiary kotłowni', 'description' => 'Pompa obiegowa',
        'quantity' => 1.5, 'unit' => 'szt.', 'unit_cost' => 2000,
        'needed_by' => '2026-09-15', 'responsible_id' => $manager->id,
        'status' => 'ordered', 'supplier' => 'Dostawca testowy',
    ])->assertRedirect(route('projects.show', ['project' => $project, 'tab' => 'requirements']))
        ->assertSessionHas('success');

    expect($requirement->refresh()->name)->toBe('Pompa Grundfos TP')
        ->and($requirement->technology)->toBe('Pomiary kotłowni')
        ->and($requirement->formattedQuantity())->toBe('1,5')
        ->and((float) $requirement->estimated_cost)->toBe(3000.0)
        ->and($requirement->unitCost())->toBe(2000.0)
        ->and($requirement->status)->toBe('ordered')
        ->and($requirement->needed_by->format('Y-m-d'))->toBe('2026-09-15');

    $this->actingAs($manager)->get(route('projects.show', ['project' => $project, 'tab' => 'requirements']))
        ->assertOk()
        ->assertSee('1,5 szt.')
        ->assertSee('2 000,00 zł / szt.')
        ->assertSee('Łącznie: 3 000,00 zł')
        ->assertSee('Łączna wartość zamówienia')
        ->assertSee('Dostawca testowy · 1 poz.')
        ->assertSee('Cena za jednostkę')
        ->assertSee('Planowany budżet')
        ->assertSee('Planowane')
        ->assertSee('Technologia')
        ->assertSee('Pomiary kotłowni')
        ->assertSee('id="requirement-technology"', false)
        ->assertSee('.requirements-table.with-selection th:nth-child(4){width:68px!important}', false)
        ->assertSee('.requirements-table.with-selection th:nth-child(7){width:116px!important}', false)
        ->assertSee('.requirements-table.with-selection th:nth-child(9){width:66px!important}', false)
        ->assertSee('Edytuj materiał lub usługę')
        ->assertSee('Kopiuj jako nową pozycję')
        ->assertSee('openRequirementModal('.$requirement->id.',true)', false)
        ->assertSee("copy?'Kopiuj materiał lub usługę'", false)
        ->assertSee('Import z Excela')
        ->assertDontSee('Import z PDF')
        ->assertSee('requirements-live-search', false)
        ->assertSee('data-requirement-search=', false)
        ->assertSee('id="requirement-quantity" value="1"', false)
        ->assertSee('requirementQuantityUsesWholePieces', false)
        ->assertSee('modalBackdropGuard', false);
});

test('requirements excel import recognizes flexible columns matches relations and skips duplicates', function () {
    $manager = User::factory()->create(['name' => 'Kierownik Importu', 'email' => 'kierownik.importu@example.test']);
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/REQ-IMPORT', 'name' => 'Import materiałów', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 50000, 'created_by' => $manager->id,
    ]);
    $project->members()->attach($manager);
    $supplier = Company::create([
        'name' => 'Hurtownia Techniczna', 'nip' => '1234567890', 'company_type' => 'supplier', 'status' => 'active',
    ]);

    $this->actingAs($manager)
        ->get(route('projects.show', ['project' => $project, 'tab' => 'requirements']))
        ->assertOk()
        ->assertSee('Importuj materiały i usługi z Excela')
        ->assertSee('Pobierz wzór Excel');

    $this->actingAs($manager)
        ->get(route('projects.requirements.template', $project))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['Zestawienie zakupów dla projektu'],
        ['Wygenerowano', '10.08.2026'],
        [],
        ['Typ pozycji', 'Nazwa materiału / usługi', 'Technologia', 'Ilość zamawiana', 'J.m.', 'Cena', 'Termin dostawy', 'Stan', 'Kontrahent', 'NIP dostawcy', 'E-mail odpowiedzialnego', 'Uwagi'],
        ['Materiał', 'Pompa obiegowa', 'Pomiary', '2,5', 'szt.', '1 200,50 zł', '31.08.2026', 'Zamówione', 'Inna pisownia dostawcy', '123-456-78-90', $manager->email, 'Pompy do kotłowni'],
        ['Usługa', 'Montaż pomp', 'Pompy', 1, 'usł.', 850, '2026-09-02', 'W realizacji', 'Firma spoza CRM', null, 'brak@example.test', 'Montaż i rozruch'],
        ['Materiał', 'Filtr do wyceny', 'Uzdatnianie', 3, 'szt.', 100, '2026-09-10', 'Planowane', null, null, null, 'Budżet wstępny'],
        ['Materiał', null, 'Zawory', 5, 'szt.', 100, null, null, null, null, null, 'Wiersz bez nazwy'],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'project-requirements-');
    (new Xlsx($spreadsheet))->save($path);
    $upload = fn () => new UploadedFile($path, 'materialy.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $firstResponse = $this->actingAs($manager)->post(route('projects.requirements.import', $project), [
        'file' => $upload(),
    ])->assertRedirect(route('projects.show', ['project' => $project, 'tab' => 'requirements']))
        ->assertSessionHas('requirements_import_report');

    $report = $firstResponse->getSession()->get('requirements_import_report');
    $pump = $project->requirements()->where('name', 'Pompa obiegowa')->firstOrFail();
    $service = $project->requirements()->where('name', 'Montaż pomp')->firstOrFail();
    $planned = $project->requirements()->where('name', 'Filtr do wyceny')->firstOrFail();
    expect($report['inserted'])->toBe(3)
        ->and($report['duplicates'])->toBe(0)
        ->and($report['invalid'])->toBe(1)
        ->and($report['unassigned'])->toBe(1)
        ->and($report['unmatched_suppliers'])->toBe(1)
        ->and($pump->supplier_company_id)->toBe($supplier->id)
        ->and($pump->technology)->toBe('Pomiary')
        ->and((float) $pump->quantity)->toBe(2.5)
        ->and((float) $pump->estimated_cost)->toBe(3001.25)
        ->and($pump->status)->toBe('ordered')
        ->and($pump->responsible_id)->toBe($manager->id)
        ->and($service->type)->toBe('service')
        ->and($service->status)->toBe('in_progress')
        ->and($planned->status)->toBe('planned')
        ->and((float) $planned->estimated_cost)->toBe(300.0);

    $secondResponse = $this->actingAs($manager)->post(route('projects.requirements.import', $project), [
        'file' => $upload(),
    ])->assertSessionHas('requirements_import_report');
    $secondReport = $secondResponse->getSession()->get('requirements_import_report');
    expect($secondReport['inserted'])->toBe(0)
        ->and($secondReport['duplicates'])->toBe(3)
        ->and($project->requirements()->count())->toBe(3);

    @unlink($path);
});

test('project requirements can be exported for one supplier and selected statuses', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $client = Company::create(['name' => 'Klient eksportu', 'company_type' => 'client', 'status' => 'active']);
    $supplier = Company::create(['name' => 'Dostawca Eksportowy', 'company_type' => 'supplier', 'status' => 'active']);
    $project = Project::create([
        'number' => 'PRJ/2026/EXPORT', 'name' => 'Eksport materiałów', 'company_id' => $client->id,
        'manager_id' => $manager->id, 'status' => 'active', 'contract_value' => 10000, 'created_by' => $manager->id,
    ]);
    $project->requirements()->create([
        'type' => 'material', 'name' => 'Pompa do zapytania', 'quantity' => 2, 'unit' => 'szt.',
        'estimated_cost' => 2400, 'supplier_company_id' => $supplier->id, 'supplier' => $supplier->name,
        'status' => 'requested', 'created_by' => $manager->id,
    ]);
    $project->requirements()->create([
        'type' => 'material', 'name' => 'Zawór już zamówiony', 'quantity' => 1, 'unit' => 'szt.',
        'estimated_cost' => 900, 'supplier_company_id' => $supplier->id, 'supplier' => $supplier->name,
        'status' => 'ordered', 'created_by' => $manager->id,
    ]);
    $project->requirements()->create([
        'type' => 'service', 'name' => 'Usługa innego dostawcy', 'quantity' => 1, 'unit' => 'usł.',
        'estimated_cost' => 500, 'supplier' => 'Dostawca zewnętrzny', 'status' => 'requested', 'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->get(route('projects.show', ['project' => $project, 'tab' => 'requirements']))
        ->assertOk()
        ->assertSee('Generuj listę Excel')
        ->assertSee('Dołącz nazwę i numer projektu oraz nazwę klienta')
        ->assertSee(route('projects.requirements.export', $project), false)
        ->assertSee('Dostawca Eksportowy');

    Excel::fake();
    $this->actingAs($manager)->get(route('projects.requirements.export', [
        'project' => $project,
        'document_type' => 'inquiry',
        'supplier_filter' => 'company:'.$supplier->id,
        'statuses' => ['requested'],
    ]))->assertOk();

    $filename = implode('_', [
        'Zapytanie_ofertowe',
        Str::slug($supplier->name, '_'),
        now()->format('Y-m-d'),
    ]).'.xlsx';
    Excel::assertDownloaded($filename, function (ProjectRequirementsListExport $export): bool {
        $rows = $export->array();
        $values = collect($rows)->flatten()->filter()->all();
        $sheet = (new Spreadsheet)->getActiveSheet();
        $export->styles($sheet);

        return in_array('Pompa do zapytania', $values, true)
            && ! in_array('Klient eksportu', $values, true)
            && ! in_array('Eksport materiałów', $values, true)
            && in_array('Dane nieujawnione', $values, true)
            && ! in_array('Zawór już zamówiony', $values, true)
            && ! in_array('Usługa innego dostawcy', $values, true)
            && in_array('Cena oferowana netto', $rows[7], true)
            && str_contains((string) $rows[8][11], 'F8*K8')
            && $sheet->getFreezePane() === 'A8'
            && $sheet->getAutoFilter()->getRange() === 'A7:M8';
    });

    Excel::fake();
    $this->actingAs($manager)->get(route('projects.requirements.export', [
        'project' => $project,
        'document_type' => 'order',
        'supplier_filter' => 'company:'.$supplier->id,
        'all_statuses' => '1',
    ]))->assertOk();

    $orderFilename = implode('_', [
        'Zamowienie',
        Str::slug($supplier->name, '_'),
        now()->format('Y-m-d'),
    ]).'.xlsx';
    Excel::assertDownloaded($orderFilename, function (ProjectRequirementsListExport $export): bool {
        $rows = $export->array();

        return in_array('Cena jednostkowa netto', $rows[7], true)
            && (float) $rows[8][10] === 1200.0
            && (float) $rows[8][11] === 2400.0;
    });

    Excel::fake();
    $this->actingAs($manager)->get(route('projects.requirements.export', [
        'project' => $project, 'document_type' => 'inquiry', 'supplier_filter' => 'company:'.$supplier->id,
        'statuses' => ['requested'], 'include_project_context' => '1',
    ]))->assertOk();
    $contextFilename = implode('_', ['Zapytanie_ofertowe', Str::slug($project->number, '_'), Str::slug($supplier->name, '_'), now()->format('Y-m-d')]).'.xlsx';
    Excel::assertDownloaded($contextFilename, function (ProjectRequirementsListExport $export) use ($project, $client): bool {
        $values = collect($export->array())->flatten()->filter()->all();

        return in_array($project->number.' — '.$project->name, $values, true)
            && in_array($client->name, $values, true);
    });

    $this->actingAs($manager)->get(route('projects.requirements.export', [
        'project' => $project,
        'document_type' => 'order',
        'supplier_filter' => 'company:'.$supplier->id,
    ]))->assertRedirect()->assertSessionHasErrorsIn('requirementsExport', ['statuses']);
});

test('projects module can be disabled for an application deployment', function () {
    CompanySettings::create([
        'name' => 'Firma bez projektów', 'primary_color' => '#123456',
        'welcome_page_mode' => 'general', 'enabled_modules' => ['dashboard', 'crm'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('projects.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee('Projekty');
});

test('client card follows audit and project module visibility', function () {
    CompanySettings::create([
        'name' => 'Firma projektowa', 'primary_color' => '#123456',
        'welcome_page_mode' => 'general', 'enabled_modules' => ['dashboard', 'crm', 'projects'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $client = Company::create([
        'name' => 'Klient z projektem', 'company_type' => 'client', 'status' => 'active', 'show_in_dashboard' => true,
    ]);
    $otherClient = Company::create(['name' => 'Inny klient', 'company_type' => 'client', 'status' => 'active']);
    Project::create([
        'number' => 'PRJ/CARD/001', 'name' => 'Projekt widoczny na karcie', 'company_id' => $client->id,
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 42000, 'created_by' => $admin->id,
    ]);
    Project::create([
        'number' => 'PRJ/CARD/002', 'name' => 'Projekt innego klienta', 'company_id' => $otherClient->id,
        'manager_id' => $admin->id, 'status' => 'active', 'contract_value' => 12000, 'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->get(route('companies.show', $client))
        ->assertOk()
        ->assertDontSee('id="tab-btn-audits"', false)
        ->assertDontSee('id="tab-audits"', false)
        ->assertSee('id="tab-btn-projects"', false)
        ->assertSee('id="tab-projects"', false)
        ->assertSee('Projekt widoczny na karcie')
        ->assertDontSee('Projekt innego klienta');

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-dashboard-metric="audits"', false)
        ->assertSee('data-dashboard-metric="projects"', false)
        ->assertSee('1 projekt')
        ->assertSee('Aktywne projekty');
});

test('gantt excel can be exported and imported into another project with dependencies', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $source = Project::create([
        'number' => 'PRJ/2026/SOURCE', 'name' => 'Projekt źródłowy', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 10000, 'created_by' => $manager->id,
    ]);
    $firstSourceTask = $source->tasks()->create([
        'title' => 'Przygotowanie', 'assigned_to' => $manager->id, 'created_by' => $manager->id,
        'status' => 'in_progress', 'priority' => 'high', 'progress' => 25,
        'start_date' => '2026-01-10', 'due_date' => '2026-01-12', 'project_position' => 1,
    ]);
    $source->tasks()->create([
        'title' => 'Montaż', 'assigned_to' => $manager->id, 'created_by' => $manager->id,
        'depends_on_task_id' => $firstSourceTask->id, 'status' => 'todo', 'priority' => 'medium', 'progress' => 0,
        'start_date' => '2026-01-13', 'due_date' => '2026-01-15', 'project_position' => 2,
    ]);

    $this->actingAs($manager)->get(route('projects.show', ['project' => $source, 'tab' => 'gantt']))
        ->assertOk()
        ->assertSee('Eksport Excel')
        ->assertSee('Import Excel');

    $this->actingAs($manager)
        ->get(route('projects.gantt.export', $source))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $target = Project::create([
        'number' => 'PRJ/2026/TARGET', 'name' => 'Projekt docelowy', 'manager_id' => $manager->id,
        'status' => 'planned', 'start_date' => '2026-09-01', 'contract_value' => 20000, 'created_by' => $manager->id,
    ]);

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['ID zadania', 'Kolejność', 'Nazwa', 'Data rozpoczęcia', 'Data zakończenia', 'Postęp (%)', 'Status', 'Priorytet', 'Zależne od ID', 'Zależne od', 'E-mail osoby odpowiedzialnej', 'Osoba odpowiedzialna', 'Opis'],
        ['T001', 1, 'Przygotowanie', '2026-01-10', '2026-01-12', 25, 'W trakcie', 'Wysoki', null, null, $manager->email, $manager->name, 'Opis pierwszego zadania'],
        ['T002', 2, 'Montaż', '2026-01-13', '2026-01-15', 0, 'Do zrobienia', 'Średni', 'T001', 'Przygotowanie', $manager->email, $manager->name, null],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'project-gantt-');
    (new Xlsx($spreadsheet))->save($path);
    $upload = fn () => new UploadedFile($path, 'harmonogram.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->actingAs($manager)->post(route('projects.gantt.import', $target), [
        'file' => $upload(),
        'new_start_date' => '2026-09-01',
    ])->assertRedirect(route('projects.show', ['project' => $target, 'tab' => 'gantt']))
        ->assertSessionHas('gantt_import_report');

    $report = $response->getSession()->get('gantt_import_report');
    $importedFirst = $target->tasks()->where('title', 'Przygotowanie')->firstOrFail();
    $importedSecond = $target->tasks()->where('title', 'Montaż')->firstOrFail();
    expect($report['inserted'])->toBe(2)
        ->and($report['duplicates'])->toBe(0)
        ->and($importedFirst->start_date->format('Y-m-d'))->toBe('2026-09-01')
        ->and($importedFirst->due_date->format('Y-m-d'))->toBe('2026-09-03')
        ->and($importedFirst->assigned_to)->toBe($manager->id)
        ->and($importedSecond->start_date->format('Y-m-d'))->toBe('2026-09-04')
        ->and($importedSecond->depends_on_task_id)->toBe($importedFirst->id);

    $secondResponse = $this->actingAs($manager)->post(route('projects.gantt.import', $target), [
        'file' => $upload(),
        'new_start_date' => '2026-09-01',
    ])->assertSessionHas('gantt_import_report');
    $secondReport = $secondResponse->getSession()->get('gantt_import_report');
    expect($secondReport['inserted'])->toBe(0)
        ->and($secondReport['duplicates'])->toBe(2)
        ->and($target->tasks()->count())->toBe(2);

    @unlink($path);
});

test('gantt import accepts the previous export format with dependency names', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/LEGACY', 'name' => 'Import starego formatu', 'manager_id' => $manager->id,
        'status' => 'planned', 'contract_value' => 10000, 'created_by' => $manager->id,
    ]);

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['Rodzaj', 'Nazwa', 'Data rozpoczęcia', 'Data zakończenia', 'Czas trwania (dni)', 'Postęp (%)', 'Zależne od', 'Osoba odpowiedzialna', 'Opis'],
        ['Zadanie', 'Etap pierwszy', '2026-10-01', '2026-10-02', 2, 0, null, $manager->name, null],
        ['Zadanie', 'Etap drugi', '2026-10-03', '2026-10-05', 3, 0, 'Etap pierwszy', $manager->name, null],
        ['Kamień milowy', 'Odbiór etapu', '2026-10-06', '2026-10-08', 1, 0, 'Etap drugi', $manager->name, null],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'project-gantt-legacy-');
    (new Xlsx($spreadsheet))->save($path);

    $this->actingAs($manager)->post(route('projects.gantt.import', $project), [
        'file' => new UploadedFile($path, 'stary-harmonogram.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertSessionHas('gantt_import_report');

    $first = $project->tasks()->where('title', 'Etap pierwszy')->firstOrFail();
    $second = $project->tasks()->where('title', 'Etap drugi')->firstOrFail();
    $milestone = $project->tasks()->where('title', 'Odbiór etapu')->firstOrFail();
    expect($second->depends_on_task_id)->toBe($first->id)
        ->and($first->assigned_to)->toBe($manager->id)
        ->and($milestone->is_milestone)->toBeTrue()
        ->and($milestone->due_date->format('Y-m-d'))->toBe('2026-10-06')
        ->and($milestone->depends_on_task_id)->toBe($second->id)
        ->and($project->tasks()->count())->toBe(3);

    @unlink($path);
});

test('gantt import recognizes dependency column from external schedule and repairs existing tasks', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/EXTERNAL', 'name' => 'Import zewnętrznego harmonogramu', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 10000, 'created_by' => $manager->id,
    ]);
    $first = $project->tasks()->create([
        'title' => 'Podpisanie umowy', 'created_by' => $manager->id, 'status' => 'done', 'priority' => 'medium',
        'progress' => 100, 'start_date' => '2026-06-28', 'due_date' => '2026-07-09', 'project_position' => 0,
    ]);
    $dependent = $project->tasks()->create([
        'title' => 'Czyszczenie wymiennika', 'created_by' => $manager->id, 'status' => 'todo', 'priority' => 'medium',
        'progress' => 0, 'start_date' => '2026-07-23', 'due_date' => '2026-07-31', 'project_position' => 1,
    ]);

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['Nazwa zadania', 'Data rozpoczęcia', 'Data zakończenia', 'Czas trwania (dni)', 'Postęp (%)', 'Zależność od'],
        ['Podpisanie umowy', '28.06.2026', '9.07.2026', 12, 100, 'Brak'],
        ['Czyszczenie wymiennika', '23.07.2026', '31.07.2026', 9, 0, 'Podpisanie umowy'],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'project-gantt-external-');
    (new Xlsx($spreadsheet))->save($path);

    $response = $this->actingAs($manager)->post(route('projects.gantt.import', $project), [
        'file' => new UploadedFile($path, 'harmonogram-zewnetrzny.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertSessionHas('gantt_import_report');

    $report = $response->getSession()->get('gantt_import_report');
    expect($report['inserted'])->toBe(0)
        ->and($report['duplicates'])->toBe(2)
        ->and($dependent->refresh()->depends_on_task_id)->toBe($first->id)
        ->and($project->tasks()->count())->toBe(2);

    @unlink($path);
});

test('excel finance import recognizes polish columns and never imports a duplicate twice', function () {
    $manager = User::factory()->create();
    $manager->assignRole('admin');
    $project = Project::create([
        'number' => 'PRJ/2026/FIN', 'name' => 'Import finansów', 'manager_id' => $manager->id,
        'status' => 'active', 'contract_value' => 150000, 'created_by' => $manager->id,
    ]);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['Raport kosztów projektu', 'sierpień 2026'],
        [],
        ['Data księgowania', 'Podmiot', 'Nr dokumentu', 'Kwota netto', 'Opis', 'Status', 'Termin płatności'],
        ['05.08.2026', 'Hurtownia A', 'FV/10/08/2026', 1234.56, 'Materiały elektryczne', 'Opłacona', '20.08.2026'],
        ['06.08.2026', 'Hurtownia A', 'FV/10/08/2026', 1234.56, 'Powtórzony dokument', 'Zaksięgowana', '20.08.2026'],
        ['07.08.2026', 'Usługi B', 'FV/11/08/2026', '2 500,40 zł', 'Montaż', 'Zaksięgowana', null],
        ['błędna data', 'Usługi C', 'FV/12/08/2026', 100, 'Błędny wiersz', null, null],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'project-finance-');
    (new Xlsx($spreadsheet))->save($path);

    $upload = fn () => new UploadedFile($path, 'koszty.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    $firstResponse = $this->actingAs($manager)->post(route('projects.finances.import', $project), [
        'file' => $upload(), 'type' => 'cost', 'new_group_name' => 'Koszty sierpień',
    ])->assertRedirect(route('projects.show', ['project' => $project, 'tab' => 'finances']))
        ->assertSessionHas('finance_import_report');

    $firstReport = $firstResponse->getSession()->get('finance_import_report');
    expect($firstReport['inserted'])->toBe(2)
        ->and($firstReport['duplicates'])->toBe(1)
        ->and($firstReport['invalid'])->toBe(1)
        ->and($project->financialEntries()->count())->toBe(2)
        ->and($project->financialEntries()->sum('amount'))->toBe(3734.96)
        ->and($project->financeGroups()->where('name', 'Koszty sierpień')->exists())->toBeTrue();

    $this->actingAs($manager)->post(route('projects.finances.bulk', $project), [
        'entry_ids' => $project->financialEntries()->pluck('id')->all(), 'action' => 'paid',
    ])->assertSessionHas('success');
    expect($project->financialEntries()->where('status', 'paid')->count())->toBe(2);

    $secondResponse = $this->actingAs($manager)->post(route('projects.finances.import', $project), [
        'file' => $upload(), 'type' => 'cost', 'finance_group_id' => $project->financeGroups()->first()->id,
    ])->assertSessionHas('finance_import_report');
    $secondReport = $secondResponse->getSession()->get('finance_import_report');
    expect($secondReport['inserted'])->toBe(0)
        ->and($secondReport['duplicates'])->toBe(3)
        ->and($project->financialEntries()->count())->toBe(2);

    @unlink($path);
});
