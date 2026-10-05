<?php

use App\Models\BoardTask;
use App\Models\CrmOpportunity;
use App\Models\Task;

test('tasks archive and restore without losing owner lead or participants', function () {
    [$admin, $project, $audit] = boardFixture();
    $lead = CrmOpportunity::create(['company_id' => $project->company_id, 'title' => 'Szansa archiwalna', 'stage' => 'new_lead', 'created_by' => $admin->id]);
    $tasks = [
        ['board', BoardTask::create(['project_id' => $project->id, 'title' => 'Archiwalne projektowe', 'assigned_to' => $admin->id])],
        ['board', BoardTask::create(['audit_id' => $audit->id, 'title' => 'Archiwalne audytowe', 'assigned_to' => $admin->id])],
        ['crm', Task::create(['crm_opportunity_id' => $lead->id, 'company_id' => $project->company_id, 'title' => 'Archiwalne CRM', 'assigned_to' => $admin->id, 'status' => 'done'])],
    ];
    $this->actingAs($admin);
    foreach ($tasks as [$kind, $task]) {
        $this->patchJson(route('board.archive', [$kind, $task->id]), ['revision' => 1, 'restore' => false])->assertOk();
        $this->assertSoftDeleted($task->getTable(), ['id' => $task->id]);
        $this->get(route('board.mine'))->assertDontSee($task->title);
        $this->get(route('board.mine', ['archive' => 1]))->assertOk()->assertSee($task->title)->assertSee('Przywróć');
        $this->get(route('crm.index', ['tab' => 'trash']))->assertOk()->assertSee($task->title);
    }
    $this->get(route('projects.show', [$project, 'tab' => 'tasks', 'board_archive' => 1]))->assertOk()->assertSee('Archiwalne projektowe')->assertDontSee('Archiwalne audytowe');
    $this->get(route('audits.show', [$audit, 'tab' => 'tasks', 'board_archive' => 1]))->assertOk()->assertSee('Archiwalne audytowe');
    $this->get(route('crm.index', ['tab' => 'pipeline']))->assertOk()->assertSee('Archiwalne CRM');
    if (getenv('ARCHIVE_BOARD_QA')) {
        file_put_contents(base_path('tmp/archive-board.html'), $this->get(route('board.mine', ['archive' => 1]))->getContent());
        file_put_contents(base_path('tmp/archive-crm.html'), $this->get(route('crm.index', ['tab' => 'trash']))->getContent());
    }
    foreach ($tasks as [$kind, $task]) {
        $this->patchJson(route('board.archive', [$kind, $task->id]), ['revision' => 1, 'restore' => true])->assertStatus(409);
        $fresh = $task->fresh();
        $revision = $kind === 'board' ? $fresh->revision : $fresh->board_revision;
        $this->patchJson(route('board.archive', [$kind, $task->id]), ['revision' => $revision, 'restore' => true])->assertOk();
        expect($task->fresh()->trashed())->toBeFalse();
        $this->get(route('board.mine'))->assertSee($task->title);
    }
    expect($tasks[2][1]->fresh()->crm_opportunity_id)->toBe($lead->id);
});

test('archive permission follows task write permission not team visibility', function () {
    [$admin, $project] = boardFixture();
    $worker = boardWorker($project);
    $worker->givePermissionTo('board.team.view');
    $own = BoardTask::create(['project_id' => $project->id, 'title' => 'Własne', 'assigned_to' => $worker->id]);
    $foreign = BoardTask::create(['project_id' => $project->id, 'title' => 'Cudze', 'assigned_to' => $admin->id]);
    $crm = Task::create(['company_id' => $project->company_id, 'title' => 'Cudze CRM', 'assigned_to' => $admin->id, 'status' => 'todo']);
    $this->actingAs($worker)->patchJson(route('board.archive', ['board', $own->id]), ['revision' => 1, 'restore' => false])->assertOk();
    foreach (['board' => $foreign, 'crm' => $crm] as $kind => $task) {
        $this->patchJson(route('board.archive', [$kind, $task->id]), ['revision' => 1, 'restore' => false])->assertForbidden();
    }
    $project->members()->detach($worker);
    $this->patchJson(route('board.archive', ['board', $own->id]), ['revision' => 2, 'restore' => true])->assertForbidden();
    $this->get(route('board.mine', ['archive' => 1]))->assertDontSee('Własne');
    $schedule = Task::create(['project_id' => $project->id, 'title' => 'Etap', 'status' => 'todo']);
    $this->actingAs($admin)->patchJson(route('board.archive', ['crm', $schedule->id]), ['revision' => 1, 'restore' => false])->assertNotFound();
});
