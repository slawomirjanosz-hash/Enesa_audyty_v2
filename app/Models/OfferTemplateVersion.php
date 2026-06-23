<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferTemplateVersion extends Model
{
    protected $fillable = [
        'offer_template_type_id',
        'version_number',
        'html_content',
        'is_current',
        'uploaded_by',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function offerTemplateType(): BelongsTo
    {
        return $this->belongsTo(OfferTemplateType::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
