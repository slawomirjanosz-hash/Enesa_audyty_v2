<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditorCompanyAccess extends Model
{
    protected $fillable = [
        'auditor_id', 'company_id', 'can_view_dashboard', 'can_view_audits',
        'can_view_offer_requests', 'can_view_offers', 'can_view_offer_prices',
        'can_view_documents', 'can_view_chat',
    ];

    protected $casts = [
        'can_view_dashboard' => 'boolean',
        'can_view_audits' => 'boolean',
        'can_view_offer_requests' => 'boolean',
        'can_view_offers' => 'boolean',
        'can_view_offer_prices' => 'boolean',
        'can_view_documents' => 'boolean',
        'can_view_chat' => 'boolean',
    ];

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
