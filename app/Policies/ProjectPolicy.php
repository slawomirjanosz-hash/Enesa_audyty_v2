<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\AuditorAccessService;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return (app(AuditorAccessService::class)->hasFullAccess($user) || $user->can('projects.view'))
            && (
                app(AuditorAccessService::class)->hasFullAccess($user)
                || $project->manager_id === $user->id
                || $project->members()->whereKey($user->id)->exists()
            );
    }

    public function update(User $user, Project $project): bool
    {
        return app(AuditorAccessService::class)->hasFullAccess($user)
            || ($user->can('projects.edit') && (
                $project->manager_id === $user->id
                || $project->members()->whereKey($user->id)->exists()
            ));
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']);
    }
}
