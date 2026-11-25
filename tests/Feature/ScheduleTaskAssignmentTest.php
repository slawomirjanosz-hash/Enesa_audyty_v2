<?php

use App\Models\BoardTask;
use App\Models\Task;
use App\Services\ScheduleTaskAssignment;
use App\Services\TaskReminderSelection;

test('assigned schedule stages create one task for personal board and follow stage changes', function () {
    [$admin, $project, $audit] = boardFixture();
    $worker = boardWorker($project);
    foreach (['project' => $project, 'audit' => $audit] as $kind => $owner) {
        if ($kind === 'audit') {
            $worker->givePermissionTo('audits.view');
            $owner->members()->syncWithoutDetaching([$worker->id]);
        }
        $stage = Task::create([$kind.'_id' => $owner->id, 'title' => 'Przypisany etap '.$kind, 'assigned_to' => $worker->id, 'due_date' => '2026-12-10', 'status' => 'todo']);
        $card = BoardTask::where('source_stage_id', $stage->id)->sole();
        expect($card->stage_task_id)->toBe($stage->id)->and($card->due_date->toDateString())->toBe('2026-12-10');
        app(ScheduleTaskAssignment::class)->sync($stage);
        expect(BoardTask::where('source_stage_id', $stage->id)->count())->toBe(1);
        $this->actingAs($worker)->get(route('board.mine'))->assertOk()->assertSee('Przypisany etap '.$kind);
        $stage->update(['title' => 'Zmieniony etap '.$kind, 'due_date' => '2026-12-15']);
        expect($card->fresh()->title)->toBe('Zmieniony etap '.$kind)->and($card->fresh()->due_date->toDateString())->toBe('2026-12-15');
        $card->update(['status' => 'done']);
        $card->delete();
        $stage->update(['due_date' => '2026-12-20']);
        expect(BoardTask::where('source_stage_id', $stage->id)->count())->toBe(0);
        expect(BoardTask::withTrashed()->where('source_stage_id', $stage->id)->count())->toBe(1);
        $this->get(route('board.mine'))->assertDontSee('Zmieniony etap '.$kind);
    }
});

test('existing matching archived schedule task is adopted without restoring and reminders do not duplicate', function () {
    [$admin, $project] = boardFixture();
    $worker = boardWorker($project);
    $stage = Task::create(['project_id' => $project->id, 'title' => 'Dawny etap', 'due_date' => '2026-10-01', 'status' => 'todo']);
    $card = BoardTask::create(['project_id' => $project->id, 'stage_task_id' => $stage->id, 'title' => $stage->title, 'assigned_to' => $worker->id, 'status' => 'done']);
    $card->delete();
    $stage->update(['assigned_to' => $worker->id]);
    expect($card->fresh()->source_stage_id)->toBe($stage->id);
    expect(BoardTask::where('source_stage_id', $stage->id)->count())->toBe(0);
    expect(app(TaskReminderSelection::class)->groups()->has($worker->id))->toBeFalse();
});

test('stage execution has one calendar entry and reminder and permanent deletion does not recreate it', function () {
    [$admin, $project] = boardFixture();
    $worker = boardWorker($project);
    $worker->givePermissionTo('calendar.view');
    $stage = Task::create(['project_id' => $project->id, 'title' => 'Jedno wykonanie etapu', 'assigned_to' => $worker->id, 'due_date' => '2026-10-01', 'status' => 'todo']);
    $card = BoardTask::where('source_stage_id', $stage->id)->sole();
    $reminders = app(TaskReminderSelection::class)->groups()->get($worker->id);
    expect($reminders)->toHaveCount(1)->and($reminders->first()->id)->toBe($card->id);
    $card->update(['status' => 'done']);
    $this->actingAs($worker)->get(route('calendar.index', ['month' => '2026-10']))->assertOk()
        ->assertViewHas('tasksByDate', fn ($days) => $days->flatten()->where('id', $stage->id)->sole()->status === 'done');
    expect(app(TaskReminderSelection::class)->groups()->has($worker->id))->toBeFalse();
    $card->forceDelete();
    $stage->update(['title' => 'Nie przywracaj usuniętego zadania']);
    expect(BoardTask::withTrashed()->where('source_stage_id', $stage->id)->count())->toBe(0);
    $this->get(route('calendar.index', ['month' => '2026-10']))->assertOk()
        ->assertViewHas('tasksByDate', fn ($days) => $days->flatten()->where('id', $stage->id)->isEmpty());
});
