<?php

namespace App\Support;

use App\Models\HrLeave;
use App\Models\User;

final class HrAccess
{
    public static function canViewAllLeaves(User $user): bool
    {
        return self::canViewTeam($user) || $user->can('hr.leaves.accounting');
    }

    public static function canViewLeave(User $user, HrLeave $leave): bool
    {
        return $leave->user_id === $user->id || $leave->approver_id === $user->id || self::canViewAllLeaves($user);
    }

    public static function canManageSettings(User $user): bool
    {
        return self::canViewTeam($user);
    }

    public static function canViewTeam(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin']) || $user->can('system.full_access') || $user->can('hr.team.view');
    }
}
