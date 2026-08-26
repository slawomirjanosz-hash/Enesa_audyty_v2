<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrBusinessTrip extends Model
{
    protected $fillable = ['user_id', 'vehicle_id', 'purpose', 'departure_at', 'outbound_arrival_at', 'return_departure_at', 'return_at', 'days', 'travel_hours', 'outbound_travel_hours', 'return_travel_hours', 'origin', 'destination', 'distance_km', 'distance_source', 'km_rate', 'mileage_amount', 'diet_rate', 'diet_amount', 'vehicle_type', 'vehicle_name', 'registration_number', 'toll_cost', 'accommodation_cost', 'other_cost', 'total_amount', 'notes', 'created_by'];

    protected $casts = ['departure_at' => 'datetime', 'outbound_arrival_at' => 'datetime', 'return_departure_at' => 'datetime', 'return_at' => 'datetime', 'travel_hours' => 'decimal:2', 'outbound_travel_hours' => 'decimal:2', 'return_travel_hours' => 'decimal:2', 'distance_km' => 'decimal:2', 'km_rate' => 'decimal:4', 'mileage_amount' => 'decimal:2', 'diet_rate' => 'decimal:2', 'diet_amount' => 'decimal:2', 'toll_cost' => 'decimal:2', 'accommodation_cost' => 'decimal:2', 'other_cost' => 'decimal:2', 'total_amount' => 'decimal:2'];

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
