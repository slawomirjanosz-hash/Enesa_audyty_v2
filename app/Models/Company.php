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
        'show_in_dashboard',
        'is_owner',
        'notes',
        'source',
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

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function crmOpportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeOwner($query)
    {
        return $query->where('is_owner', true);
    }

    public function folderSlug(): string
    {
        $name = $this->name ?? ('firma_' . $this->id);

        // Zamiana polskich znaków diakrytycznych na łacińskie odpowiedniki
        $map = [
            'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
            'Ą'=>'A','Ć'=>'C','Ę'=>'E','Ł'=>'L','Ń'=>'N','Ó'=>'O','Ś'=>'S','Ź'=>'Z','Ż'=>'Z',
        ];
        $name = strtr($name, $map);

        // Usuń wszystko poza literami, cyframi, spacjami i myślnikami
        $name = preg_replace('/[^A-Za-z0-9 \-]/', '', $name);

        // Zamień spacje i wielokrotne myślniki na pojedynczy podkreślnik
        $name = preg_replace('/[\s\-]+/', '_', trim($name));

        // Usuń podkreślniki na początku/końcu, ogranicz długość
        $name = trim($name, '_');
        $name = substr($name, 0, 80);

        if (empty($name)) {
            $name = 'firma_' . $this->id;
        }

        return $name . '_' . $this->id;
    }

    /** Roles that belong to the application owner (Enesa) firm. */
    public const STAFF_ROLES = ['superadmin', 'admin', 'auditor_senior', 'auditor'];
}
