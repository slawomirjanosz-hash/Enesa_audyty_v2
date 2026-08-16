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
        'password',
        'is_active',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'last_seen_at' => 'datetime',
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
}
