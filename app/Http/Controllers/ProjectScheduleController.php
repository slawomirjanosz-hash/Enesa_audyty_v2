<?php

namespace App\Http\Controllers;

use App\Exports\ProjectGanttExport;
use App\Models\Project;
use App\Models\Task;
use App\Services\ProjectGanttImportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectScheduleController extends Controller
{
    public function storeTask(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'depends_on_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:todo,in_progress,done'],
            'priority' => ['required', 'in:low,medium,high'],
            'progress' => ['required', 'integer', 'between:0,100'],
            'is_milestone' => ['sometimes', 'boolean'],
        ]);
        if ($data['is_milestone'] ?? false) {
            $data['due_date'] = $data['start_date'];
        }
        $this->validateProjectTaskAssignee($project, $data['assigned_to'] ?? null);
        $this->validateTaskDependency($project, null, $data['depends_on_task_id'] ?? null);
        $data['project_position'] = ((int) $project->tasks()->max('project_position')) + 1;
        $task = $project->tasks()->create($data + [
            'company_id' => $project->company_id,
            'created_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            $task->load('assignedUser');

            return response()->json($this->taskTimelinePayload($project, $task), 201);
        }

        return redirect()->back()->with('success', 'Zadanie zostało dodane.');
    }

    public function updateTask(Request $request, Project $project, Task $task): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($task->project_id === $project->id, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'depends_on_task_id' => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'status' => ['sometimes', 'in:todo,in_progress,done'],
            'progress' => ['required', 'integer', 'between:0,100'],
            'start_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'is_milestone' => ['sometimes', 'boolean'],
        ]);
        if (($data['is_milestone'] ?? $task->is_milestone) && isset($data['start_date'])) {
            $data['due_date'] = $data['start_date'];
        }
        if (array_key_exists('assigned_to', $data)) {
            $this->validateProjectTaskAssignee($project, $data['assigned_to']);
        }
        if (array_key_exists('depends_on_task_id', $data)) {
            $this->validateTaskDependency($project, $task, $data['depends_on_task_id']);
        }
        $oldEnd = $task->due_date?->copy();
        $data['status'] ??= match (true) {
            $data['progress'] >= 100 => 'done',
            $data['progress'] > 0 => 'in_progress',
            default => 'todo',
        };
        if ($data['status'] === 'done') {
            $data['progress'] = 100;
        }
        $task->update($data);
        if ($oldEnd && $task->due_date) {
            $shiftDays = (int) $oldEnd->diffInDays($task->due_date, false);
            if ($shiftDays !== 0) {
                $visited = [$task->id];
                $this->shiftDependentTasks($project, $task, $shiftDays, $visited);
            }
        }

        if ($request->expectsJson()) {
            $task->load('assignedUser');
            $payload = $this->taskTimelinePayload($project, $task);
            $payload['project_tasks'] = $project->tasks()->with('assignedUser')->get()
                ->map(fn (Task $projectTask) => $this->taskTimelinePayload($project, $projectTask))->values();

            return response()->json($payload);
        }

        return redirect()->back()->with('success', 'Zadanie zostało zaktualizowane.');
    }

    public function destroyTask(Request $request, Project $project, Task $task): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($task->project_id === $project->id, 404);
        $task->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Zadanie zostało usunięte.');
    }

    public function bulkDestroyTasks(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $data = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $taskIds = collect($data['task_ids'])->map(fn ($id) => (int) $id)->values();
        $tasks = $project->tasks()->whereKey($taskIds)->get();
        if ($tasks->count() !== $taskIds->count()) {
            throw ValidationException::withMessages([
                'task_ids' => 'Wybrane zadania muszą należeć do tego projektu.',
            ]);
        }

        DB::transaction(function () use ($project, $tasks) {
            $tasks->each->delete();
            $project->tasks()->orderBy('project_position')->orderBy('id')->pluck('id')
                ->each(fn ($taskId, $position) => Task::whereKey($taskId)->update(['project_position' => $position]));
        });

        return response()->json([
            'success' => true,
            'deleted' => $tasks->count(),
        ]);
    }

    public function reorderTasks(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'distinct', 'exists:tasks,id'],
        ]);
        $projectTaskIds = $project->tasks()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $requestedIds = collect($data['order'])->map(fn ($id) => (int) $id)->sort()->values();
        if ($projectTaskIds->all() !== $requestedIds->all()) {
            throw ValidationException::withMessages(['order' => 'Kolejność musi zawierać wszystkie zadania tego projektu.']);
        }
        foreach ($data['order'] as $position => $taskId) {
            Task::where('project_id', $project->id)->whereKey($taskId)->update(['project_position' => $position]);
        }

        return response()->json(['success' => true]);
    }

    public function exportGantt(Project $project): BinaryFileResponse
    {
        $this->authorize('view', $project);

        $filename = 'Harmonogram_'.Str::slug($project->number ?: $project->name, '_').'.xlsx';

        return Excel::download(new ProjectGanttExport($project), $filename);
    }

    public function importGantt(Request $request, Project $project, ProjectGanttImportService $importer): RedirectResponse
    {
        $this->authorize('view', $project);
        $data = $request->validateWithBag('ganttImport', [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'new_start_date' => ['nullable', 'date'],
        ]);

        $report = $importer->import($project, $data['file'], $data['new_start_date'] ?? null, $request->user());

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'gantt'])
            ->with('success', "Import harmonogramu zakończony: dodano {$report['inserted']} zadań, pominięto {$report['duplicates']} duplikatów.")
            ->with('gantt_import_report', $report);
    }

    public function generatePublicGantt(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        if (! $project->public_gantt_token) {
            $project->update(['public_gantt_token' => Str::random(48)]);
        }

        return response()->json(['url' => route('projects.public-gantt', $project->public_gantt_token)]);
    }

    public function publicGantt(string $token): View
    {
        $project = Project::where('public_gantt_token', $token)
            ->with(['tasks.assignedUser', 'tasks.dependency'])
            ->firstOrFail();
        $timelineItems = $project->tasks->filter(fn ($task) => $task->start_date && $task->due_date)->map(fn ($task) => [
            'id' => 'task-'.$task->id,
            'name' => $task->title,
            'start' => $task->start_date->format('Y-m-d'),
            'end' => $task->due_date->format('Y-m-d'),
            'progress' => $task->progress,
            'dependencies' => $task->depends_on_task_id ? 'task-'.$task->depends_on_task_id : '',
            'kind' => $task->is_milestone ? 'milestone' : 'task',
            'is_milestone' => $task->is_milestone,
            'assignee' => $task->assignedUser?->name,
        ])->values();

        return view('projects.public-gantt', compact('project', 'timelineItems'));
    }

    private function validateTaskDependency(Project $project, ?Task $task, ?int $dependencyId): void
    {
        if (! $dependencyId) {
            return;
        }
        $dependency = Task::where('project_id', $project->id)->find($dependencyId);
        if (! $dependency) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'Zadanie zależne musi należeć do tego samego projektu.']);
        }
        if ($task && $dependency->id === $task->id) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'Zadanie nie może zależeć samo od siebie.']);
        }
        $visited = [];
        while ($dependency) {
            if (in_array($dependency->id, $visited, true) || ($task && $dependency->id === $task->id)) {
                throw ValidationException::withMessages(['depends_on_task_id' => 'Ta zależność utworzyłaby zamkniętą pętlę zadań.']);
            }
            $visited[] = $dependency->id;
            $dependency = $dependency->depends_on_task_id
                ? Task::where('project_id', $project->id)->find($dependency->depends_on_task_id)
                : null;
        }
    }

    private function shiftDependentTasks(Project $project, Task $task, int $days, array &$visited): void
    {
        $dependents = Task::where('project_id', $project->id)->where('depends_on_task_id', $task->id)->get();
        foreach ($dependents as $dependent) {
            if (in_array($dependent->id, $visited, true)) {
                continue;
            }
            $visited[] = $dependent->id;
            $dependent->update([
                'start_date' => Carbon::parse($dependent->start_date)->addDays($days),
                'due_date' => Carbon::parse($dependent->due_date)->addDays($days),
            ]);
            $this->shiftDependentTasks($project, $dependent, $days, $visited);
        }
    }

    private function taskTimelinePayload(Project $project, Task $task): array
    {
        return [
            'kind' => $task->is_milestone ? 'milestone' : 'task',
            'id' => 'task-'.$task->id,
            'db_id' => $task->id,
            'name' => $task->title,
            'start' => $task->start_date?->format('Y-m-d'),
            'end' => $task->due_date?->format('Y-m-d'),
            'progress' => $task->progress,
            'status' => $task->status,
            'priority' => $task->priority,
            'description' => $task->description,
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->assignedUser?->name,
            'is_milestone' => $task->is_milestone,
            'dependencies' => $task->depends_on_task_id ? 'task-'.$task->depends_on_task_id : '',
            'update_url' => route('projects.tasks.update', [$project, $task]),
            'delete_url' => route('projects.tasks.destroy', [$project, $task]),
            'position' => $task->project_position,
        ];
    }

    private function validateProjectTaskAssignee(Project $project, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $belongsToTeam = (int) $project->manager_id === $userId
            || $project->members()->whereKey($userId)->exists();

        if (! $belongsToTeam) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Zadanie można przypisać tylko użytkownikowi należącemu do tego projektu.',
            ]);
        }
    }
}
