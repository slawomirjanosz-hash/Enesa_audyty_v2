<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Offer;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['superadmin', 'admin'] as $role) {
        Role::findOrCreate($role);
    }
});

test('superadmin configures modules for the whole application in company settings', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $this->actingAs($superadmin)
        ->post(route('settings.company.update'), [
            'name' => 'Firma instalatorska',
            'primary_color' => '#1A4D3A',
            'welcome_page_mode' => 'general',
            'enabled_modules' => ['dashboard', 'crm', 'offers'],
        ])
        ->assertRedirect(route('settings.company'));

    expect(CompanySettings::first()->enabled_modules)->toBe(['dashboard', 'crm', 'offers'])
        ->and(CompanySettings::first()->short_name)->toBe('FI');
});

test('disabled application modules disappear from navigation and reject direct access', function () {
    CompanySettings::create([
        'name' => 'Firma instalatorska',
        'primary_color' => '#1A4D3A',
        'welcome_page_mode' => 'general',
        'enabled_modules' => ['dashboard', 'crm', 'offers'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Widok kart klientów')
        ->assertDontSee('Widok audytora')
        ->assertSee('CRM')
        ->assertSee('Strefa Ofert')
        ->assertDontSee('System Audytów')
        ->assertDontSee('Strefa klienta');

    $this->actingAs($admin)->get(route('audit-types.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('client-zone.index'))->assertForbidden();
});

test('disabled audits module disappears from CRM and audits tab cannot be forced by URL', function () {
    CompanySettings::create([
        'name' => 'Firma instalatorska',
        'enabled_modules' => ['dashboard', 'crm', 'offers'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Company::create([
        'name' => 'Klient CRM',
        'company_type' => 'client',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('crm.index', ['tab' => 'companies']))
        ->assertOk()
        ->assertDontSee(route('crm.index', ['tab' => 'audits']), false)
        ->assertDontSee('audits-tbody', false);

    $this->actingAs($admin)
        ->get(route('crm.index', ['tab' => 'audits']))
        ->assertOk()
        ->assertSee('Aktywne firmy')
        ->assertSee('Klient CRM')
        ->assertDontSee('audits-tbody', false);
});

test('offer numbers use the editable company short name', function () {
    CompanySettings::create([
        'name' => 'Prinż Cieszyn',
        'short_name' => 'PRINZ',
    ]);

    $firstNumber = Offer::generateNumber();
    Offer::create([
        'offer_number' => $firstNumber,
        'offer_full_number' => $firstNumber,
        'status' => 'w_toku',
    ]);

    expect($firstNumber)->toBe('OF_PRINZ_'.now()->format('Ymd').'_001')
        ->and(Offer::generateNumber())->toBe('OF_PRINZ_'.now()->format('Ymd').'_002');
});

test('staff login redirects to the first enabled module', function () {
    CompanySettings::create([
        'name' => 'Firma bez dashboardu',
        'primary_color' => '#1A4D3A',
        'welcome_page_mode' => 'general',
        'enabled_modules' => ['crm', 'offers'],
    ]);
    $admin = User::factory()->create(['password' => 'password']);
    $admin->assignRole('admin');

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('crm.index'));
});

test('dashboard shows company creation date in table and last change on card', function () {
    CompanySettings::create([
        'name' => 'Firma testowa',
        'enabled_modules' => ['dashboard', 'crm'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create([
        'name' => 'Klient datowany',
        'company_type' => 'client',
        'status' => 'active',
        'show_in_dashboard' => true,
    ]);
    $company->forceFill([
        'created_at' => '2026-07-01 08:15:00',
        'updated_at' => '2026-08-12 16:40:00',
    ])->saveQuietly();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Data dodania')
        ->assertSee('01.07.2026 08:15')
        ->assertSee('Ostatnia zmiana: 12.08.2026 16:40')
        ->assertSee('data-sort-value="2026-07-01 08:15:00"', false);
});

test('dashboard shows only current user open task count', function () {
    CompanySettings::create([
        'name' => 'Firma zadaniowa',
        'enabled_modules' => ['dashboard', 'crm'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $otherUser = User::factory()->create();
    $otherUser->assignRole('admin');

    Task::create(['title' => 'Moje zadanie pierwsze', 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'todo', 'priority' => 'medium']);
    Task::create(['title' => 'Moje zadanie drugie', 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'in_progress', 'priority' => 'high']);
    Task::create(['title' => 'Moje zakończone', 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'done', 'priority' => 'low']);
    Task::create(['title' => 'Zadanie innej osoby', 'assigned_to' => $otherUser->id, 'created_by' => $admin->id, 'status' => 'todo', 'priority' => 'medium']);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-dashboard-stat="my-open-tasks"', false)
        ->assertSeeInOrder(['>2</div>', 'Moje zadania do zrobienia'], false);
});

test('dashboard highlights overdue and newly assigned tasks', function () {
    CompanySettings::create([
        'name' => 'Firma alertów',
        'enabled_modules' => ['dashboard', 'crm'],
    ]);
    $employee = User::factory()->create(['dashboard_tasks_seen_id' => 0]);
    $employee->assignRole('admin');
    $manager = User::factory()->create();
    $manager->assignRole('admin');

    Task::create([
        'title' => 'Nowe zadanie po terminie',
        'assigned_to' => $employee->id,
        'created_by' => $manager->id,
        'status' => 'todo',
        'priority' => 'high',
        'due_date' => now()->subHour(),
    ]);

    $this->actingAs($employee->refresh())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-task-alert="red"', false)
        ->assertSee('data-task-alert="green"', false)
        ->assertSee('Nowe zadania: 1');

    $this->actingAs($employee->refresh())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-task-alert="red"', false)
        ->assertDontSee('data-task-alert="green"', false)
        ->assertDontSee('Nowe zadania: 1');
});

test('dashboard shows another users overdue tasks without flashing red', function () {
    CompanySettings::create([
        'name' => 'Firma cudzych zadań',
        'enabled_modules' => ['dashboard', 'crm'],
    ]);
    $viewer = User::factory()->create();
    $viewer->assignRole('admin');
    $assignee = User::factory()->create();
    $assignee->assignRole('admin');

    Task::create([
        'title' => 'Zaległe zadanie innego użytkownika',
        'assigned_to' => $assignee->id,
        'created_by' => $viewer->id,
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => now()->subDay(),
    ]);

    $this->actingAs($viewer)->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['>1</div>', 'Zadania po terminie'], false)
        ->assertSee('Zaległe zadania innych użytkowników')
        ->assertDontSee('data-task-alert="red"', false);
});

test('dashboard does not reveal other users overdue tasks without team calendar access', function () {
    CompanySettings::create([
        'name' => 'Firma prywatnych zadań',
        'enabled_modules' => ['dashboard', 'crm', 'calendar'],
    ]);
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('pracownik_bez_zadan_zespolu');
    $role->givePermissionTo([
        Permission::findOrCreate('dashboard.view'),
        Permission::findOrCreate('crm.view'),
    ]);
    $viewer->assignRole($role);
    $assignee = User::factory()->create();
    $assignee->assignRole('admin');

    Task::create([
        'title' => 'Ukryte zaległe zadanie innej osoby',
        'assigned_to' => $assignee->id,
        'created_by' => $assignee->id,
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => now()->subDay(),
    ]);

    $this->actingAs($viewer)->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['>0</div>', 'Zadania po terminie'], false)
        ->assertSee('Wszystko na czas')
        ->assertDontSee('Zaległe zadania innych użytkowników')
        ->assertDontSee('data-task-alert="red"', false);
});
