<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class ProjectScheduleDelayService
{
    private static bool $running = false;

    public function syncProject(int $projectId): void
    {
        if (self::$running) {
            return;
        }
        self::$running = true;
        try {
            DB::transaction(function () use ($projectId) {
                if (! Project::whereKey($projectId)->lockForUpdate()->first()) {
                    return;
                }
                $tasks = Task::where('project_id', $projectId)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $visited = [];
                $visiting = [];
                $sync = function (Task $task) use (&$sync, &$visited, &$visiting, $tasks): bool {
                    if (isset($visited[$task->id])) {
                        return true;
                    }
                    if (isset($visiting[$task->id])) {
                        return false; // Do not follow corrupt/legacy cycles.
                    }
                    $visiting[$task->id] = true;
                    $parent = $tasks->get($task->depends_on_task_id);
                    if ($parent && ! $sync($parent)) {
                        return false;
                    }
                    if ($parent && $parent->due_date && $task->start_date && $task->due_date && ! $task->scheduleIsComplete()) {
                        $anchor = $task->schedule_dependency_anchor ?? $parent->due_date->copy();
                        $target = (int) $anchor->diffInDays($parent->scheduleEffectiveEnd(), false);
                        $delta = $target - (int) $task->schedule_dependency_shift;
                        $task->forceFill(['schedule_dependency_anchor' => $anchor, 'schedule_dependency_shift' => $target]);
                        if ($delta !== 0) {
                            $task->start_date = $task->start_date->copy()->addDays($delta);
                            $task->due_date = $task->due_date->copy()->addDays($delta);
                        }
                        if ($task->isDirty()) {
                            $task->save(); // Also updates linked calendar/board cards through the existing model event.
                        }
                    }
                    unset($visiting[$task->id]);
                    $visited[$task->id] = true;

                    return true;
                };
                foreach ($tasks as $task) {
                    $sync($task);
                }
            }, 3);
        } finally {
            self::$running = false;
        }
    }

    public function syncAll(): void
    {
        Project::whereHas('tasks', fn ($q) => $q->whereNotNull('depends_on_task_id'))->select('id')->chunkById(100, function ($projects) {
            foreach ($projects as $project) {
                $this->syncProject($project->id);
            }
        });
    }
}
