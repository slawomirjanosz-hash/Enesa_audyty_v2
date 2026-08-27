<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeave extends Model
{
    public const TYPES = [
        'annual' => 'Urlop wypoczynkowy',
        'on_demand' => 'Urlop na żądanie',
        'unpaid' => 'Urlop bezpłatny',
        'caregiver' => 'Urlop opiekuńczy',
        'childcare_14' => 'Opieka nad dzieckiem do 14 lat',
        'occasional' => 'Zwolnienie okolicznościowe',
        'force_majeure' => 'Zwolnienie z powodu siły wyższej',
        'training' => 'Urlop szkoleniowy',
        'maternity' => 'Urlop macierzyński',
        'parental' => 'Urlop rodzicielski',
        'paternity' => 'Urlop ojcowski',
        'childcare_leave' => 'Urlop wychowawczy',
        'sick_leave' => 'Zwolnienie chorobowe (L4)',
    ];

    protected $fillable = ['user_id', 'type', 'start_date', 'end_date', 'days', 'notes', 'created_by'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
