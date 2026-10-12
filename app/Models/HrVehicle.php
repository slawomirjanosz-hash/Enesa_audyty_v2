<?php

namespace App\Models;

use App\Support\HrAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrVehicle extends Model
{
    protected $fillable = ['user_id', 'created_by', 'type', 'name', 'registration_number', 'make_model', 'is_active', 'fuel_card_number', 'responsible_person', 'usage_location', 'vat_percent', 'insurer', 'insurance_expires_at', 'vat_26', 'cit_percent', 'gps', 'logbook_entry_at', 'service_book_entry_at', 'service_book_status', 'inspection_due_at', 'registration_document'];

    protected $casts = ['is_active' => 'boolean', 'created_by' => 'integer', 'user_id' => 'integer', 'vat_26' => 'boolean', 'gps' => 'boolean', 'insurance_expires_at' => 'date', 'inspection_due_at' => 'date', 'logbook_entry_at' => 'date', 'service_book_entry_at' => 'date'];

    public const COLUMNS = [
        'type' => 'Typ', 'name' => 'Nazwa', 'registration_number' => 'Nr rejestracyjny',
        'make_model' => 'Marka / model', 'fuel_card_number' => 'Nr karty Orlen',
        'responsible_person' => 'Osoba odpowiedzialna', 'usage_location' => 'Miejsce użytkowania',
        'vat_percent' => 'VAT', 'insurer' => 'Ubezpieczyciel', 'insurance_expires_at' => 'Koniec ubezpieczenia',
        'vat_26' => 'VAT 26', 'cit_percent' => 'CIT', 'gps' => 'GPS',
        'logbook_entry_at' => 'Książka ewidencji — ostatni wpis', 'service_book_entry_at' => 'Książka serwisowa — ostatni wpis',
        'inspection_due_at' => 'Następny przegląd', 'registration_document' => 'Dowód rejestracyjny',
    ];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('type', 'company')->orWhere(fn (Builder $private) => $private->where('type', 'private')->where(fn (Builder $owner) => $owner->where('created_by', $user->id)->orWhere(fn (Builder $legacy) => $legacy->whereNull('created_by')->where('user_id', $user->id)))));
    }

    public function canEdit(User $user): bool
    {
        return $this->type === 'private'
            ? ($this->created_by ?? $this->user_id) === $user->id
            : HrAccess::canViewTeam($user);
    }

    public function deadlineState(string $field): string
    {
        $date = $this->getAttribute($field);
        if (! $date) {
            return '';
        }
        $days = (int) today()->diffInDays($date->copy()->startOfDay(), false);

        return $days < 0 ? 'overdue' : ($days <= 7 ? 'urgent' : ($days <= 14 ? 'soon' : ''));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
