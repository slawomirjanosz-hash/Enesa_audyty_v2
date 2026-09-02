<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('admin views own and team tasks in the monthly calendar', function () {
    $admin = User::factory()->create(['name' => 'Anna Administrator']);
    $admin->assignRole('admin');
    $employee = User::factory()->create(['name' => 'Piotr Pracownik']);
    $employee->assignRole('auditor');
    $company = Company::create(['name' => 'Klient kalendarza', 'company_type' => 'client', 'status' => 'active']);
    $project = Project::create([
        'number' => 'PRJ-CAL-1',
        'name' => 'Projekt kalendarza',
        'company_id' => $company->id,
        'manager_id' => $admin->id,
        'created_by' => $admin->id,
        'status' => 'active',
    ]);

    Task::create([
        'title' => 'Moje zadanie CRM',
        'company_id' => $company->id,
        'assigned_to' => $admin->id,
        'created_by' => $admin->id,
        'status' => 'todo',
        'priority' => 'high',
        'due_date' => '2026-08-20',
    ]);
    Task::create([
        'title' => 'Zadanie projektowe pracownika',
        'company_id' => $company->id,
        'project_id' => $project->id,
        'assigned_to' => $employee->id,
        'created_by' => $admin->id,
        'status' => 'in_progress',
        'priority' => 'medium',
        'due_date' => '2026-08-21',
    ]);

    $this->actingAs($admin)
        ->get(route('calendar.index', ['month' => '2026-08', 'scope' => 'mine']))
        ->assertOk()
        ->assertSee('Moje zadanie CRM')
        ->assertDontSee('Zadanie projektowe pracownika')
        ->assertSee('Cały zespół')
        ->assertSee('Piotr Pracownik')
        ->assertSee('Numer tygodnia')
        ->assertSee('Tydz.<br>31', false);

    $this->actingAs($admin)
        ->get(route('calendar.index', ['month' => '2026-08', 'scope' => 'team']))
        ->assertOk()
        ->assertSee('Moje zadanie CRM')
        ->assertSee('Zadanie projektowe pracownika')
        ->assertSee('Projekt kalendarza');
});

test('staff member without team permission sees only own calendar', function () {
    $ownCalendarRole = Role::findOrCreate('pracownik_kalendarza');
    $ownCalendarRole->givePermissionTo(Permission::findOrCreate('calendar.view'));
    $employee = User::factory()->create(['name' => 'Jan Własny']);
    $employee->assignRole($ownCalendarRole);
    $otherEmployee = User::factory()->create(['name' => 'Ewa Inna']);
    $otherEmployee->assignRole('auditor');

    Task::create([
        'title' => 'Widoczne własne zadanie',
        'assigned_to' => $employee->id,
        'created_by' => $employee->id,
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => '2026-08-22',
    ]);
    Task::create([
        'title' => 'Ukryte zadanie innej osoby',
        'assigned_to' => $otherEmployee->id,
        'created_by' => $otherEmployee->id,
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => '2026-08-22',
    ]);

    $this->actingAs($employee)
        ->get(route('calendar.index', ['month' => '2026-08', 'scope' => 'team', 'user_id' => $otherEmployee->id]))
        ->assertOk()
        ->assertSee('Widoczne własne zadanie')
        ->assertDontSee('Ukryte zadanie innej osoby')
        ->assertDontSee('Cały zespół')
        ->assertDontSee('Ewa Inna');
});

test('calendar is available from navigation and floating shortcut only with permission', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Kalendarz')
        ->assertSee('class="calendar-quick-link"', false)
        ->assertSee('href="'.route('calendar.index').'"', false)
        ->assertDontSee('data-tooltip="Kalendarz"', false);

    $response->assertSeeInOrder(['Ustawienia', 'Kalendarz']);

    $role = Role::findOrCreate('bez_kalendarza');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get(route('calendar.index'))->assertForbidden();
});

test('disabled calendar module disappears from navigation and rejects direct access', function () {
    CompanySettings::create([
        'name' => 'Firma bez kalendarza',
        'enabled_modules' => ['dashboard', 'crm', 'projects'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('class="calendar-quick-link"', false)
        ->assertDontSee('href="'.route('calendar.index').'"', false);

    $this->actingAs($admin)->get(route('calendar.index'))->assertForbidden();
});
