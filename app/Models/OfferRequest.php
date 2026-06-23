<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfferRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'created_by_id',
        'offer_form_version_id',
        'status',
        'form_responses',
        'completion_percent',
        'tresc',
        'notes',
    ];

    protected $casts = [
        'form_responses'     => 'array',
        'completion_percent' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function offerFormVersion(): BelongsTo
    {
        return $this->belongsTo(OfferFormVersion::class);
    }

    public function offerMessages(): HasMany
    {
        return $this->hasMany(OfferMessage::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
