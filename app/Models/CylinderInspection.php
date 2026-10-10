<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CylinderInspection extends Model
{
    public const RESULTS = ['no_findings' => 'Pozytywny', 'defects_found' => 'Negatywny', 'further_review' => 'Wymaga dalszej oceny'];

    protected $fillable = ['inspected_at', 'next_due_at', 'result', 'observations', 'inspector_id', 'inspector_name', 'weight_kg', 'working_pressure_bar'];

    protected $casts = ['inspected_at' => 'date', 'next_due_at' => 'date', 'revision' => 'integer'];

    public function videos(): HasMany
    {
        return $this->hasMany(CylinderVideo::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CylinderPhoto::class);
    }

    public function cylinder(): BelongsTo
    {
        return $this->belongsTo(Cylinder::class);
    }
}
