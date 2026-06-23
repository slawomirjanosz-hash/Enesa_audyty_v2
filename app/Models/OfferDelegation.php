<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferDelegation extends Model
{
    protected $fillable = [
        'offer_id',
        'km_do_klienta',
        'czas_dojazdu_min',
        'liczba_wyjazdow',
        'czy_kilkudniowy',
        'liczba_noc',
        'liczba_osob',
        'stawka_noc',
    ];

    protected $casts = [
        'czy_kilkudniowy' => 'boolean',
        'stawka_noc'      => 'decimal:2',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function kosztDelegacji(): float
    {
        $kosztyDojazdu = ($this->km_do_klienta ?? 0) * 2 * ($this->liczba_wyjazdow ?? 1) * 0.89;

        $kosztyNoclegu = $this->czy_kilkudniowy
            ? ($this->liczba_noc ?? 0) * ($this->liczba_osob ?? 1) * ($this->stawka_noc ?? 300.00)
            : 0;

        return round($kosztyDojazdu + $kosztyNoclegu, 2);
    }
}
