<?php

use App\Models\ActivityLog;
use App\Models\CompanySettings;
use App\Models\Task;
use App\Models\User;
use App\Support\ActivityLogPresentation;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['superadmin', 'admin', 'auditor'] as $role) {
        Role::findOrCreate($role);
    }
});

test('changes made by a user are recorded with old and new values', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('crm.tasks.store'), [
        'title' => 'Kontrolowane zadanie', 'assigned_to' => $admin->id,
        'status' => 'todo', 'priority' => 'medium', 'due_date' => '2026-08-28',
    ])->assertRedirect();
    $task = Task::where('title', 'Kontrolowane zadanie')->firstOrFail();

    $this->actingAs($admin)->put(route('crm.tasks.update', $task), [
        'title' => 'Kontrolowane zadanie', 'assigned_to' => $admin->id,
        'status' => 'in_progress', 'priority' => 'high', 'due_date' => '2026-09-02',
    ])->assertRedirect();

    $log = ActivityLog::where('auditable_type', Task::class)
        ->where('auditable_id', $task->id)
        ->where('action', 'updated')
        ->latest('id')
        ->firstOrFail();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->changes['status'])->toBe(['old' => 'todo', 'new' => 'in_progress'])
        ->and($log->changes['priority'])->toBe(['old' => 'medium', 'new' => 'high'])
        ->and($log->changes['due_date'])->toBe(['old' => '2026-08-28', 'new' => '2026-09-02'])
        ->and($log->route_name)->toBe('crm.tasks.update');

    $this->actingAs($admin)->get(route('activity-log.index'))
        ->assertOk()
        ->assertSee('Lista zmian')
        ->assertSee('Kontrolowane zadanie')
        ->assertSee('Pokaż zmienione pola');
});

test('successful login and logout are visible on the login tab', function () {
    $admin = User::factory()->create([
        'email' => 'logowania@example.com',
        'password' => Hash::make('tajne-haslo'),
    ]);
    $admin->assignRole('admin');

    $this->post(route('login'), ['email' => $admin->email, 'password' => 'tajne-haslo'])->assertRedirect();
    $this->post(route('logout'))->assertRedirect('/');

    expect(ActivityLog::where('user_id', $admin->id)->where('action', 'login')->exists())->toBeTrue()
        ->and(ActivityLog::where('user_id', $admin->id)->where('action', 'logout')->exists())->toBeTrue();

    $this->actingAs($admin)->get(route('activity-log.index', ['tab' => 'logins']))
        ->assertOk()
        ->assertSee('Logowanie')
        ->assertSee('Wylogowanie')
        ->assertSee('logowania@example.com');
});

test('login history uses compact polish pagination', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach (range(1, 53) as $number) {
        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'subject_label' => 'Logowanie '.$number,
        ]);
    }

    $this->actingAs($admin)->get(route('activity-log.index', ['tab' => 'logins']))
        ->assertOk()
        ->assertSee('Wyświetlanie 1–50 z 53 wyników')
        ->assertSee('Następna →')
        ->assertDontSee('pagination.previous')
        ->assertDontSee('<svg', false);
});

test('activity log is available only to administrators and explicitly selected roles', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $this->actingAs($auditor)->get(route('activity-log.index'))->assertForbidden();

    $selectedRole = Role::findOrCreate('kontrola_zmian');
    $selectedRole->givePermissionTo(Permission::findOrCreate('activity_log.view'));
    $selected = User::factory()->create();
    $selected->assignRole($selectedRole);

    $this->actingAs($selected)->get(route('activity-log.index'))
        ->assertOk()
        ->assertSee('Cała historia');
});

test('historical HR rate changes show readable labels and exact old and new amounts', function () {
    $admin = User::factory()->create(['name' => 'Kamil Testowy']);
    $admin->assignRole('admin');
    $log = ActivityLog::create([
        'user_id' => $admin->id, 'action' => 'updated',
        'auditable_type' => CompanySettings::class,
        'auditable_id' => 1, 'subject_label' => 'PRINŻ CIESZYN',
        'route_name' => 'hr.settings.update',
        'changes' => ['hr_km_rate' => ['old' => '1.1500', 'new' => '1.2345'], 'hr_diet_rate' => ['old' => 45, 'new' => 50]],
    ]);
    $before = $log->fresh()->getAttributes();
    $this->actingAs($admin)->get(route('activity-log.index'))->assertOk()
        ->assertSee('Ustawienia firmy')->assertSee('Stawka za kilometr delegacji')
        ->assertSee('1,15 zł/km')->assertSee('1,2345 zł/km')
        ->assertSee('Dieta za dobę delegacji')->assertSee('45,00 zł')->assertSee('50,00 zł')
        ->assertSee('Przed zmianą')->assertSee('Po zmianie')
        ->assertSee('<details class="log-details" open>', false);
    expect($log->fresh()->getAttributes())->toBe($before);
});

test('change presentation preserves zero and unknown values and escapes HTML', function () {
    $presentation = new ActivityLogPresentation;
    expect($presentation->value('hr_km_rate', 0))->toBe('0,00 zł/km')
        ->and($presentation->value('hr_km_rate', null))->toBe('Brak wartości')
        ->and($presentation->value('gps', false))->toBe('Nie')
        ->and($presentation->value('status', 'in_progress'))->toBe('W trakcie')
        ->and($presentation->value('unknown', 'wartość'))->toBe('wartość')
        ->and($presentation->value('due_date', '2026-10-16'))->toBe('16.10.2026')
        ->and($presentation->value('enabled_modules', '["hr","crm"]'))->toBe("HR\nCRM i firmy")
        ->and($presentation->value('api_token', 'secret'))->toBe('[dane chronione]');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    ActivityLog::create(['action' => 'updated', 'changes' => ['description' => ['old' => null, 'new' => '<script>alert(1)</script>']]]);
    $this->actingAs($admin)->get(route('activity-log.index'))->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});
