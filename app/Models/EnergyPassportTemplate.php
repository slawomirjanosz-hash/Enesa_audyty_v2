<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnergyPassportTemplate extends Model
{
    protected $fillable = [
        'name', 'code', 'scope', 'category', 'version', 'source_filename', 'sections', 'is_builtin', 'created_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'is_builtin' => 'boolean',
    ];

    public function passports(): HasMany
    {
        return $this->hasMany(EnergyPassport::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
