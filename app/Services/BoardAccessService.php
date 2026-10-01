<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\AuditorCompanyAccess;
use App\Models\BoardTask;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class BoardAccessService
{
    public function view(User $user, Project|Audit $owner): bool
    {
        $project = $owner instanceof Project;
        if (! CompanySettings::moduleIsEnabled($project ? 'projects' : 'audits')) {
            return false;
        }
        if ($user->hasAnyRole(['client_admin', 'client_user'])) {
            return ! $project && $user->companies()->whereKey($owner->company_id)->exists();
        }
        if (! Gate::forUser($user)->allows('view', $owner)) {
            return false;
        }

        return ! $project || app(AuditorAccessService::class)->hasFullAccess($user) || $user->canAny(['projects.schedule.view', 'projects.schedule.manage']);
    }

    public function manage(User $user, Project|Audit $owner): bool
    {
        return ! $user->hasAnyRole(['client_admin', 'client_user']) && $this->view($user, $owner)
            && (app(AuditorAccessService::class)->hasFullAccess($user) || ($owner instanceof Project ? $user->can('projects.schedule.manage') : $user->canAny(['audits.manage', 'audits.schedule.manage'])));
    }

    public function changeStatus(User $user, BoardTask $card): bool
    {
        $owner = $card->project ?? $card->audit;

        return $owner && ! $user->hasAnyRole(['client_admin', 'client_user']) && $this->view($user, $owner)
            && ($card->assigned_to === $user->id || $this->manage($user, $owner));
    }

    public function mine(User $user): Builder
    {
        $full = app(AuditorAccessService::class)->hasFullAccess($user);

        return BoardTask::query()->where('assigned_to', $user->id)->where(function ($q) use ($user, $full) {
            $q->whereRaw('1=0');
            if (CompanySettings::moduleIsEnabled('projects') && ($full || $user->canAny(['projects.schedule.view', 'projects.schedule.manage']))) {
                $q->orWhereHas('project', function ($projects) use ($user, $full) {
                    if (! $full) {
                        if (! $user->can('projects.view')) {
                            $projects->whereRaw('1=0');

                            return;
                        }
                        $projects->where(fn ($p) => $p->where('manager_id', $user->id)->orWhereHas('members', fn ($m) => $m->whereKey($user->id)));
                    }
                });
            }
            if (CompanySettings::moduleIsEnabled('audits')) {
                $q->orWhereHas('audit', function ($audits) use ($user, $full) {
                    if ($full) {
                        return;
                    }
                    if ($user->hasRole('auditor')) {
                        $audits->whereIn('company_id', AuditorCompanyAccess::where('auditor_id', $user->id)->where('can_view_audits', true)->select('company_id'));
                    } elseif (! $user->can('audits.view')) {
                        $audits->whereRaw('1=0');
                    }
                });
            }
        });
    }
}
