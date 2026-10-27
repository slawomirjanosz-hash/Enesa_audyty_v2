<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\FinancialDepartment;
use App\Models\FinancialPlan;
use App\Models\Offer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FinancialPlanAccess
{
    public function allowed(User $user, bool $manage = false): bool
    {
        return ! $user->hasAnyRole(['client_admin', 'client_user']) && ($user->hasRole('superadmin')
            || ($manage ? $user->can('financial_plans.manage') : $user->canAny(['financial_plans.view', 'financial_plans.manage'])));
    }

    public function departments(User $user): Builder
    {
        return FinancialDepartment::query()->when(! $user->hasRole('superadmin'), fn ($query) => $query
            ->where(fn ($scope) => $scope->where('owner_id', $user->id)->orWhereHas('planners', fn ($users) => $users->whereKey($user->id))));
    }

    public function authorizeDepartment(User $user, FinancialDepartment $department, bool $manage = false): void
    {
        abort_unless($this->allowed($user, $manage) && $this->departments($user)->whereKey($department->id)->exists(), 403);
    }

    public function projects(User $user): Builder
    {
        $query = Project::query();
        $fullAccess = app(AuditorAccessService::class)->hasFullAccess($user);
        $priceAccess = $user->hasRole('superadmin') || ($user->can('projects.requirements.material_prices.view') && $user->can('projects.requirements.service_prices.view'));
        if (! CompanySettings::moduleIsEnabled('projects') || ! $priceAccess
            || (! $fullAccess && (! $user->can('projects.view') || ! $user->canAny(['projects.finances.view', 'projects.finances.manage'])))) {
            return $query->whereRaw('1 = 0');
        }

        return $query->when(! $fullAccess, fn ($q) => $q
            ->where(fn ($scope) => $scope->where('manager_id', $user->id)->orWhereHas('members', fn ($members) => $members->whereKey($user->id))));
    }

    public function offers(User $user): Builder
    {
        $query = Offer::query()->where('is_template', false)->whereNotNull('company_id');
        if (! CompanySettings::moduleIsEnabled('offers')) {
            return $query->whereRaw('1 = 0');
        }
        $access = app(AuditorAccessService::class);
        $access->scopeByCompanyAccess($query, $user, 'can_view_offers');
        $access->scopeByCompanyAccess($query, $user, 'can_view_offer_prices');

        return $query;
    }

    public function canViewBaseline(User $user, FinancialPlan $plan): bool
    {
        $snapshot = $plan->approved_snapshot;
        if (! $snapshot) {
            return false;
        }
        $projectIds = $snapshot['project_ids'];
        $offerIds = $snapshot['offer_ids'];

        return $this->projects($user)->whereKey($projectIds)->count() === count($projectIds)
            && $this->offers($user)->whereKey($offerIds)->count() === count($offerIds);
    }
}
