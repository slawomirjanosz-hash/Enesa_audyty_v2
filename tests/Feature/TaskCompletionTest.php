<?php

use App\Models\Audit;
use App\Models\BoardTask;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('completion migration reopens only active shared completed tasks without inventing confirmations', function () {
    [$admin, $worker, $project] = completionFixture();
    $migration = require database_path('migrations/2026_10_23_120000_add_task_completion_confirmations.php');
    $migration->down();
    foreach (['crm', 'board'] as $kind) {
        $values = ['title' => 'Istniejące wspólne', 'status' => 'done', 'assigned_to' => $admin->id];
        $task = $kind === 'crm' ? Task::create($values) : BoardTask::create($values + ['project_id' => $project->id]);
        $task->participants()->attach($worker);
        $archived = $kind === 'crm' ? Task::create($values) : BoardTask::create($values + ['project_id' => $project->id]);
        $archived->participants()->attach($worker);
        $archived->delete();
        $rows[] = [$task, $archived];
    }
    $stage = Task::create(['title' => 'Etap zakończony', 'project_id' => $project->id, 'status' => 'done']);
    $stage->participants()->attach([$admin->id, $worker->id]);
    $migration->up();
    foreach ($rows as [$task, $archived]) {
        expect($task->fresh()->status)->toBe('in_progress');
        expect($task->fresh()->completion_confirmations)->toBeNull();
        expect($archived->fresh()->status)->toBe('done');
    }
    expect($stage->fresh()->status)->toBe('done');
});

function completionFixture(): array
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('superadmin'));
    $worker = User::factory()->create(['name' => 'Wykonawca testowy', 'is_active' => true]);
    $worker->assignRole(Role::findOrCreate('employee'));
    $worker->givePermissionTo(['projects.view', 'projects.schedule.view', 'audits.view', 'crm.tasks.own.manage']);
    $company = Company::create(['name' => 'Potwierdzenia', 'company_type' => 'client', 'status' => 'active']);
    $project = Project::create(['company_id' => $company->id, 'number' => 'P/C', 'name' => 'Potwierdzenia', 'manager_id' => $admin->id, 'status' => 'active']);
    $project->members()->attach($worker);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'A/C', 'title' => 'Potwierdzenia audytu', 'manager_id' => $admin->id, 'status' => 'draft']);
    $audit->members()->attach($worker);

    return [$admin, $worker, $project, $audit];
}

test('each executor confirms independently before completion and archiving across all modules', function (string $kind) {
    [$admin, $worker, $project, $audit] = completionFixture();
    $task = match ($kind) {
        'crm' => Task::create(['title' => 'Wspólne CRM', 'company_id' => $project->company_id, 'assigned_to' => $admin->id, 'status' => 'todo']),
        'project' => BoardTask::create(['title' => 'Wspólne projektowe', 'project_id' => $project->id, 'assigned_to' => $admin->id, 'status' => 'todo']),
        'audit' => BoardTask::create(['title' => 'Wspólne audytowe', 'audit_id' => $audit->id, 'assigned_to' => $admin->id, 'status' => 'todo']),
    };
    $task->participants()->attach($worker);
    $type = $kind === 'crm' ? 'crm' : 'board';
    $status = route($kind === 'crm' ? 'board.crm.status' : 'board.status', $task);
    $revision = $kind === 'crm' ? 'board_revision' : 'revision';
    $archive = route('board.archive', [$type, $task->id]);
    $this->actingAs($admin)->patchJson($archive, ['revision' => 1, 'restore' => false])->assertStatus(422);
    $this->actingAs($worker)->patchJson($status, ['revision' => 1, 'status' => 'done'])->assertOk()->assertJsonPath('status', 'in_progress');
    expect(array_keys($task->fresh()->completion_confirmations))->toBe([$worker->id]);
    $this->get(route('board.mine'))->assertOk()->assertSee('Potwierdzenia: 1/2')->assertSee('Potwierdzono wykonanie');
    $this->patchJson($status, ['revision' => 1, 'status' => 'done'])->assertStatus(409);
    $this->patchJson($status, ['revision' => 2, 'status' => 'done'])->assertOk()->assertJsonPath('status', 'in_progress');
    $this->patchJson($archive, ['revision' => $task->fresh()->$revision, 'restore' => false])->assertStatus(422);
    $this->actingAs($admin)->patchJson($status, ['revision' => $task->fresh()->$revision, 'status' => 'done'])->assertOk()->assertJsonPath('status', 'done');
    expect(count($task->fresh()->completion_confirmations))->toBe(2);
    $this->patchJson($archive, ['revision' => $task->fresh()->$revision, 'restore' => false])->assertOk();
    $this->get(route('board.mine'))->assertDontSee($task->title);
    $this->patchJson($status, ['revision' => $task->fresh()->$revision, 'status' => 'todo'])->assertNotFound();
    $this->patchJson($archive, ['revision' => $task->fresh()->$revision, 'restore' => true])->assertOk();
    $this->patchJson($status, ['revision' => $task->fresh()->$revision, 'status' => 'todo'])->assertOk();
    expect($task->fresh()->completion_confirmations)->toBe([]);
})->with(['crm', 'project', 'audit']);

test('CRM forms and board editor cannot bypass individual confirmations and a new participant reopens completion', function () {
    [$admin, $worker, $project] = completionFixture();
    $crm = Task::create(['title' => 'CRM potwierdzane', 'status' => 'todo', 'assigned_to' => $admin->id]);
    $crm->participants()->attach($worker);
    $this->actingAs($worker)->patchJson(route('crm.tasks.status', $crm), ['status' => 'done'])->assertOk()->assertJsonPath('status', 'in_progress');
    $this->delete(route('crm.tasks.destroy', $crm))->assertStatus(422);
    $this->actingAs($admin)->put(route('crm.tasks.update', $crm), ['title' => $crm->title, 'status' => 'done', 'priority' => 'medium', 'assigned_to' => $admin->id])->assertSessionHasNoErrors();
    expect($crm->fresh()->status)->toBe('done');
    $card = BoardTask::create(['title' => 'Karta potwierdzana', 'project_id' => $project->id, 'assigned_to' => $admin->id, 'status' => 'done']);
    $this->putJson(route('board.participants', ['board', $card->id]), ['participants' => [$worker->id], 'revision' => 1])->assertOk();
    expect($card->fresh()->status)->toBe('in_progress');
    $this->putJson(route('board.update', $card), ['title' => $card->title, 'assigned_to' => $admin->id, 'status' => 'done', 'revision' => 2])->assertOk();
    expect($card->fresh()->status)->toBe('in_progress');
    $this->actingAs($worker)->patchJson(route('board.status', $card), ['status' => 'done', 'revision' => 3])->assertOk()->assertJsonPath('status', 'done');
});

test('administrators cannot confirm for others and only administrators can permanently delete accessible tasks including archives', function () {
    [$admin, $worker, $project] = completionFixture();
    $other = User::factory()->create();
    $other->assignRole(Role::findOrCreate('employee'));
    $crm = Task::create(['title' => 'Cudze potwierdzenie', 'assigned_to' => $worker->id, 'status' => 'todo']);
    $crm->participants()->attach($other);
    $this->actingAs($admin)->patchJson(route('board.crm.status', $crm), ['status' => 'done', 'revision' => 1])->assertForbidden();
    foreach (['board' => BoardTask::create(['title' => 'Usuwane', 'project_id' => $project->id, 'assigned_to' => $worker->id]), 'crm' => $crm] as $kind => $task) {
        $url = route('board.permanent-delete', [$kind, $task->id]);
        $this->actingAs($worker)->deleteJson($url, ['revision' => 1])->assertForbidden();
        $this->actingAs($admin)->deleteJson($url, ['revision' => 99])->assertStatus(409);
        $task->delete();
        $this->deleteJson($url, ['revision' => 1])->assertOk();
        $this->assertDatabaseMissing($task->getTable(), ['id' => $task->id]);
    }
    $active = Task::create(['title' => 'Usuwane aktywne', 'assigned_to' => $admin->id, 'status' => 'todo']);
    $this->deleteJson(route('board.permanent-delete', ['crm', $active->id]), ['revision' => 1])->assertOk();
    $stage = Task::create(['title' => 'Etap harmonogramu', 'project_id' => $project->id]);
    $this->deleteJson(route('board.permanent-delete', ['crm', $stage->id]), ['revision' => 1])->assertNotFound();
    CompanySettings::query()->delete();
    CompanySettings::create(['name' => 'Wyłączone CRM', 'enabled_modules' => ['projects']]);
    $hidden = Task::create(['title' => 'Wyłączony moduł', 'status' => 'todo']);
    $this->deleteJson(route('board.permanent-delete', ['crm', $hidden->id]), ['revision' => 1])->assertNotFound();
});

test('task deletion preserves project access scopes even for the admin role and blocks client roles', function () {
    [$admin, $worker, $project] = completionFixture();
    $card = BoardTask::create(['title' => 'Poza zakresem administratora', 'project_id' => $project->id, 'assigned_to' => $admin->id]);
    $limited = User::factory()->create(['individual_permissions' => true]);
    $limited->assignRole(Role::findOrCreate('admin'));
    $limited->givePermissionTo(['projects.view', 'projects.schedule.view']);
    $this->actingAs($limited)->deleteJson(route('board.permanent-delete', ['board', $card->id]), ['revision' => 1])->assertNotFound();
    $admin->assignRole(Role::findOrCreate('client_admin'));
    $this->actingAs($admin)->deleteJson(route('board.permanent-delete', ['board', $card->id]), ['revision' => 1])->assertForbidden();
    $this->assertDatabaseHas('board_tasks', ['id' => $card->id]);
});
