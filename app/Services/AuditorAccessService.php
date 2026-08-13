<?php

namespace App\Services;

use App\Models\AuditorCompanyAccess;
use App\Models\Document;
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

        if (! $this->isDelegatedAuditor($user)) return false;

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

        if (! $this->isDelegatedAuditor($user) && $user->can('crm.view')) return true;

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

        if (! $this->isDelegatedAuditor($user) && $modulePermission && $user->can($modulePermission)) return [];

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

        return $query->whereIn($companyColumn, $this->accessibleCompanyIds($user, $ability));
    }

    public function canViewDocument(User $user, Document $document): bool
    {
        if ($this->hasFullAccess($user)) {
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
        if ($this->hasFullAccess($user)) {
            return $query;
        }

        $companyIds = $this->accessibleCompanyIds($user, 'can_view_documents');

        return $query->where(function (Builder $documents) use ($user, $companyIds) {
            $documents->whereIn('company_id', $companyIds)
                ->orWhereHas('auditorAccesses', fn (Builder $accesses) => $accesses->where('user_id', $user->id));
        });
    }
}
