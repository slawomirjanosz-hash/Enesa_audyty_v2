<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\HrLeave;
use App\Models\User;
use Illuminate\Support\Collection;

class HrLeaveReminderSelection
{
    public function groups(): Collection
    {
        if (! CompanySettings::moduleIsEnabled('hr')) {
            return collect();
        }
        $tomorrow = now('Europe/Warsaw')->addDay()->toDateString();

        return HrLeave::with('user')->where('approval_status', 'pending')
            ->where('type', '!=', 'sick_leave')->whereNotNull('approver_id')
            ->whereColumn('approver_id', '!=', 'user_id')->whereDate('start_date', '<=', $tomorrow)
            ->orderBy('start_date')->orderBy('id')->get()->groupBy('approver_id')
            ->filter(function ($leaves, $id) {
                $person = User::find($id);

                return $person && $person->is_active && filter_var($person->email, FILTER_VALIDATE_EMAIL)
                    && ! $person->hasAnyRole(['client_admin', 'client_user'])
                    && ($person->hasRole('superadmin') || $person->canAny(['hr.leaves.view', 'system.full_access']));
            });
    }
}
