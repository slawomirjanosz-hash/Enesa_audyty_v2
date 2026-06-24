<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferSavedTemplate extends Model
{
    protected $fillable = [
        'name',
        'offer_id',
        'content_subject',
        'content_scope',
        'content_deadline',
        'content_payment',
        'price_sections',
        'created_by',
    ];

    protected $casts = [
        'price_sections' => 'array',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
