<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfferTemplateType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function offerTemplateVersions(): HasMany
    {
        return $this->hasMany(OfferTemplateVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentVersion(): ?OfferTemplateVersion
    {
        return $this->offerTemplateVersions()->where('is_current', true)->first();
    }
}
