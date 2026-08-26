<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id', 'subject_label',
        'changes', 'route_name', 'url', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function actionLabel(): string
    {
        return [
            'created' => 'Utworzono',
            'updated' => 'Zmieniono',
            'deleted' => 'Usunięto',
            'restored' => 'Przywrócono',
            'login' => 'Logowanie',
            'logout' => 'Wylogowanie',
        ][$this->action] ?? ucfirst($this->action);
    }

    public function areaLabel(): string
    {
        $class = class_basename((string) $this->auditable_type);

        return [
            'Company' => 'CRM / firma', 'CrmOpportunity' => 'CRM / szansa', 'CrmActivity' => 'CRM / aktywność',
            'Task' => 'Zadanie', 'Project' => 'Projekt', 'ProjectFinancialEntry' => 'Finanse projektu',
            'ProjectRequirement' => 'Materiały i usługi', 'ProjectFinanceGroup' => 'Grupa finansowa',
            'Document' => 'Dokument', 'Offer' => 'Oferta', 'OfferRequest' => 'Zapytanie ofertowe',
            'Audit' => 'Audyt', 'User' => 'Użytkownik', 'Role' => 'Rola', 'Permission' => 'Uprawnienie',
            'ImportantContact' => 'CRM / ważny kontakt',
            'HrBusinessTrip' => 'HR / delegacja', 'HrAttendance' => 'HR / lista obecności',
            'HrVehicle' => 'HR / samochód',
        ][$class] ?? ($class ?: 'System');
    }
}
