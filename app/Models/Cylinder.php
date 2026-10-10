<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cylinder extends Model
{
    public const PARAMETER_LABELS = [
        'name' => 'Nazwa urządzenia',
        'manufacturer_mark' => 'Znak wytwórczy',
        'manufactured_year' => 'Rok produkcji',
        'serial_number' => 'Numer fabryczny',
        'inventory_number' => 'Numer ewidencyjny u eksploatującego',
        'working_medium' => 'Czynnik roboczy',
        'temperature_min_c' => 'Temperatura dopuszczalna min. [°C]',
        'temperature_max_c' => 'Temperatura dopuszczalna max. [°C]',
        'capacity_litres' => 'Pojemność [dm³]',
        'working_pressure_bar' => 'Ciśnienie robocze [bar]',
        'test_pressure_bar' => 'Ciśnienie próbne [bar]',
        'tare_or_gross_mass_kg' => 'Tara lub masa brutto [kg]',
        'net_mass_kg' => 'Masa netto [kg]',
        'stamped_empty_mass_kg' => 'Masa butli bez osprzętu — wybita [kg]',
        'filling_mass_symbol' => 'Symbol masy przewożonej / dopuszczalnej',
        'equipment_type' => 'Osprzęt — typ',
        'equipment_mark' => 'Osprzęt — znak Π lub CE',
        'manufacturer' => 'Producent (informacja dodatkowa)',
    ];

    public const DECIMAL_PARAMETERS = ['temperature_min_c', 'temperature_max_c', 'capacity_litres', 'working_pressure_bar', 'test_pressure_bar', 'tare_or_gross_mass_kg', 'net_mass_kg', 'stamped_empty_mass_kg'];

    protected $fillable = ['company_id', 'name', 'device_type', 'manufacturer_mark', 'inventory_number', 'working_medium', 'temperature_min_c', 'temperature_max_c', 'test_pressure_bar', 'tare_or_gross_mass_kg', 'net_mass_kg', 'stamped_empty_mass_kg', 'filling_mass_symbol', 'equipment_type', 'equipment_mark', 'serial_number', 'manufacturer', 'type', 'manufactured_year', 'capacity_litres', 'working_pressure_bar', 'notes', 'archived_at'];

    protected $attributes = ['device_type' => 'butla'];

    public function getNameAttribute($value): ?string
    {
        return $value ?? $this->attributes['type'] ?? null;
    }

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

    public function photo(): HasOne
    {
        return $this->hasOne(CylinderPhoto::class)->whereNull('cylinder_inspection_id');
    }

    public function dueStatus(): string
    {
        $due = $this->latestInspection?->next_due_at;
        if (! $due || $this->archived_at) {
            return 'neutral';
        }

        return $due->lt(today()) ? 'late' : ($due->lte(today()->addMonthNoOverflow()) ? 'soon' : 'neutral');
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
