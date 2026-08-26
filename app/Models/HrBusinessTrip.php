<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrBusinessTrip extends Model
{
    protected $fillable = ['user_id', 'vehicle_id', 'purpose', 'departure_at', 'return_at', 'days', 'travel_hours', 'origin', 'destination', 'distance_km', 'distance_source', 'vehicle_type', 'vehicle_name', 'registration_number', 'toll_cost', 'notes', 'created_by'];

    protected $casts = ['departure_at' => 'datetime', 'return_at' => 'datetime', 'travel_hours' => 'decimal:2', 'distance_km' => 'decimal:2', 'toll_cost' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(HrVehicle::class, 'vehicle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
