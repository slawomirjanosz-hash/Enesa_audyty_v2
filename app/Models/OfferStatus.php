<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferStatus extends Model
{
    public const BUILT_IN = [
        'w_toku' => 'W toku',
        'w_negocjacji' => 'W negocjacjach',
        'wygrana' => 'Wygrana',
        'przegrana' => 'Przegrana',
        'anulowana' => 'Anulowana',
        'zarchiwizowana' => 'Zarchiwizowana',
    ];

    protected $fillable = ['name', 'normalized_name'];

    public static function options(): array
    {
        return self::BUILT_IN + static::query()->orderBy('name')->pluck('name', 'name')->all();
    }
}
