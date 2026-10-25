<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseInvoice extends Model
{
    use SoftDeletes;

    public const METHODS = ['unknown' => 'Nieustalona', 'card' => 'Karta', 'cash' => 'Gotówka', 'transfer' => 'Przelew'];

    public const SOURCES = ['unknown' => 'Nieustalone', 'company' => 'Firmowe', 'personal' => 'Prywatne'];

    public const PAYMENTS = ['unknown' => 'Nieustalone', 'unpaid' => 'Nieopłacona', 'paid' => 'Opłacona', 'partial' => 'Częściowo opłacona', 'settled' => 'Rozliczona — zamknięta'];

    protected $guarded = ['id'];

    protected $casts = ['purchase_date' => 'date', 'net_amount' => 'decimal:2', 'gross_amount' => 'decimal:2', 'is_unassigned' => 'boolean', 'settled_at' => 'datetime', 'revision' => 'integer'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function statusLabel(): string
    {
        return $this->settled_at ? 'Rozliczona' : ($this->is_unassigned ? 'Do przypisania' : 'Do rozliczenia');
    }
}
