<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cylinder extends Model
{
    protected $fillable = ['company_id', 'serial_number', 'manufacturer', 'type', 'manufactured_year', 'capacity_litres', 'working_pressure_bar', 'notes', 'archived_at'];

    protected $casts = ['archived_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(CylinderInspection::class);
    }

    public function latestInspection(): HasOne
    {
        return $this->hasOne(CylinderInspection::class)->ofMany(['inspected_at' => 'max', 'id' => 'max']);
    }
}
