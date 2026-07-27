<?php

namespace App\Policies;

use App\Models\CrmOpportunity;
use App\Models\User;
use App\Policies\Concerns\HandlesStaffAccess;
use App\Services\AuditorAccessService;

class CrmOpportunityPolicy
{
    use HandlesStaffAccess;

    public function view(User $user, CrmOpportunity $opportunity): bool
    {
        return $opportunity->company_id !== null
            && app(AuditorAccessService::class)->canViewCompany($user, $opportunity->company_id, 'can_view_dashboard');
    }

    public function update(User $user, CrmOpportunity $opportunity): bool { return $this->canModify($user); }
    public function delete(User $user, CrmOpportunity $opportunity): bool { return $this->canModify($user); }
}