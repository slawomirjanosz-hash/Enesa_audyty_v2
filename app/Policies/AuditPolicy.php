<?php

namespace App\Policies;

use App\Models\Audit;
use App\Models\User;
use App\Policies\Concerns\HandlesStaffAccess;
use App\Services\AuditorAccessService;

class AuditPolicy
{
    use HandlesStaffAccess;

    public function view(User $user, Audit $audit): bool
    {
        return app(AuditorAccessService::class)->canViewCompany($user, $audit->company_id, 'can_view_audits');
    }
}
