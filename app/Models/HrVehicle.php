<?php

namespace App\Models;

use App\Support\HrAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrVehicle extends Model
{
    public function serviceEntries(): HasMany
    {
        return $this->hasMany(HrVehicleServiceEntry::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function missingFields(): array
    {
        $fields = self::COLUMNS;
        unset($fields['type']);
        $fields['registration_document_present'] = 'Dowód rejestracyjny — dostępny';
        $fields['service_book_status'] = 'Książka serwisowa — status';
        if ($this->service_book_status === 'missing') {
            unset($fields['service_book_entry_at']);
        }

        return array_filter($fields, function ($label, $field) {
            if ($field === 'responsible_person' && $this->responsibleUser) {
                return false;
            }
            $value = $this->getAttribute($field);

            return $value === null || (is_string($value) && trim($value) === '');
        }, ARRAY_FILTER_USE_BOTH);
    }

    public static function eligibleResponsibleUsers()
    {
        return User::query()->where('is_active', true)
            ->whereHas('roles')->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']));
    }

    protected $fillable = ['user_id', 'created_by', 'type', 'name', 'registration_number', 'make_model', 'is_active', 'fuel_card_number', 'responsible_person', 'usage_location', 'vat_percent', 'insurer', 'insurance_expires_at', 'vat_26', 'cit_percent', 'gps', 'logbook_entry_at', 'service_book_entry_at', 'service_book_status', 'inspection_due_at', 'registration_document', 'registration_document_present'];

    protected $casts = ['is_active' => 'boolean', 'created_by' => 'integer', 'responsible_user_id' => 'integer', 'user_id' => 'integer', 'vat_26' => 'boolean', 'gps' => 'boolean', 'registration_document_present' => 'boolean', 'insurance_expires_at' => 'date', 'inspection_due_at' => 'date', 'logbook_entry_at' => 'date', 'service_book_entry_at' => 'date'];

    public const SHORT_COLUMNS = [
        'registration_number' => 'Nr rej.', 'fuel_card_number' => 'Karta Orlen',
        'responsible_person' => 'Osoba odp.', 'usage_location' => 'Lokalizacja',
        'insurer' => 'Ubezp.', 'insurance_expires_at' => 'Koniec ubezp.',
        'logbook_entry_at' => 'Książka ewid. — ost. wpis',
        'service_book_entry_at' => 'Książka serw. — ost. wpis',
        'inspection_due_at' => 'Nast. przegląd', 'registration_document' => 'Dowód rej.',
    ];

    public const CHOICE_FIELDS = ['usage_location', 'fuel_card_number', 'insurer'];

    public static function choicesFor(User $user): array
    {
        // Include inactive vehicles so a card/location can be reused, never expose other private cars.
        $vehicles = static::visibleTo($user)->get(static::CHOICE_FIELDS);
        $choices = [];
        foreach (static::CHOICE_FIELDS as $field) {
            $choices[$field] = $vehicles->pluck($field)->filter(fn ($value) => $value !== null && $value !== '')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        }

        return $choices;
    }

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
