<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\Concerns\HandlesStaffAccess;
use App\Services\AuditorAccessService;

class CompanyPolicy
{
    use HandlesStaffAccess;

    public function view(User $user, Company $company): bool
    {
        return app(AuditorAccessService::class)->hasAnyCompanyAccess($user, $company->id);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->canModify($user, 'crm.companies.manage');
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->canModify($user, 'crm.companies.manage');
    }
}
