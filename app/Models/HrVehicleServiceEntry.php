<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrVehicleServiceEntry extends Model
{
    public const TYPES = [
        'engine_oil' => 'Wymiana oleju silnikowego',
        'timing' => 'Wymiana rozrządu',
        'gearbox_oil' => 'Wymiana oleju w skrzyni biegów',
        'other' => 'Inna czynność',
    ];

    protected $fillable = ['performed_on', 'mileage', 'type', 'description'];

    protected $casts = ['performed_on' => 'date', 'mileage' => 'integer'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
