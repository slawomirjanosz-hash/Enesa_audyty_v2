<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\AuditorAccessService;

trait HandlesStaffAccess
{
    public function before(User $user): ?bool
    {
        return app(AuditorAccessService::class)->hasFullAccess($user) ? true : null;
    }

    protected function canModify(User $user, string $permission): bool
    {
        return app(AuditorAccessService::class)->hasFullAccess($user) || $user->can($permission);
    }
}
