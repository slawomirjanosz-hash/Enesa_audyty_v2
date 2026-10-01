<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditFinancialEntry extends Model
{
    protected $fillable = ['audit_id', 'type', 'name', 'document_number', 'entry_date', 'amount', 'status', 'notes', 'created_by', 'finance_group_id', 'supplier', 'supplier_company_id', 'payment_date', 'source', 'import_row_order', 'import_fingerprint'];

    protected $casts = ['entry_date' => 'date', 'payment_date' => 'date', 'amount' => 'decimal:2'];

    public function financeGroup(): BelongsTo
    {
        return $this->belongsTo(AuditFinanceGroup::class, 'finance_group_id');
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
