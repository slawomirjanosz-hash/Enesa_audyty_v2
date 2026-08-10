<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectRequirement extends Model
{
    protected $fillable = [
        'project_id', 'type', 'name', 'description', 'quantity', 'unit',
        'estimated_cost', 'supplier', 'supplier_company_id', 'status', 'needed_by', 'responsible_id', 'created_by',
    ];

    protected $casts = ['quantity' => 'decimal:2', 'estimated_cost' => 'decimal:2', 'needed_by' => 'date'];

    protected static function booted(): void
    {
        static::saved(function (self $requirement): void {
            if ($requirement->status === 'purchased') {
                $requirement->syncPurchasedFinancialEntry();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    public function financialEntry(): HasOne
    {
        return $this->hasOne(ProjectFinancialEntry::class, 'project_requirement_id');
    }

    public function formattedQuantity(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantity, 2, ',', ' '), '0'), ',');
    }

    public function displayUnit(): string
    {
        $unit = trim((string) $this->unit);

        return $unit === '' || is_numeric(str_replace(',', '.', $unit))
            ? ($this->type === 'material' ? 'szt.' : 'usł.')
            : $unit;
    }

    public function unitCost(): ?float
    {
        if ($this->estimated_cost === null || (float) $this->quantity <= 0) {
            return null;
        }

        return round((float) $this->estimated_cost / (float) $this->quantity, 2);
    }

    public function syncPurchasedFinancialEntry(): ProjectFinancialEntry
    {
        $entry = $this->financialEntry()->firstOrNew();

        if (! $entry->exists) {
            $entry->fill([
                'project_id' => $this->project_id,
                'project_requirement_id' => $this->id,
                'document_number' => 'MAT/'.$this->id,
                'entry_date' => now()->toDateString(),
                'status' => 'issued',
                'source' => 'requirement',
                'created_by' => $this->created_by,
            ]);
        }

        $entry->fill([
            'type' => 'cost',
            'name' => $this->name,
            'supplier' => $this->supplierCompany?->name ?? $this->supplier,
            'supplier_company_id' => $this->supplier_company_id,
            'amount' => (float) ($this->estimated_cost ?? 0),
            'notes' => trim(implode("\n", array_filter([
                'Automatycznie z materiałów i usług: '.$this->formattedQuantity().' '.$this->displayUnit().'.',
                $this->description,
            ]))),
        ]);
        $entry->save();

        return $entry;
    }
}
