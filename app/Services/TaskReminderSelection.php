<?php

namespace App\Services;

use App\Models\BoardTask;
use App\Models\CompanySettings;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

class TaskReminderSelection
{
    public function groups(): Collection
    {
        $date = now('Europe/Warsaw')->toDateString();
        $tasks = Task::with(['company', 'assignedUser.roles', 'project.members', 'audit'])
            ->where('due_date', '<', $date)->where('status', '!=', 'done')->whereNotNull('assigned_to')
            ->get()->filter(fn (Task $task) => $this->allowed($task->assignedUser, $task));
        $cards = BoardTask::with(['assignee.roles', 'project.members', 'project.company', 'audit.company'])
            ->where('due_date', '<', $date)->where('status', '!=', 'done')->whereNotNull('assigned_to')
            ->get()->filter(fn (BoardTask $task) => $this->allowed($task->assignee, $task));

        // IDs in these two tables are independent sequences.
        return collect($tasks->all())->concat($cards->all())->groupBy('assigned_to');
    }

    private function allowed(?User $user, Task|BoardTask $task): bool
    {
        if (! $user || ! $user->is_active || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)
            || $user->hasAnyRole(['client_admin', 'client_user']) || $user->roles->isEmpty()) {
            return false;
        }
        if ($task->project_id !== null) {
            return $task->project && ! in_array($task->project->status, ['completed', 'cancelled'], true)
                && app(BoardAccessService::class)->view($user, $task->project);
        }
        if ($task->audit_id !== null) {
            return $task->audit && ! in_array($task->audit->status, ['done', 'cancelled'], true)
                && app(BoardAccessService::class)->view($user, $task->audit);
        }

        return $task instanceof Task && CompanySettings::moduleIsEnabled('crm')
            && (app(AuditorAccessService::class)->hasFullAccess($user) || $user->canAny(['crm.tasks.own.manage', 'crm.tasks.team.manage']));
    }
}
