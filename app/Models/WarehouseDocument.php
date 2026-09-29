<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseDocument extends Model
{
    public const TYPES = ['receipt' => 'Przyjęcie (PZ)', 'issue' => 'Wydanie (WZ)', 'adjustment' => 'Inwentaryzacja / korekta (KOR)'];

    protected $guarded = ['id'];

    protected $hidden = ['submission_token', 'payload_hash'];

    protected $casts = ['document_date' => 'date'];

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseDocumentLine::class);
    }
}
