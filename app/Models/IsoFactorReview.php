<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoFactorReview extends Model
{
    public const DOCUMENT_TITLE = '4.1 Kontekst organizacji Czynniki';

    public const SWOT_FIELDS = [
        'strengths' => 'S — Mocne strony',
        'weaknesses' => 'W — Słabe strony',
        'opportunities' => 'O — Szanse',
        'threats' => 'T — Zagrożenia',
        'conclusions' => 'Wnioski i zalecane działania',
    ];

    protected $guarded = ['id'];

    protected $casts = ['swot' => 'array', 'answers' => 'array', 'basis' => 'array', 'auditor_changes' => 'array', 'client_changes' => 'array', 'client_approval' => 'array', 'auditor_approval' => 'array', 'issuer' => 'array', 'lock_version' => 'integer'];
}
