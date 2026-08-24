<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\HandlesStaffAccess;
use App\Services\AuditorAccessService;

class TaskPolicy
{
    use HandlesStaffAccess;

    public function view(User $user, Task $task): bool
    {
        return $task->company_id !== null
            && app(AuditorAccessService::class)->canViewCompany($user, $task->company_id, 'can_view_dashboard');
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can('crm.tasks.team.manage')
            || ($task->assigned_to === $user->id && $user->can('crm.tasks.own.manage'));
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
