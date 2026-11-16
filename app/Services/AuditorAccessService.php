<?php

namespace App\Services;

use App\Models\AuditorCompanyAccess;
use App\Models\Document;
use App\Models\Offer;
use App\Models\OfferRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class AuditorAccessService
{
    public function hasFullAccess(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can('system.full_access');
    }

    public function isDelegatedAuditor(User $user): bool
    {
        return $user->hasRole('auditor');
    }

    public function canViewCompany(User $user, int $companyId, string $ability): bool
    {
        if ($this->hasFullAccess($user)) {
            return true;
        }

        $modulePermission = [
            'can_view_dashboard' => 'crm.view',
            'can_view_audits' => 'audits.view',
            'can_view_offer_requests' => 'offers.view',
            'can_view_offers' => 'offers.view',
            'can_view_offer_prices' => 'offers.prices.view',
            'can_view_documents' => 'documents.view',
            'can_view_chat' => 'client_zone.chat.manage',
        ][$ability] ?? null;

        if (! $this->isDelegatedAuditor($user) && $modulePermission && $user->can($modulePermission)) {
            return true;
        }

        if (! $this->isDelegatedAuditor($user)) {
            return false;
        }

        return AuditorCompanyAccess::query()
            ->where('auditor_id', $user->id)
            ->where('company_id', $companyId)
            ->where($ability, true)
            ->exists();
    }

    public function hasAnyCompanyAccess(User $user, int $companyId): bool
    {
        if ($this->hasFullAccess($user)) {
            return true;
        }

        if (! $this->isDelegatedAuditor($user) && $user->can('crm.view')) {
            return true;
        }

        return $this->isDelegatedAuditor($user)
            && AuditorCompanyAccess::query()
                ->where('auditor_id', $user->id)
                ->where('company_id', $companyId)
                ->exists();
    }

    public function accessibleCompanyIds(User $user, string $ability): array
    {
        if ($this->hasFullAccess($user)) {
            return [];
        }

        $modulePermission = [
            'can_view_dashboard' => 'crm.view',
            'can_view_audits' => 'audits.view',
            'can_view_offer_requests' => 'offers.view',
            'can_view_offers' => 'offers.view',
            'can_view_offer_prices' => 'offers.prices.view',
            'can_view_documents' => 'documents.view',
            'can_view_chat' => 'client_zone.chat.manage',
        ][$ability] ?? null;

        if (! $this->isDelegatedAuditor($user) && $modulePermission && $user->can($modulePermission)) {
            return [];
        }

        if (! $this->isDelegatedAuditor($user)) {
            return [-1];
        }

        return AuditorCompanyAccess::query()
            ->where('auditor_id', $user->id)
            ->where($ability, true)
            ->pluck('company_id')
            ->all();
    }

    public function scopeByCompanyAccess(Builder|Relation $query, User $user, string $ability, string $companyColumn = 'company_id'): Builder|Relation
    {
        if ($this->hasFullAccess($user)) {
            return $query;
        }

        $modulePermission = [
            'can_view_dashboard' => 'crm.view',
            'can_view_audits' => 'audits.view',
            'can_view_offer_requests' => 'offers.view',
            'can_view_offers' => 'offers.view',
            'can_view_offer_prices' => 'offers.prices.view',
            'can_view_documents' => 'documents.view',
            'can_view_chat' => 'client_zone.chat.manage',
        ][$ability] ?? null;

        // Offer ownership limits regular staff access. Delegated auditors use explicit company grants.
        if (! $this->isDelegatedAuditor($user) && $modulePermission && $user->can($modulePermission)) {
            if (in_array($ability, ['can_view_offers', 'can_view_offer_requests'], true)) {
                $table = $query->getModel()->getTable();

                return $query->whereNotNull($table.'.company_id')->where(function (Builder $records) use ($table, $user, $ability) {
                    $records->where($table.'.assigned_user_id', $user->id)
                        ->orWhere(fn (Builder $unassigned) => $unassigned->whereNull($table.'.assigned_user_id')
                            ->where($table.'.created_by_id', $user->id));

                    if ($ability === 'can_view_offers') {
                        $records->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
                    }
                });
            }

            return $query;
        }

        return $query->whereIn($companyColumn, $this->accessibleCompanyIds($user, $ability));
    }

    public function canViewOfferRecord(User $user, Offer|OfferRequest $record, string $ability): bool
    {
        if ($this->hasFullAccess($user)) {
            return true;
        }

        return $record->company_id !== null
            && $this->canViewCompany($user, $record->company_id, $ability)
            && ($this->isDelegatedAuditor($user)
                || ($record->assigned_user_id ?? $record->created_by_id) === $user->id
                || ($record instanceof Offer && $record->members()->whereKey($user->id)->exists()));
    }

    public function canViewDocument(User $user, Document $document): bool
    {
        if ($document->offer_id !== null && ! $this->hasFullAccess($user)
            && (! $document->offer || ! $user->can('view', $document->offer))) {
            return false;
        }
        if ($document->project_id !== null) {
            return $document->project !== null && $user->can('view', $document->project)
                && ($this->hasFullAccess($user) || $user->can('projects.documents.view'));
        }
        if ($this->hasFullAccess($user)) {
            return true;
        }

        if (! $this->isDelegatedAuditor($user) && $user->can('documents.view')) {
            return true;
        }

        if (! $this->isDelegatedAuditor($user)) {
            return false;
        }

        return $this->canViewCompany($user, $document->company_id, 'can_view_documents')
            || $document->auditorAccesses()->where('user_id', $user->id)->exists();
    }

    public function scopeDocumentsVisibleTo(Builder $query, User $user): Builder
    {
        $query->where(function (Builder $documents) use ($user) {
            $documents->whereNull('project_id')->orWhereHas('project', function (Builder $projects) use ($user) {
                if (! $this->hasFullAccess($user)) {
                    if (! $user->can('projects.view') || ! $user->can('projects.documents.view')) {
                        $projects->whereRaw('1 = 0');
                    } else {
                        $projects->where(fn (Builder $members) => $members->where('manager_id', $user->id)
                            ->orWhereHas('members', fn (Builder $users) => $users->whereKey($user->id)));
                    }
                }
            });
        });
        if ($this->hasFullAccess($user)) {
            return $query;
        }

        $query->where(fn (Builder $documents) => $documents->whereNull('offer_id')
            ->orWhereHas('offer', fn (Builder $offers) => $this->scopeByCompanyAccess($offers, $user, 'can_view_offers')));

        if (! $this->isDelegatedAuditor($user) && $user->can('documents.view')) {
            return $query;
        }

        $companyIds = $this->accessibleCompanyIds($user, 'can_view_documents');

        return $query->where(function (Builder $documents) use ($user, $companyIds) {
            $documents->whereIn('company_id', $companyIds)
                ->orWhereHas('auditorAccesses', fn (Builder $accesses) => $accesses->where('user_id', $user->id));
        });
    }
}
