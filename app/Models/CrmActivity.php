<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    protected $fillable = [
        'company_id',
        'crm_opportunity_id',
        'offer_id',
        'user_id',
        'type',
        'description',
        'metadata',
        'occurred_on',
        'performed_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_on' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function crmOpportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by')->withTrashed();
    }
}
