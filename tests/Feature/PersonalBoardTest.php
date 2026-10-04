<?php

use App\Models\BoardTask;
use App\Models\CompanySettings;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Role;

function boardWorker($project): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('employee'));
    $user->givePermissionTo(['projects.view', 'projects.schedule.view', 'crm.tasks.own.manage']);
    $project->members()->attach($user);

    return $user;
}

test('personal board combines CRM and cards with multi source and user filters without granting edits', function () {
    [$admin, $project, $audit] = boardFixture();
    $worker = boardWorker($project);
    $card = BoardTask::create(['project_id' => $project->id, 'title' => 'Projekt własny', 'assigned_to' => $worker->id]);
    $crm = Task::create(['company_id' => $project->company_id, 'title' => 'CRM własny', 'assigned_to' => $worker->id, 'status' => 'todo']);
    $foreign = Task::create(['company_id' => $project->company_id, 'title' => 'CRM zespołu', 'assigned_to' => $admin->id, 'status' => 'todo']);
    $this->actingAs($worker)->get(route('board.mine'))->assertOk()->assertSee('Projekt własny')->assertSee('CRM własny')->assertDontSee('CRM zespołu')->assertDontSee('name="users[]"', false);
    $this->get(route('board.mine', ['users' => [$admin->id]]))->assertForbidden();
    $worker->givePermissionTo('board.team.view');
    $this->get(route('board.mine'))->assertSee('name="users[]"', false)->assertDontSee('CRM zespołu');
    $this->get(route('board.mine', ['users' => [$admin->id, $worker->id], 'projects' => ['']]))->assertSee('CRM zespołu')->assertSee('CRM własny')->assertDontSee('Projekt własny');
    $this->patchJson(route('board.crm.status', $foreign), ['status' => 'done', 'revision' => 1])->assertForbidden();
    $this->patchJson(route('board.crm.status', $crm), ['status' => 'in_progress', 'revision' => 1])->assertOk()->assertJsonPath('revision', 2);
    expect($crm->fresh()->status)->toBe('in_progress');
    $this->patchJson(route('board.crm.status', $crm), ['status' => 'done', 'revision' => 1])->assertStatus(409);
    $this->get(route('board.mine', ['users' => ['99999']]))->assertForbidden();
    if (getenv('PERSONAL_BOARD_QA')) {
        file_put_contents(base_path('tmp/personal-board.html'), $this->get(route('board.mine'))->getContent());
    }
});

test('participants join existing CRM and project tasks and cannot bypass ownership or stale revisions', function () {
    [$admin, $project] = boardFixture();
    $worker = boardWorker($project);
    $outsider = User::factory()->create();
    $outsider->assignRole(Role::findOrCreate('employee'));
    $card = BoardTask::create(['project_id' => $project->id, 'title' => 'Wspólne projektowe', 'assigned_to' => $admin->id]);
    $crm = Task::create(['company_id' => $project->company_id, 'title' => 'Wspólne CRM', 'assigned_to' => $admin->id, 'status' => 'todo']);
    $this->actingAs($admin);
    foreach (['board' => $card, 'crm' => $crm] as $kind => $task) {
        $url = route('board.participants', [$kind, $task->id]);
        $this->putJson($url, ['participants' => [$outsider->id], 'revision' => 1])->assertStatus(422);
        $this->putJson($url, ['participants' => [$worker->id], 'revision' => 1])->assertOk();
        expect($task->fresh()->participants->pluck('id')->all())->toBe([$worker->id]);
        $this->putJson($url, ['participants' => [], 'revision' => 1])->assertStatus(409);
    }
    $this->actingAs($worker)->get(route('board.mine'))->assertSee('Wspólne CRM')->assertSee('Wspólne projektowe');
    $this->patchJson(route('board.status', $card), ['status' => 'done', 'revision' => 2])->assertOk();
    $this->patchJson(route('board.crm.status', $crm), ['status' => 'done', 'revision' => 2])->assertOk();
    expect(Task::forUser($worker->id)->pluck('id')->all())->toContain($crm->id);
    $project->members()->detach($worker);
    $this->get(route('board.mine'))->assertDontSee('Wspólne projektowe');
    $this->putJson(route('board.participants', ['board', $card->id]), ['participants' => [], 'revision' => 3])->assertForbidden();
});

test('disabled modules hide their filters cards and CRM mutation and schedules are never duplicated', function () {
    [$admin, $project, $audit] = boardFixture();
    Task::create(['project_id' => $project->id, 'title' => 'Etap nie karta', 'assigned_to' => $admin->id]);
    Task::create(['audit_id' => $audit->id, 'title' => 'Etap audytu nie karta', 'assigned_to' => $admin->id]);
    $crm = Task::create(['title' => 'CRM tylko', 'assigned_to' => $admin->id, 'status' => 'todo']);
    CompanySettings::query()->delete();
    CompanySettings::create(['name' => 'Test', 'enabled_modules' => ['crm']]);
    $this->actingAs($admin)->get(route('board.mine'))->assertOk()->assertSee('CRM tylko')->assertDontSee('Etap nie karta')->assertDontSee('Etap audytu nie karta')->assertDontSee('name="projects[]"', false)->assertDontSee('name="audits[]"', false);
    CompanySettings::first()->update(['enabled_modules' => ['projects']]);
    $this->get(route('board.mine'))->assertDontSee('CRM tylko')->assertDontSee('name="crm[]"', false);
    $this->patchJson(route('board.crm.status', $crm), ['status' => 'done', 'revision' => 1])->assertNotFound();
});

test('mixed board paginates filtered CRM records before hydration without duplicates', function () {
    [$admin, $project] = boardFixture();
    for ($i = 0; $i < 92; $i++) {
        Task::create(['title' => 'CRM paginacja '.$i, 'assigned_to' => $admin->id, 'status' => 'todo']);
    }
    $this->actingAs($admin)->get(route('board.mine'))->assertOk()->assertViewHas('cards', fn ($cards) => $cards->count() === 90 && $cards->total() === 92);
    $this->get(route('board.mine', ['page' => 2]))->assertOk()->assertViewHas('cards', fn ($cards) => $cards->count() === 2);
});
