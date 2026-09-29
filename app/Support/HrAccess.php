<?php

namespace App\Support;

use App\Models\User;

final class HrAccess
{
    public static function canViewTeam(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin']) || $user->can('system.full_access') || $user->can('hr.team.view');
    }

    public static function canViewAllVehicles(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin']) || $user->can('system.full_access') || $user->can('hr.vehicles.all.view');
    }
}
