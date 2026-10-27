<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialDepartment extends Model
{
    protected $fillable = ['name', 'owner_id'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'financial_department_members');
    }

    public function planners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'financial_department_access');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(FinancialPlan::class);
    }
}
