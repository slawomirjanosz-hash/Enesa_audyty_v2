<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
    $client = Company::create(['name' => 'Klient z projektem', 'company_type' => 'client', 'status' => 'active']);
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
