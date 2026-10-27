<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialPlan extends Model
{
    protected $fillable = ['financial_department_id', 'year', 'approved_snapshot', 'approved_by', 'approved_at', 'revision'];

    protected $casts = ['year' => 'integer', 'approved_snapshot' => 'encrypted:array', 'approved_at' => 'datetime'];

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
