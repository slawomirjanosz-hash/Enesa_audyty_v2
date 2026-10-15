<?php

namespace App\Support;

use App\Models\User;

final class HrAccess
{
    public static function canManageSettings(User $user): bool
    {
        return self::canViewTeam($user);
    }

    public static function canViewTeam(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin']) || $user->can('system.full_access') || $user->can('hr.team.view');
    }
}
