<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPlanLine extends Model
{
    public const KINDS = ['project' => 'Projekt', 'offer' => 'Oferta', 'revenue' => 'Planowana sprzedaż', 'overhead' => 'Pozostały koszt działu', 'person' => 'Osoba i samochód'];

    public const QUALIFICATIONS = ['excluded' => 'Wykluczona', 'reserve' => 'Rezerwowa', 'real' => 'Realna', 'confirmed' => 'Potwierdzona'];

    public const ACCURACY = ['initial' => 'Szacunek wstępny', 'detailed' => 'Wycena szczegółowa', 'approved' => 'Budżet zatwierdzony'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'probability' => 'integer',
        'revenue_override' => 'encrypted', 'cost_override' => 'encrypted',
        'salary_cost' => 'encrypted', 'car_cost' => 'encrypted', 'risk_reserve' => 'encrypted',
        'actual_salary_cost' => 'encrypted', 'actual_car_cost' => 'encrypted', 'actual_cost' => 'encrypted',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
