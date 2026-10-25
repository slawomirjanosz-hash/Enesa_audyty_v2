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
        $tasks = Task::with(['company', 'assignedUser.roles', 'participants.roles', 'project.members', 'audit'])
            ->where('due_date', '<', $date)->where('status', '!=', 'done')->get();
        $cards = BoardTask::with(['assignee.roles', 'participants.roles', 'project.members', 'project.company', 'audit.company'])
            ->where('due_date', '<', $date)->where('status', '!=', 'done')->get();

        // IDs in these two tables are independent sequences.
        $groups = collect();
        foreach (collect($tasks->all())->concat($cards->all()) as $task) {
            $responsible = $task instanceof BoardTask ? $task->assignee : $task->assignedUser;
            $people = collect([$responsible])->filter()->merge($task->participants)->unique('id');
            foreach ($people as $person) {
                if ($this->allowed($person, $task)) {
                    if (! $groups->has($person->id)) {
                        $groups->put($person->id, collect());
                    }
                    $groups->get($person->id)->push($task);
                }
            }
        }

        return $groups;
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
