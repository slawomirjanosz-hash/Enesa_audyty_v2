<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'offer_number',
        'offer_slug',
        'offer_full_number',
        'offer_title',
        'status',
        'won_as',
        'company_id',
        'assigned_user_id',
        'created_by_id',
        'offer_template_version_id',
        'offer_request_id',
        'crm_opportunity_id',
        'kwota_netto',
        'valid_until',
        'notes',
        'additional_description',
        'content_subject',
        'content_scope',
        'content_deadline',
        'content_payment',
        'show_unit_prices',
        'price_sections',
        'text_sections',
        'delegations',
        'is_template',
    ];

    protected $casts = [
        'kwota_netto' => 'decimal:2',
        'valid_until' => 'date',
        'show_unit_prices' => 'boolean',
        'price_sections' => 'array',
        'text_sections' => 'array',
        'delegations' => 'array',
        'is_template' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function offerTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(OfferTemplateVersion::class);
    }

    public function offerRequest(): BelongsTo
    {
        return $this->belongsTo(OfferRequest::class);
    }

    public function crmOpportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class);
    }

    public function offerDelegation(): HasOne
    {
        return $this->hasOne(OfferDelegation::class);
    }

    public function offerMessages(): HasMany
    {
        return $this->hasMany(OfferMessage::class);
    }

    public function fullNumber(): string
    {
        return $this->offer_full_number
            ?? ($this->offer_number.($this->offer_slug ? '_'.$this->offer_slug : ''));
    }

    public static function generateNumber(bool $isTemplate = false): string
    {
        $now = now();
        $prefix = $isTemplate ? 'SZ' : 'OF';
        $companyShortName = CompanySettings::query()->first()?->offerShortName() ?? 'FI';
        $monthPrefix = $prefix.'_'.$companyShortName.'_'.$now->format('Ym');

        $maxSeq = static::withTrashed()
            ->where('offer_number', 'like', $monthPrefix.'%')
            ->pluck('offer_number')
            ->map(function (string $num): int {
                $parts = explode('_', $num);

                return (int) end($parts);
            })
            ->max() ?? 0;

        $seq = str_pad($maxSeq + 1, 3, '0', STR_PAD_LEFT);

        return $prefix.'_'.$companyShortName.'_'.$now->format('Ymd').'_'.$seq;
    }
}
