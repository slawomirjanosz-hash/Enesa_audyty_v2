<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Services\AuditorAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditGanttShareController extends Controller
{
    private function manage(Request $request, Audit $audit): void
    {
        $this->authorize('view', $audit);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()) || $request->user()->can('audits.manage') || $request->user()->can('audits.schedule.manage'), 403);
    }

    public function store(Request $request, Audit $audit): JsonResponse
    {
        $this->manage($request, $audit);
        if (! $audit->public_gantt_token) {
            $audit->update(['public_gantt_token' => Str::random(48)]);
        }

        return response()->json(['url' => route('audits.public-gantt', $audit->public_gantt_token)]);
    }

    public function destroy(Request $request, Audit $audit): RedirectResponse
    {
        $this->manage($request, $audit);
        $audit->update(['public_gantt_token' => null]);

        return redirect()->route('audits.show', [$audit, 'tab' => 'schedule'])->with('success', 'Link do harmonogramu został wyłączony.');
    }

    public function show(string $token): View
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{48}$/D', $token), 404);
        $audit = Audit::where('public_gantt_token', $token)->with('tasks.assignedUser')->firstOrFail();
        $timelineItems = $audit->tasks->map(fn ($task) => [
            'id' => 'task-'.$task->id, 'name' => $task->title, 'start' => $task->start_date?->format('Y-m-d'), 'end' => $task->due_date?->format('Y-m-d'),
            'progress' => $task->progress, 'dependencies' => $task->depends_on_task_id ? 'task-'.$task->depends_on_task_id : '',
            'kind' => $task->is_milestone ? 'milestone' : 'task', 'is_milestone' => $task->is_milestone, 'assignee' => $task->assignedUser?->name,
        ])->values();

        return view('audits.public-gantt', compact('audit', 'timelineItems'));
    }
}
