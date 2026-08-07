<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number', 'name', 'company_id', 'manager_id', 'status', 'start_date',
        'end_date', 'contract_value', 'description', 'public_gantt_token', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'contract_value' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('project_position')->orderBy('id');
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(ProjectFinancialEntry::class)->orderBy('entry_date');
    }

    public function financeGroups(): HasMany
    {
        return $this->hasMany(ProjectFinanceGroup::class)->orderBy('name');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProjectRequirement::class)->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderByDesc('created_at');
    }

    public function totalCosts(): float
    {
        return (float) $this->financialEntries->where('type', 'cost')->whereIn('status', ['issued', 'paid'])->sum('amount');
    }

    public function plannedCosts(): float
    {
        return (float) $this->financialEntries->where('type', 'cost')->where('status', 'planned')->sum('amount');
    }

    public function totalInvoiced(): float
    {
        return (float) $this->financialEntries->where('type', 'invoice')->whereIn('status', ['issued', 'paid'])->sum('amount');
    }

    public function plannedInvoiced(): float
    {
        return (float) $this->financialEntries->where('type', 'invoice')->where('status', 'planned')->sum('amount');
    }

    public function result(): float
    {
        return $this->totalInvoiced() - $this->totalCosts();
    }
}
