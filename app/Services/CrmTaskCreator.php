<?php

namespace App\Services;

use App\Http\Controllers\BoardCollaborationController;
use App\Mail\TaskAssigned;
use App\Models\CrmOpportunity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CrmTaskCreator
{
    public function create(Request $request, string $errorBag = 'taskCreate'): Task
    {
        $canManageTeam = app(AuditorAccessService::class)->hasFullAccess($request->user())
            || $request->user()->can('crm.tasks.team.manage');
        abort_unless($canManageTeam || $request->user()->can('crm.tasks.own.manage'), 403);
        $data = $request->validateWithBag($errorBag, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'crm_opportunity_id' => ['nullable', 'exists:crm_opportunities,id'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'status' => ['required', 'in:todo,in_progress,done'],
            'priority' => ['required', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'company_context_id' => ['nullable', 'integer', 'exists:companies,id'],
            'participants' => ['nullable', 'array', 'max:100'],
            'participants.*' => ['integer', 'distinct'],
        ]);
        $participants = $data['participants'] ?? [];
        unset($data['participants']);
        $candidates = BoardCollaborationController::candidates(new Task)->modelKeys();
        if (array_diff($participants, $candidates)) {
            throw ValidationException::withMessages(['participants' => 'Wybierz aktywnych pracowników z dostępem do zadań CRM.'])->errorBag($errorBag);
        }
        unset($data['company_context_id']);
        if (! $canManageTeam) {
            $data['assigned_to'] = $request->user()->id;
        }
        if (! empty($data['assigned_to']) && User::find($data['assigned_to'])?->hasRole('superadmin')) {
            throw ValidationException::withMessages(['assigned_to' => 'Wybierz pracownika.'])->errorBag($errorBag);
        }
        if (! empty($data['crm_opportunity_id']) && ! CrmOpportunity::query()
            ->whereKey($data['crm_opportunity_id'])->where('company_id', $data['company_id'] ?? null)->exists()) {
            throw ValidationException::withMessages(['crm_opportunity_id' => 'Wybrany lead nie należy do wskazanego klienta.'])->errorBag($errorBag);
        }
        $task = DB::transaction(function () use ($data, $participants, $request) {
            $status = $data['status'];
            $task = Task::create(array_merge($data, ['status' => 'todo', 'created_by' => $request->user()->id]));
            $task->participants()->sync(array_values(array_diff($participants, [$task->assigned_to])));
            app(TaskCompletionService::class)->apply($task, ['status' => $status], $request->user());
            $task->save();

            return $task;
        });
        if ($task->assigned_to && (int) $task->assigned_to !== $request->user()->id) {
            $assignedUser = $task->assignedUser;
            if ($assignedUser && $assignedUser->email) {
                Mail::to($assignedUser->email)->send(new TaskAssigned($task));
            }
        }

        return $task;
    }
}
