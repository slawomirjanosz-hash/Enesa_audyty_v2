<?php

namespace App\Services;

use App\Models\AuditorCompanyAccess;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class AuditorAccessService
{
    public const FULL_ACCESS_ROLES = ['superadmin', 'admin', 'auditor_senior'];

    public function hasFullAccess(User $user): bool
    {
        return $user->hasAnyRole(self::FULL_ACCESS_ROLES)
            || $user->getAllPermissions()->contains('name', 'system.full_access');
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
