<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar_data',
        'avatar_mime',
        'signature_data',
        'signature_mime',
        'password',
        'is_active',
        'has_employment_contract',
        'last_seen_at',
        'dashboard_tasks_seen_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'avatar_data',
        'signature_data',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'has_employment_contract' => 'boolean',
            'last_seen_at' => 'datetime',
            'dashboard_tasks_seen_id' => 'integer',
        ];
    }

    public function initials(): string
    {
        $nameParts = preg_split('/\s+/u', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($nameParts === []) {
            return '?';
        }

        $firstInitial = mb_substr($nameParts[0], 0, 1);
        $lastInitial = count($nameParts) > 1
            ? mb_substr($nameParts[array_key_last($nameParts)], 0, 1)
            : '';

        return mb_strtoupper($firstInitial.$lastInitial);
    }

    public function avatarDataUri(): ?string
    {
        if (! $this->avatar_data) {
            return null;
        }

        return 'data:'.($this->avatar_mime ?: 'image/jpeg').';base64,'.$this->avatar_data;
    }

    public function signatureDataUri(): ?string
    {
        return $this->signature_data
            ? 'data:'.($this->signature_mime ?: 'image/png').';base64,'.$this->signature_data
            : null;
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('is_admin', 'deleted_at')
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function allCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('is_admin', 'deleted_at')
            ->withTimestamps();
    }

    public function assignedOffers(): HasMany
    {
        return $this->hasMany(Offer::class, 'assigned_user_id');
    }

    public function createdOffers(): HasMany
    {
        return $this->hasMany(Offer::class, 'created_by_id');
    }

    public function auditorCompanyAccesses(): HasMany
    {
        return $this->hasMany(AuditorCompanyAccess::class, 'auditor_id');
    }

    public function auditorDocumentAccesses(): HasMany
    {
        return $this->hasMany(AuditorDocumentAccess::class);
    }

    public function relatedCrmOpportunities(): BelongsToMany
    {
        return $this->belongsToMany(CrmOpportunity::class, 'crm_opportunity_user')->withTimestamps();
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(HrLeaveEntitlement::class);
    }

    public function hrLeaves(): HasMany
    {
        return $this->hasMany(HrLeave::class);
    }

    public function annualLeaveUsedDays(int $year): int
    {
        return $this->hrLeaves()->whereIn('type', ['annual', 'on_demand'])
            ->whereYear('start_date', '<=', $year)->whereYear('end_date', '>=', $year)->get()
            ->sum(function (HrLeave $leave) use ($year): int {
                $date = $leave->start_date->copy();
                $used = 0;
                while ($date->lte($leave->end_date)) {
                    if ($date->year === $year && ($leave->include_weekends || $date->isWeekday())) {
                        $used++;
                    }
                    $date->addDay();
                }

                return $used;
            });
    }

    public function annualLeaveBalance(int $year): array
    {
        $entitled = (int) ($this->leaveEntitlements()->where('year', $year)->value('entitled_days') ?? 0);
        $used = $this->annualLeaveUsedDays($year);

        return ['entitled' => $entitled, 'used' => $used, 'remaining' => $entitled - $used];
    }
}
