<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFinancialEntry extends Model
{
    protected $fillable = [
        'project_id', 'finance_group_id', 'type', 'name', 'document_number', 'supplier', 'supplier_company_id',
        'entry_date', 'payment_date', 'amount', 'status', 'source', 'import_row_order',
        'import_fingerprint', 'notes', 'created_by',
    ];

    protected $casts = ['entry_date' => 'date', 'payment_date' => 'date', 'amount' => 'decimal:2'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function financeGroup(): BelongsTo
    {
        return $this->belongsTo(ProjectFinanceGroup::class, 'finance_group_id');
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }
}
