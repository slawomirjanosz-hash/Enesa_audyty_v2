<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceCatalogItem extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'unit',
        'net_unit_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'net_unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
