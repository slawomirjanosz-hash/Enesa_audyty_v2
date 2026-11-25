<?php

use App\Exports\ProjectGanttExport;
use App\Models\BoardTask;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectScheduleDelayService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 5, 1)->startOfDay());
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findOrCreate('superadmin'));
    $this->project = Project::create(['number' => 'DELAY/1', 'name' => 'Projekt z zależnościami', 'status' => 'active', 'manager_id' => $this->admin->id]);
    $this->stage = fn ($title, $start, $end, $parent = null) => $this->project->tasks()->create([
        'title' => $title, 'start_date' => $start, 'due_date' => $end, 'depends_on_task_id' => $parent?->id,
        'progress' => 0, 'status' => 'todo', 'priority' => 'medium', 'assigned_to' => $this->admin->id,
    ]);
    $this->source = ($this->stage)('Projekt technologiczny', '2026-05-01', '2026-05-10');
    $this->next = ($this->stage)('Projekt elektryczny', '2026-05-15', '2026-05-20', $this->source);
    $this->actingAs($this->admin);
});

afterEach(function () {
    $this->travelBack();
});

test('elapsed delay shifts successors once preserves planned source and propagates through chains', function () {
    $card = BoardTask::create(['title' => 'Zadanie etapu', 'stage_task_id' => $this->next->id, 'due_date' => '2026-05-20', 'stage_offset_days' => 0, 'assigned_to' => $this->admin->id]);
    $third = ($this->stage)('Montaż', '2026-05-25', '2026-05-26', $this->next);
    $this->travelTo(now()->setDate(2026, 5, 12));
    app(ProjectScheduleDelayService::class)->syncProject($this->project->id);
    expect($this->source->fresh()->due_date->toDateString())->toBe('2026-05-10')
        ->and($this->source->fresh()->scheduleDelayPayload())->toBe(['overdue_end' => '2026-05-12', 'overdue_days' => 2])
        ->and($this->next->fresh()->start_date->toDateString())->toBe('2026-05-17')
        ->and($this->next->fresh()->due_date->toDateString())->toBe('2026-05-22')
        ->and($third->fresh()->start_date->toDateString())->toBe('2026-05-27');
    expect($card->fresh()->due_date->toDateString())->toBe('2026-05-22');
    app(ProjectScheduleDelayService::class)->syncProject($this->project->id);
    expect($this->next->fresh()->due_date->toDateString())->toBe('2026-05-22');
    $this->travelTo(now()->addDay());
    $this->artisan('projects:sync-schedule-delays')->assertSuccessful();
    expect($this->next->fresh()->start_date->toDateString())->toBe('2026-05-18')
        ->and($third->fresh()->start_date->toDateString())->toBe('2026-05-28');
});

test('completion freezes actual delay and completed successors never move', function () {
    $this->travelTo(now()->setDate(2026, 5, 12));
    $this->source->update(['progress' => 100, 'status' => 'done']);
    expect($this->next->fresh()->due_date->toDateString())->toBe('2026-05-22');
    $this->next->update(['progress' => 100, 'status' => 'done']);
    $this->travelTo(now()->addDays(20));
    app(ProjectScheduleDelayService::class)->syncProject($this->project->id);
    expect($this->source->fresh()->scheduleDelayPayload()['overdue_days'])->toBe(2)
        ->and($this->next->fresh()->due_date->toDateString())->toBe('2026-05-22');
});

test('editing planned dates does not apply the same elapsed delay a second time', function () {
    $this->travelTo(now()->setDate(2026, 5, 12));
    app(ProjectScheduleDelayService::class)->syncProject($this->project->id);
    $this->source->update(['due_date' => '2026-05-12']);
    expect($this->next->fresh()->due_date->toDateString())->toBe('2026-05-22');
    $this->source->update(['due_date' => '2026-05-14']);
    expect($this->next->fresh()->due_date->toDateString())->toBe('2026-05-24');
});

test('authenticated schedule public link and export use the same shifted dates', function () {
    $this->project->update(['public_gantt_token' => 'delay-stable-token']);
    $this->travelTo(now()->setDate(2026, 5, 12));
    $this->get(route('projects.show', [$this->project, 'tab' => 'gantt']))->assertOk()->assertSee('project-gantt-delays.js');
    $response = $this->get(route('projects.public-gantt', 'delay-stable-token'))->assertOk();
    $items = $response->viewData('timelineItems');
    expect($items->firstWhere('id', 'task-'.$this->source->id)['end'])->toBe('2026-05-10')
        ->and($items->firstWhere('id', 'task-'.$this->next->id)['start'])->toBe('2026-05-17');
    $calendar = $this->get(route('calendar.index', ['month' => '2026-05']))->assertOk()->viewData('tasksByDate');
    expect($calendar->get('2026-05-22')->contains('id', $this->next->id))->toBeTrue();
    $rows = (new ProjectGanttExport($this->project))->array();
    expect(collect($rows)->first(fn ($row) => $row[2] === 'Projekt elektryczny')[4])->toBe('2026-05-17');
    $this->patchJson(route('projects.tasks.update', [$this->project, $this->source]), ['progress' => 100])->assertOk()->assertJsonPath('overdue_days', 2);
    $this->travelTo(now()->addDay());
    app(ProjectScheduleDelayService::class)->syncProject($this->project->id);
    expect($this->next->fresh()->start_date->toDateString())->toBe('2026-05-17')
        ->and($this->project->fresh()->public_gantt_token)->toBe('delay-stable-token');
    if ($path = getenv('SCHEDULE_DELAY_QA')) {
        file_put_contents($path, $this->get(route('projects.show', [$this->project, 'tab' => 'gantt']))->getContent());
    }
});

test('malformed cross project links and cycles cannot move unrelated tasks', function () {
    $other = Project::create(['number' => 'OTHER', 'name' => 'Inny', 'status' => 'active']);
    $foreign = $other->tasks()->create(['title' => 'Obcy', 'start_date' => '2026-05-15', 'due_date' => '2026-05-20']);
    DB::table('tasks')->where('id', $foreign->id)->update(['depends_on_task_id' => $this->source->id]);
    DB::table('tasks')->where('id', $this->source->id)->update(['depends_on_task_id' => $this->next->id]);
    $this->travelTo(now()->setDate(2026, 5, 12));
    app(ProjectScheduleDelayService::class)->syncProject($this->project->id);
    app(ProjectScheduleDelayService::class)->syncProject($other->id);
    expect($foreign->fresh()->due_date->toDateString())->toBe('2026-05-20');
    $reader = User::factory()->create();
    $this->actingAs($reader)->patchJson(route('projects.tasks.update',[$this->project, $this->source]),['progress' => 100])->assertForbidden();
});
