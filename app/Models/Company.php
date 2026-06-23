<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'nip',
        'email',
        'phone',
        'address',
        'city',
        'status',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function offerRequests(): HasMany
    {
        return $this->hasMany(OfferRequest::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_admin', 'deleted_at')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function archivedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_admin', 'deleted_at')
            ->withTimestamps()
            ->wherePivotNotNull('deleted_at');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }
}
