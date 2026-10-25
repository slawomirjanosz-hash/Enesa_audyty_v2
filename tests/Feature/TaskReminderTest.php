<?php

use App\Mail\TaskOverdue;
use App\Models\Audit;
use App\Models\BoardTask;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskReminderSelection;
use Carbon\Carbon;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['mail.default' => 'smtp', 'mail.from.address' => 'reminders@test.invalid']);
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-14 08:01', 'Europe/Warsaw'));
});

function reminderUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin'));

    return $user;
}

function reminderTask(User $user, array $extra = []): Task
{
    return Task::create($extra + ['title' => 'Zaległe zadanie', 'assigned_to' => $user->id, 'status' => 'todo', 'due_date' => '2026-10-13']);
}

function reminderBoardFixture(): array
{
    $user = reminderUser();
    $company = Company::create(['name' => 'Test reminders']);
    $project = Project::create(['company_id' => $company->id, 'number' => 'REM/1', 'name' => 'Project', 'manager_id' => $user->id, 'status' => 'active']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'REM/1', 'title' => 'Audit', 'manager_id' => $user->id, 'status' => 'in_progress']);

    return [$user, $project, $audit];
}

test('dry run has no sends or database claims even without configured mail', function () {
    config(['mail.default' => 'log']);
    reminderTask(reminderUser());
    $this->artisan('tasks:send-overdue-reminders --dry-run')->expectsOutputToContain('1 zadań, 1 odbiorców')->assertSuccessful();
    $this->artisan('tasks:send-overdue-reminders')->assertFailed();
    Mail::assertNothingSent();
    $this->assertDatabaseCount('task_reminder_deliveries', 0);
});

test('one daily email per recipient survives repeated commands and next day sends again', function () {
    $user = reminderUser();
    reminderTask($user);
    reminderTask($user, ['title' => 'Drugi wpis']);
    $this->artisan('tasks:send-overdue-reminders --scheduled')->assertSuccessful();
    $this->artisan('tasks:send-overdue-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(TaskOverdue::class, 1);
    $this->assertDatabaseHas('task_reminder_deliveries', ['user_id' => $user->id, 'status' => 'accepted', 'task_count' => 2]);
    $this->travelTo(Carbon::parse('2026-10-15 08:01', 'Europe/Warsaw'));
    $this->artisan('tasks:send-overdue-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(TaskOverdue::class, 2);
});

test('scheduled worker respects Polish time including daylight saving', function (string $utc, bool $send) {
    config(['app.timezone' => 'UTC']);
    $this->travelTo(Carbon::parse($utc, 'UTC'));
    reminderTask(reminderUser(), ['due_date' => '2026-01-01']);
    $this->artisan('tasks:send-overdue-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(TaskOverdue::class, $send ? 1 : 0);
})->with([['2026-07-01 06:00', true], ['2026-12-01 07:00', true], ['2026-12-01 06:59', false], ['2026-07-01 07:00', false], ['2026-10-17 06:00', false], ['2026-10-18 06:00', false], ['2026-10-19 06:00', true]]);

test('only overdue active assigned tasks are selected, not today or archived tasks', function () {
    $user = reminderUser();
    $wanted = reminderTask($user);
    reminderTask($user, ['due_date' => '2026-10-14']);
    reminderTask($user, ['due_date' => null]);
    reminderTask($user, ['status' => 'done']);
    reminderTask($user, ['assigned_to' => null]);
    reminderTask($user)->delete();
    $inactive = reminderUser();
    $inactive->update(['is_active' => false]);
    reminderTask($inactive);
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_admin'));
    reminderTask($client);
    expect(app(TaskReminderSelection::class)->groups()->flatten()->pluck('id')->all())->toBe([$wanted->id]);
});

test('weekend skips both scheduler and direct command without claiming a delivery', function () {
    reminderTask(reminderUser());
    $this->travelTo(Carbon::parse('2026-10-17 08:00', 'Europe/Warsaw'));
    $this->artisan('tasks:send-overdue-reminders')->assertSuccessful();
    $this->artisan('tasks:send-overdue-reminders --scheduled')->assertSuccessful();
    Mail::assertNothingSent();
    $this->assertDatabaseCount('task_reminder_deliveries', 0);
});

test('tablica tasks are included with correct links, archives and completed owners are excluded', function () {
    [$user, $project, $audit] = reminderBoardFixture();
    $card = BoardTask::create(['title' => 'Karta projektu', 'project_id' => $project->id, 'assigned_to' => $user->id, 'status' => 'in_progress', 'due_date' => '2026-10-13']);
    reminderTask($user);
    $this->artisan('tasks:send-overdue-reminders')->assertSuccessful();
    Mail::assertSent(TaskOverdue::class, fn ($mail) => $mail->tasks->count() === 2 && str_contains(html_entity_decode($mail->render()), route('projects.show', [$project, 'tab' => 'tasks'])));
    $card->delete();
    expect(app(TaskReminderSelection::class)->groups()->flatten()->count())->toBe(1);
    $card->restore();
    $project->update(['status' => 'completed']);
    expect(app(TaskReminderSelection::class)->groups()->flatten()->count())->toBe(1);
});

test('board access revocation and disabled modules suppress reminders', function () {
    [$user, $project] = reminderBoardFixture();
    $card = BoardTask::create(['title' => 'Karta', 'project_id' => $project->id, 'assigned_to' => $user->id, 'status' => 'todo', 'due_date' => '2026-10-13']);
    expect(app(TaskReminderSelection::class)->groups()->flatten()->count())->toBe(1);
    $user->syncRoles(Role::findOrCreate('employee'));
    expect(app(TaskReminderSelection::class)->groups()->flatten()->count())->toBe(0);
    $user->assignRole('superadmin');
    $settings = CompanySettings::firstOrCreate([], ['name' => 'Test']);
    $settings->update(['enabled_modules' => []]);
    expect(app(TaskReminderSelection::class)->groups()->flatten()->count())->toBe(0);
});

test('provider failure is recorded, does not abort other recipients and is not blindly retried', function () {
    $first = reminderUser();
    $second = reminderUser();
    reminderTask($first);
    reminderTask($second);
    Mail::swap(Mockery::mock(Mailer::class, function ($mock) {
        $mock->shouldReceive('to')->twice()->andReturnSelf();
        $mock->shouldReceive('send')->once()->ordered()->andThrow(new RuntimeException('Provider rejected message'));
        $mock->shouldReceive('send')->once()->ordered()->andReturnNull();
    }));
    $this->artisan('tasks:send-overdue-reminders')->assertFailed();
    expect(DB::table('task_reminder_deliveries')->where('status', 'failed')->count())->toBe(1)
        ->and(DB::table('task_reminder_deliveries')->where('status', 'accepted')->count())->toBe(1);
    $this->artisan('tasks:send-overdue-reminders')->assertSuccessful();
});

test('resend requires a key and a non-placeholder sender', function () {
    config(['mail.default' => 'resend', 'services.resend.key' => null]);
    reminderTask(reminderUser());
    $this->artisan('tasks:send-overdue-reminders')->assertFailed();
    config(['services.resend.key' => 'test-placeholder', 'mail.from.address' => 'hello@example.com']);
    $this->artisan('tasks:send-overdue-reminders')->assertFailed();
    Mail::assertNothingSent();
    $this->assertDatabaseCount('task_reminder_deliveries', 0);
});

test('one digest combines CRM schedule and board tasks for each responsible person and participant', function () {
    [$owner, $project, $audit] = reminderBoardFixture();
    $participant = reminderUser();
    $crm = reminderTask($owner, ['title' => 'CRM zbiorczy']);
    $schedule = reminderTask($owner, ['title' => 'Harmonogram zbiorczy', 'project_id' => $project->id]);
    $board = BoardTask::create(['title' => 'Tablica zbiorcza', 'audit_id' => $audit->id, 'assigned_to' => $owner->id, 'status' => 'in_progress', 'due_date' => '2026-10-13']);
    foreach ([$crm, $schedule, $board] as $task) {
        $task->participants()->sync([$participant->id, $owner->id]);
    }
    $this->artisan('tasks:send-overdue-reminders --scheduled')->assertSuccessful();
    $this->artisan('tasks:send-overdue-reminders --scheduled')->assertSuccessful();
    Mail::assertSentCount(2);
    foreach ([$owner, $participant] as $person) {
        Mail::assertSent(TaskOverdue::class, fn ($mail) => $mail->hasTo($person->email) && $mail->tasks->count() === 3);
    }
    $participant->update(['is_active' => false]);
    expect(app(TaskReminderSelection::class)->groups()->keys()->all())->toBe([$owner->id]);
});

test('task cards show the recorded creator including an archived creator', function () {
    [$owner, $project] = reminderBoardFixture();
    $creator = reminderUser();
    $creator->update(['name' => 'Autor zadania testowego']);
    $board = BoardTask::create(['title' => 'Zlecona karta', 'project_id' => $project->id, 'assigned_to' => $owner->id, 'created_by' => $creator->id, 'status' => 'todo']);
    reminderTask($owner, ['created_by' => $creator->id]);
    $creator->delete();
    $this->actingAs($owner)->get(route('board.mine'))->assertOk()->assertSee('Wyznaczył(a):')->assertSee('Autor zadania testowego');
    expect($board->fresh()->createdBy->id)->toBe($creator->id);
});
