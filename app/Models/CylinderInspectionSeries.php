<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CylinderInspectionSeries extends Model
{
    public const STATUSES = ['draft' => 'Robocza', 'in_progress' => 'W trakcie', 'completed' => 'Zakończona'];

    protected $table = 'cylinder_inspection_series';

    protected $fillable = ['name', 'company_id', 'responsible_id', 'created_by', 'status', 'completed_at'];

    protected $attributes = ['status' => 'draft'];

    protected $casts = ['completed_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(CylinderInspectionSeriesItem::class, 'series_id');
    }

    public function protocol(): HasOne
    {
        return $this->hasOne(CylinderProtocol::class, 'series_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
