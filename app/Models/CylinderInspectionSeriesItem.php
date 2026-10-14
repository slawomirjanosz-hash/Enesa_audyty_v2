<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CylinderInspectionSeriesItem extends Model
{
    protected $fillable = ['cylinder_id', 'inspection_id'];

    public function series(): BelongsTo
    {
        return $this->belongsTo(CylinderInspectionSeries::class, 'series_id');
    }

    public function cylinder(): BelongsTo
    {
        return $this->belongsTo(Cylinder::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(CylinderInspection::class, 'inspection_id');
    }
}
