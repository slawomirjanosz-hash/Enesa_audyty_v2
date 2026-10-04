<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CompanyReliabilityAccess
{
    public function allows(?User $user, string $action = 'view', ?Company $company = null): bool
    {
        // Client roles are denied even if accidentally assigned a staff permission/second role.
        if (! $user || $user->hasAnyRole(['client_admin', 'client_user']) || $user->roles->isEmpty()) {
            return false;
        }
        $permitted = $user->hasRole('superadmin') || ($user->hasPermissionTo('company_reliability.view')
            && ($action === 'view' || $user->hasPermissionTo('company_reliability.'.$action)));

        return $permitted && (! $company || app(AuditorAccessService::class)->hasAnyCompanyAccess($user, $company->id));
    }
}
