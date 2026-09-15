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

    public function videos(): HasMany
    {
        return $this->hasMany(CylinderVideo::class);
    }

    public function conditionStatus(): string
    {
        if ($this->archived_at) {
            return 'archived';
        }
        $inspection = $this->latestInspection;
        if (! $inspection) {
            return 'unknown';
        }
        if (in_array($inspection->result, ['defects_found', 'further_review'], true)) {
            return 'problem';
        }
        $due = $inspection->next_due_at;
        if (! $due) {
            return 'unknown';
        }
        if ($due->lt(today())) {
            return 'overdue';
        }
        if ($due->lte(today()->addMonthNoOverflow())) {
            return 'soon';
        }

        return $inspection->result === 'no_findings' ? 'ok' : 'unknown';
    }

    public function conditionLabel(): string
    {
        return ['problem' => 'Problemy / wymaga oceny', 'overdue' => 'Po terminie', 'soon' => 'Termin w ciągu miesiąca', 'ok' => 'Bez uwag — termin ważny', 'unknown' => 'Brak oceny lub terminu', 'archived' => 'Archiwum'][$this->conditionStatus()];
    }
}
