<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferFormVersion extends Model
{
    protected $fillable = [
        'offer_form_id',
        'version_number',
        'html_content',
        'is_current',
        'uploaded_by',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function offerForm(): BelongsTo
    {
        return $this->belongsTo(OfferForm::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
