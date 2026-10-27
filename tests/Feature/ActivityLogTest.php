<?php

use App\Models\ActivityLog;
use App\Models\CompanySettings;
use App\Models\HrVehicle;
use App\Models\Task;
use App\Models\User;
use App\Support\ActivityLogPresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        ->assertSee('<details class="log-details">', false)
        ->assertDontSee('<details class="log-details" open>', false);
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

test('activity timestamps no longer create business changes and historical noise is hidden', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
    expect(ActivityLog::where('auditable_type', User::class)->where('action', 'updated')->count())->toBe(0);
    $noise = ActivityLog::create(['action' => 'updated', 'auditable_type' => User::class, 'subject_label' => 'Szum aktywności', 'changes' => ['security_activity_at' => ['old' => null, 'new' => now()->toDateTimeString()]]]);
    $mixed = ActivityLog::create(['action' => 'updated', 'auditable_type' => User::class, 'subject_label' => 'Rzeczywista zmiana użytkownika', 'changes' => ['security_activity_at' => ['old' => null, 'new' => now()->toDateTimeString()], 'name' => ['old' => 'Adam', 'new' => 'Jan']]]);
    $this->actingAs($admin)->get(route('activity-log.index'))->assertOk()->assertDontSee('Szum aktywności')->assertDontSee('security_activity_at')->assertSee('Rzeczywista zmiana użytkownika')->assertSee('Pokaż zmienione pola (1)');
    expect($noise->fresh()->is_noise)->toBeTrue()->and($mixed->fresh()->changes)->toHaveKey('security_activity_at');
});

test('search covers actors old and new Polish values field labels and all pages without changing access', function () {
    $admin = User::factory()->create(['name' => 'Żaneta Kontroler']);
    $admin->assignRole('admin');
    foreach (range(1, 53) as $index) {
        ActivityLog::create(['user_id' => $admin->id, 'action' => 'updated', 'auditable_type' => HrVehicle::class, 'subject_label' => 'Samochód SCI123', 'changes' => ['responsible_person' => ['old' => 'Paweł Żółty', 'new' => 'Łukasz Nowy']]]);
    }
    $this->actingAs($admin);
    foreach (['ŻANETA', 'PAWEŁ', 'ŁUKASZ', 'SCI123', 'samochód', 'Osoba odpowiedzialna', $admin->email] as $term) {
        $this->get(route('activity-log.index', ['search' => $term]))->assertOk()->assertSee('Wyświetlanie 1–50 z 53 wyników');
    }
    $this->get(route('activity-log.index', ['search' => 'Żaneta SCI123', 'page' => 2]))->assertOk()->assertSee('Wyświetlanie 51–53 z 53 wyników');
    $this->get(route('activity-log.index', ['search' => 'brak-dopasowania']))->assertDontSee('Samochód SCI123');
    $unauthorized = User::factory()->create();
    $unauthorized->assignRole('auditor');
    $this->actingAs($unauthorized)->withHeader('X-Activity-Results', '1')->get(route('activity-log.index', ['search' => 'SCI123']))->assertForbidden();
});

test('car reassignment records who changed it and preserves readable before and after user names', function () {
    CompanySettings::create(['name' => 'Test HR', 'enabled_modules' => ['hr']]);
    $admin = User::factory()->create(['name' => 'Autor przypisania']);
    $admin->assignRole('admin');
    $before = User::factory()->create(['name' => 'Poprzedni kierowca']);
    $after = User::factory()->create(['name' => 'Nowy kierowca']);
    $before->assignRole('admin');
    $after->assignRole('admin');
    $car = new HrVehicle(['type' => 'company', 'name' => 'Samochód audytowy', 'registration_number' => 'SCI-ZMIANA', 'responsible_person' => $before->name]);
    $car->responsible_user_id = $before->id;
    $car->save();
    $this->actingAs($admin)->put(route('hr.vehicles.update', $car), ['name' => $car->name, 'registration_number' => $car->registration_number, 'responsible_user_id' => $after->id])->assertSessionHasNoErrors();
    $log = ActivityLog::where('auditable_type', HrVehicle::class)->where('action', 'updated')->latest('id')->firstOrFail();
    expect($log->user_id)->toBe($admin->id)->and($log->changes['responsible_user_id']['old'])->toBe($before->id)->and($log->changes['responsible_user_id']['new'])->toBe($after->id);
    $after->update(['name' => 'Późniejsza nazwa użytkownika']);
    $this->get(route('activity-log.index', ['search' => 'SCI-ZMIANA']))->assertOk()->assertSee('Autor przypisania')->assertSee('Zmiana przypisania samochodu')->assertSee('Poprzedni kierowca')->assertSee('Nowy kierowca')->assertDontSee('Późniejsza nazwa użytkownika');
});

test('search respects login tabs and month filters and never indexes protected values', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    ActivityLog::forceCreate(['user_id' => $admin->id, 'action' => 'login', 'subject_label' => 'Logowanie unikalne', 'created_at' => '2026-09-10 08:00:00']);
    $log = ActivityLog::forceCreate(['user_id' => $admin->id, 'action' => 'updated', 'subject_label' => 'Zmiana unikalna', 'created_at' => '2026-10-10 08:00:00', 'changes' => ['name' => ['old' => 'Przed', 'new' => 'Po'], 'password' => ['old' => null, 'new' => 'sekret-haslo-123']]]);
    expect($log->search_text)->not->toContain('sekret-haslo-123');
    $this->actingAs($admin)->get(route('activity-log.index', ['search' => 'unikaln']))->assertSee('Zmiana unikalna')->assertDontSee('Logowanie unikalne');
    $this->get(route('activity-log.index', ['tab' => 'logins', 'search' => 'unikaln', 'month' => '2026-09']))->assertSee('Logowanie unikalne')->assertDontSee('Zmiana unikalna');
    $this->get(route('activity-log.index', ['search' => 'unikaln', 'month' => '2026-09']))->assertDontSee('Zmiana unikalna');
    $this->withHeader('X-Activity-Results', '1')->get(route('activity-log.index', ['search' => 'Zmiana']))->assertOk()->assertDontSee('<html', false)->assertSee('Zmiana unikalna');
});

test('backfill makes older records searchable and suppresses noise without rewriting original changes', function () {
    $migration = require database_path('migrations/2026_10_26_090000_index_activity_log_search_and_noise.php');
    $migration->down();
    Schema::table('activity_logs', fn ($table) => $table->text('search_text')->nullable());
    $original = ['security_activity_at' => ['old' => null, 'new' => '2026-10-26 08:00:00']];
    $id = DB::table('activity_logs')->insertGetId(['action' => 'updated', 'auditable_type' => User::class, 'subject_label' => 'Dawna aktywność', 'changes' => json_encode($original)]);
    DB::table('activity_logs')->insert(['action' => 'updated', 'auditable_type' => HrVehicle::class, 'subject_label' => 'Stary samochód', 'changes' => json_encode(['responsible_person' => ['old' => 'Żółty', 'new' => 'Łukasz']])]);
    $legacy = ['responsible_person' => 'Paweł Historyczny', 'revision' => 12, 'legacy_list' => ['Pierwszy', 'Drugi']];
    $legacyId = DB::table('activity_logs')->insertGetId(['action' => 'updated', 'auditable_type' => HrVehicle::class, 'subject_label' => 'Starszy format', 'changes' => json_encode($legacy)]);
    $longId = DB::table('activity_logs')->insertGetId(['action' => 'updated', 'subject_label' => 'Duży historyczny wpis', 'changes' => json_encode(['description' => ['old' => null, 'new' => str_repeat('Historyczna treść ', 5000)]])]);
    $migration->up();
    $migration->up();
    expect(ActivityLog::findOrFail($id)->changes)->toBe($original)->and(ActivityLog::findOrFail($id)->is_noise)->toBeTrue();
    expect(ActivityLog::where('subject_label', 'Stary samochód')->firstOrFail()->search_text)->toContain('żółty', 'łukasz');
    expect(ActivityLog::findOrFail($legacyId)->changes)->toBe($legacy)->and(ActivityLog::findOrFail($legacyId)->search_text)->toContain('paweł historyczny', 'brak zapisanej poprzedniej wartości', 'pierwszy');
    expect(strlen(ActivityLog::findOrFail($longId)->search_text))->toBeGreaterThan(65535);
});

test('search treats wildcard symbols as literal text', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    ActivityLog::create(['action' => 'updated', 'subject_label' => 'Dosłowny numer FV_10%']);
    ActivityLog::create(['action' => 'updated', 'subject_label' => 'Inny numer FVA100']);
    $this->actingAs($admin)->get(route('activity-log.index', ['search' => 'FV_10%']))->assertOk()->assertSee('Dosłowny numer')->assertDontSee('Inny numer');
});
