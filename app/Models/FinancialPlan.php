<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $year Legacy year retained for existing plans and integrations.
 */
class FinancialPlan extends Model
{
    protected $fillable = ['financial_department_id', 'name', 'year', 'start_date', 'end_date', 'opening_balance', 'approved_snapshot', 'approved_by', 'approved_at', 'revision'];

    protected $casts = ['year' => 'integer', 'start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'opening_balance' => 'encrypted', 'approved_snapshot' => 'encrypted:array', 'approved_at' => 'datetime'];

    public function periodStart(): CarbonImmutable
    {
        return $this->start_date ?? CarbonImmutable::create($this->year, 1, 1);
    }

    public function periodEnd(): CarbonImmutable
    {
        return ($this->end_date ?? CarbonImmutable::create($this->year, 12, 1))->endOfMonth();
    }

    public function periodLabel(): string
    {
        return $this->periodStart()->format('m.Y').' – '.$this->periodEnd()->format('m.Y');
    }

    public function months(): array
    {
        $months = [];
        for ($date = $this->periodStart()->startOfMonth(); $date <= $this->periodEnd(); $date = $date->addMonth()) {
            $months[count($months) + 1] = $date;
        }

        return $months;
    }

    public function scopeOverlapping(Builder $query, self $plan): Builder
    {
        return $query->where(function ($query) use ($plan) {
            $query->whereDate('start_date', '<=', $plan->periodEnd()->toDateString())
                ->orWhere(fn ($legacy) => $legacy->whereNull('start_date')->where('year', '<=', $plan->periodEnd()->year));
        })
            ->where(function ($query) use ($plan) {
                $query->whereDate('end_date', '>=', $plan->periodStart()->toDateString())
                    ->orWhere(fn ($legacy) => $legacy->whereNull('end_date')->where('year', '>=', $plan->periodStart()->year));
            });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(FinancialDepartment::class, 'financial_department_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinancialPlanLine::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(FinancialPlanChange::class);
    }
}
