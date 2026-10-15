<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CylinderProtocol extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array', 'issued_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Wydanego protokołu nie można zmieniać.'));
        static::deleting(fn () => throw new \LogicException('Wydanego protokołu nie można usuwać.'));
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(CylinderInspectionSeries::class, 'series_id');
    }
}
