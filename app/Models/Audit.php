<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Audit extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'number', 'title', 'status', 'manager_id', 'start_date', 'end_date', 'contract_value', 'description', 'created_by', 'public_gantt_token'];

    public function financeGroups(): HasMany
    {
        return $this->hasMany(AuditFinanceGroup::class)->orderBy('name');
    }

    public function documentFolders(): HasMany
    {
        return $this->hasMany(AuditDocumentFolder::class)->orderBy('name');
    }

    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class)->orderBy('name');
    }

    public function totalInvoiced(): float
    {
        return (float) $this->financialEntries->where('type', 'invoice')->whereIn('status', ['issued', 'paid'])->sum('amount');
    }

    public function plannedInvoiced(): float
    {
        return (float) $this->financialEntries->where('type', 'invoice')->where('status', 'planned')->sum('amount');
    }

    public function totalCosts(): float
    {
        return (float) $this->financialEntries->where('type', 'cost')->whereIn('status', ['issued', 'paid'])->sum('amount');
    }

    public function plannedCosts(): float
    {
        return (float) $this->financialEntries->where('type', 'cost')->where('status', 'planned')->sum('amount');
    }

    public function result(): float
    {
        return $this->totalInvoiced() - $this->totalCosts();
    }

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'contract_value' => 'decimal:2'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
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
        return $this->hasMany(AuditFinancialEntry::class)->orderByDesc('entry_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderByDesc('created_at');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(AuditSurvey::class)->orderByDesc('created_at');
    }

    public function energyPassports(): HasMany
    {
        return $this->hasMany(EnergyPassport::class)->orderByDesc('created_at');
    }

    public function isoSectionDocuments(): HasMany
    {
        return $this->hasMany(IsoSectionDocument::class)->metadata()->orderByDesc('document_year')->orderByDesc('created_at');
    }

    public function isoImplementationResponses(): HasMany
    {
        return $this->hasMany(IsoImplementationResponse::class);
    }
}
