<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferForm extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function offerFormVersions(): HasMany
    {
        return $this->hasMany(OfferFormVersion::class);
    }

    public function currentVersion(): ?OfferFormVersion
    {
        return $this->offerFormVersions()->where('is_current', true)->first();
    }
}
