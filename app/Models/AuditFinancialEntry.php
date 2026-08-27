<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditFinancialEntry extends Model
{
    protected $fillable = ['audit_id', 'type', 'name', 'document_number', 'entry_date', 'amount', 'status', 'notes', 'created_by'];

    protected $casts = ['entry_date' => 'date', 'amount' => 'decimal:2'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
