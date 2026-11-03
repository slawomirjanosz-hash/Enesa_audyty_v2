<?php

use App\Mail\TaskAssigned;
use App\Models\BoardTask;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\CrmOpportunity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function calendarCrmUser(): User
{
    $role = Role::findOrCreate('calendar_crm_employee');
    foreach (['calendar.view', 'crm.tasks.own.manage'] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission));
    }
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('new calendar task is one CRM record with collaboration completion archive and restore', function () {
    $owner = calendarCrmUser();
    $participant = calendarCrmUser();
    $data = ['title' => 'Wspólne zadanie z kalendarza', 'description' => 'Opis zadania', 'calendar_kind' => 'task', 'due_date' => '2026-11-12', 'scheduled_time' => '14:30', 'assigned_to' => $participant->id, 'priority' => 'high'];
    $this->actingAs($owner)->post(route('calendar.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('calendar.index', ['month' => '2026-11']));
    $task = Task::sole();
    expect((int) $task->assigned_to)->toBe($owner->id);
    expect((int) $task->created_by)->toBe($owner->id);
    expect($task->scheduled_time)->toStartWith('14:30');
    expect($task->priority)->toBe('high');
    $this->assertDatabaseCount('board_tasks', 0);
    $calendar = $this->get(route('calendar.index', ['month' => '2026-11']))->assertOk()->assertSee($task->title)->assertSee('14:30');
    $crm = $this->get(route('crm.index', ['tab' => 'tasks']))->assertOk()->assertSee($task->title)->assertSee('14:30');
    $this->get(route('board.mine'))->assertOk()->assertSee('crm-task-'.$task->id)->assertSee('14:30');
    if (getenv('CALENDAR_CRM_QA')) {
        file_put_contents(base_path('tmp/calendar-crm-task.html'), $calendar->getContent());
        file_put_contents(base_path('tmp/calendar-crm-list.html'), $crm->getContent());
    }
    $this->putJson(route('board.participants', ['crm', $task->id]), ['participants' => [$participant->id], 'revision' => $task->board_revision])->assertOk();
    $this->patchJson(route('board.crm.status', $task), ['status' => 'done', 'revision' => $task->fresh()->board_revision])->assertOk()->assertJsonPath('status', 'in_progress');
    $this->patchJson(route('board.archive', ['crm', $task->id]), ['restore' => false, 'revision' => $task->fresh()->board_revision])->assertUnprocessable();
    $this->actingAs($participant)->get(route('calendar.index', ['month' => '2026-11']))->assertOk()->assertSee($task->title);
    $this->patchJson(route('board.crm.status', $task), ['status' => 'done', 'revision' => $task->fresh()->board_revision])->assertOk()->assertJsonPath('status', 'done');
    $this->patchJson(route('board.archive', ['crm', $task->id]), ['restore' => false, 'revision' => $task->fresh()->board_revision])->assertOk();
    $this->get(route('calendar.index', ['month' => '2026-11']))->assertOk()->assertDontSee($task->title);
    $this->get(route('crm.index', ['tab' => 'tasks']))->assertOk()->assertDontSee($task->title);
    $archived = Task::withTrashed()->findOrFail($task->id);
    $this->patchJson(route('board.archive', ['crm', $task->id]), ['restore' => true, 'revision' => $archived->board_revision])->assertOk();
    $this->get(route('calendar.index', ['month' => '2026-11']))->assertOk()->assertSee($task->title);
    expect(Task::count())->toBe(1);
});

test('calendar uses CRM creation linking validation assignment notification and edits preserve task time', function () {
    Mail::fake();
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $worker = calendarCrmUser();
    $company = Company::create(['name' => 'Firma kalendarza', 'status' => 'active']);
    $lead = CrmOpportunity::create(['company_id' => $company->id, 'title' => 'Lead kalendarza', 'stage' => 'contact']);
    $data = ['title' => 'Zadanie do leada', 'calendar_kind' => 'task', 'due_date' => '2026-11-12', 'scheduled_time' => '09:15', 'priority' => 'medium', 'status' => 'todo', 'company_id' => $company->id, 'crm_opportunity_id' => $lead->id, 'assigned_to' => $worker->id];
    $this->actingAs($admin)->post(route('calendar.store'), array_replace($data, ['company_id' => null]))->assertSessionHasErrors('crm_opportunity_id');
    $this->post(route('calendar.store'), $data)->assertSessionHasNoErrors();
    $task = Task::sole();
    expect((int) $task->crm_opportunity_id)->toBe($lead->id);
    Mail::assertSent(TaskAssigned::class, fn ($mail) => $mail->hasTo($worker->email));
    $this->actingAs($worker)->put(route('crm.tasks.update', $task), ['title' => 'Zmieniono w CRM', 'status' => 'in_progress', 'priority' => 'high', 'due_date' => '2026-11-13', 'scheduled_time' => '10:45', 'assigned_to' => $worker->id, 'company_id' => $company->id, 'crm_opportunity_id' => $lead->id])->assertSessionHasNoErrors();
    $this->get(route('calendar.index', ['month' => '2026-11']))->assertOk()->assertSee('Zmieniono w CRM')->assertSee('10:45');
    expect(Task::count())->toBe(1);
});

test('new calendar tasks require CRM permissions and enabled CRM while meetings and old entries remain separate', function () {
    $calendarOnly = User::factory()->create();
    $calendarOnly->assignRole(Role::findOrCreate('calendar_only_reader'));
    $calendarOnly->givePermissionTo(Permission::findOrCreate('calendar.view'));
    $data = ['title' => 'Nowe zadanie', 'calendar_kind' => 'task', 'due_date' => '2026-11-12', 'scheduled_time' => '14:30'];
    $this->actingAs($calendarOnly)->post(route('calendar.store'), $data)->assertForbidden();
    $this->get(route('calendar.index'))->assertOk()->assertDontSee('data-calendar-new="task" data-date=', false);
    $this->post(route('calendar.store'), array_replace($data, ['calendar_kind' => 'meeting']))->assertRedirect();
    $legacy = new BoardTask;
    $legacy->forceFill(['title' => 'Wcześniejsze zadanie', 'calendar_kind' => 'task', 'due_date' => '2026-11-12', 'assigned_to' => $calendarOnly->id, 'created_by' => $calendarOnly->id]);
    $legacy->save();
    $this->get(route('calendar.index', ['month' => '2026-11']))->assertOk()->assertSee('Wcześniejsze zadanie');
    expect(Task::count())->toBe(0);
    expect(BoardTask::count())->toBe(2);
    CompanySettings::create(['name' => 'Bez CRM', 'enabled_modules' => ['calendar']]);
    $owner = calendarCrmUser();
    $this->actingAs($owner)->post(route('calendar.store'), $data)->assertForbidden();
});
