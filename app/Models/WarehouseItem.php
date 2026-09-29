<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WarehouseItem extends Model
{
    protected $fillable = ['sku', 'name', 'category', 'location', 'unit', 'description', 'minimum_stock'];

    protected $casts = ['quantity' => 'decimal:3', 'minimum_stock' => 'decimal:3', 'unit_cost' => 'decimal:2', 'is_active' => 'boolean', 'revision' => 'integer'];

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseDocumentLine::class);
    }

    public function latestReceiptLine(): HasOne
    {
        return $this->hasOne(WarehouseDocumentLine::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->whereHas('document', fn ($documents) => $documents->where('type', 'receipt'))
        );
    }
}
