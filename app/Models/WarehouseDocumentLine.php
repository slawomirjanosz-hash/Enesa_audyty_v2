<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseDocumentLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['quantity_before' => 'decimal:3', 'quantity_change' => 'decimal:3', 'quantity_after' => 'decimal:3', 'unit_cost' => 'decimal:2'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(WarehouseDocument::class, 'warehouse_document_id');
    }
}
