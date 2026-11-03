<?php

namespace App\Services;

use App\Mail\TaskAssigned;
use App\Models\CrmOpportunity;
use App\Models\Task;
use Illuminate\Http\Request;
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
        ]);
        unset($data['company_context_id']);
        if (! $canManageTeam) {
            $data['assigned_to'] = $request->user()->id;
        }
        if (! empty($data['crm_opportunity_id']) && ! CrmOpportunity::query()
            ->whereKey($data['crm_opportunity_id'])->where('company_id', $data['company_id'] ?? null)->exists()) {
            throw ValidationException::withMessages(['crm_opportunity_id' => 'Wybrany lead nie należy do wskazanego klienta.'])->errorBag($errorBag);
        }
        $task = Task::create(array_merge($data, ['created_by' => $request->user()->id]));
        if ($task->assigned_to && (int) $task->assigned_to !== $request->user()->id) {
            $assignedUser = $task->assignedUser;
            if ($assignedUser && $assignedUser->email) {
                Mail::to($assignedUser->email)->send(new TaskAssigned($task));
            }
        }

        return $task;
    }
}
