<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditType extends Model
{
    protected $fillable = ['name', 'slug'];

    public function versions(): HasMany
    {
        return $this->hasMany(AuditTypeVersion::class)->orderByDesc('created_at');
    }

    public function currentVersion(): ?AuditTypeVersion
    {
        return $this->versions()->where('is_current', true)->first();
    }
}
