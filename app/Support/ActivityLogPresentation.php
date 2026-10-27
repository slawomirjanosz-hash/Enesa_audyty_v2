<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\CompanySettings;
use App\Models\HrVehicle;
use App\Models\User;
use App\Services\ActivityLogService;
use Carbon\Carbon;

class ActivityLogPresentation
{
    private array $userNames = [];

    private array $vehicleRegistrations = [];

    private const USER_FIELDS = ['assigned_to', 'user_id', 'created_by', 'manager_id', 'responsible_user_id', 'supervisor_id', 'owner_id', 'deleted_by', 'settled_by', 'updated_by'];

    private const USER_LIST_FIELDS = ['user_ids', 'participants', 'hr_vehicle_manager_ids'];

    private const LABELS = [
        'hr_km_rate' => 'Stawka za kilometr delegacji', 'hr_diet_rate' => 'Dieta za dobę delegacji',
        'hr_vehicle_manager_ids' => 'Osoby zarządzające samochodami',
        'name' => 'Nazwa', 'title' => 'Tytuł', 'description' => 'Opis', 'notes' => 'Uwagi',
        'email' => 'Adres e-mail', 'phone' => 'Telefon', 'address' => 'Adres', 'city' => 'Miejscowość',
        'postcode' => 'Kod pocztowy', 'nip' => 'NIP', 'website' => 'Strona internetowa',
        'short_name' => 'Skrócona nazwa firmy', 'tagline' => 'Opis firmy', 'offer_tagline' => 'Opis na ofertach',
        'primary_color' => 'Kolor firmowy', 'enabled_modules' => 'Włączone moduły', 'welcome_page_mode' => 'Strona powitalna',
        'status' => 'Status', 'priority' => 'Priorytet', 'progress' => 'Postęp', 'number' => 'Numer',
        'assigned_to' => 'Osoba odpowiedzialna', 'user_id' => 'Użytkownik',
        'created_by' => 'Autor', 'company_id' => 'Firma — ID', 'project_id' => 'Projekt — ID',
        'responsible_user_id' => 'Przypisany pracownik', 'supervisor_id' => 'Przełożony', 'owner_id' => 'Kupujący',
        'user_ids' => 'Osoby odpowiedzialne za kolumnę', 'updated_by' => 'Zmienił(a)',
        'audit_id' => 'Audyt — ID', 'manager_id' => 'Kierownik — ID', 'supplier_id' => 'Dostawca — ID',
        'due_date' => 'Termin wykonania', 'start_date' => 'Data rozpoczęcia', 'end_date' => 'Data zakończenia',
        'is_active' => 'Aktywne', 'is_blocked' => 'Zablokowane', 'blocked_at' => 'Data zablokowania',
        'email_verified_at' => 'Data potwierdzenia e-maila', 'last_seen_at' => 'Ostatnia aktywność',
        'failed_login_attempts' => 'Nieudane próby logowania', 'failed_login_at' => 'Ostatnie nieudane logowanie',
        'login_locked_until' => 'Blokada logowania do', 'last_login_at' => 'Ostatnie logowanie',
        'amount' => 'Kwota', 'net_amount' => 'Kwota netto', 'gross_amount' => 'Kwota brutto',
        'unit_price' => 'Cena jednostkowa', 'quantity' => 'Ilość', 'unit' => 'Jednostka',
        'km_rate' => 'Stawka za kilometr', 'diet_rate' => 'Stawka diety', 'distance_km' => 'Dystans',
        'destination' => 'Cel podróży', 'purpose' => 'Cel wyjazdu', 'vehicle_id' => 'Samochód — ID',
        'departure_at' => 'Wyjazd', 'return_at' => 'Powrót', 'type' => 'Rodzaj',
        'original_filename' => 'Nazwa pliku', 'file_size' => 'Rozmiar pliku (bajty)',
        'deleted_at' => 'Data usunięcia', 'accepted_at' => 'Data akceptacji',
    ];

    public function fieldLabel(string $field): string
    {
        return self::LABELS[$field] ?? HrVehicle::COLUMNS[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    public function subject(ActivityLog $log): string
    {
        $subject = $log->subject_label ?: '—';
        if ($log->auditable_type === HrVehicle::class && $log->auditable_id) {
            $id = $log->auditable_id;
            if (! array_key_exists($id, $this->vehicleRegistrations)) {
                $this->vehicleRegistrations[$id] = HrVehicle::whereKey($id)->value('registration_number');
            }
            $registration = $this->vehicleRegistrations[$id];
            if ($registration && ! str_contains($subject, $registration)) {
                return $subject.' · nr rej. obecnie: '.$registration;
            }
        }

        return $subject;
    }

    public function context(ActivityLog $log): string
    {
        if ($log->auditable_type === HrVehicle::class && array_intersect(['responsible_person', 'responsible_user_id', 'user_id'], array_keys($this->visibleChanges($log)))) {
            return 'Zmiana przypisania samochodu';
        }

        return match ($log->route_name) {
            'hr.settings.update' => 'Ustawienia rozliczania delegacji i samochodów',
            'settings.company.update' => 'Dane i ustawienia firmy',
            default => $log->action === 'updated' ? 'Edycja danych' : $log->actionLabel(),
        };
    }

    public function value(string $field, mixed $value): string
    {
        if (preg_match('/password|token|secret|signature_data|avatar_data|logo_data/i', $field)) {
            return '[dane chronione]';
        }
        if ($value === null || $value === '') {
            return 'Brak wartości';
        }
        if (in_array($field, self::USER_FIELDS, true) && is_numeric($value)) {
            $id = (int) $value;
            $this->userNames[$id] ??= User::withTrashed()->whereKey($id)->value('name') ?: 'Usunięty użytkownik';

            return $this->userNames[$id].' (#'.$id.')';
        }
        if (is_bool($value) || in_array($field, ['vat_26', 'gps', 'registration_document_present', 'is_active', 'is_blocked'], true)) {
            return $value ? 'Tak' : 'Nie';
        }
        if (is_string($value) && str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (is_array($value)) {
            if ($value === []) {
                return $field === 'user_ids' ? 'Wszyscy uprawnieni za samochody (domyślnie)' : 'Brak pozycji';
            }
            if (in_array($field, self::USER_LIST_FIELDS, true)) {
                return implode("\n", array_map(fn ($id) => $this->value('user_id', $id), $value));
            }

            return implode("\n", array_map(fn ($item) => $field === 'enabled_modules' && is_string($item)
                ? (CompanySettings::APP_MODULES[$item] ?? $item)
                : (is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $item), $value));
        }
        if (is_numeric($value) && in_array($field, ['hr_km_rate', 'km_rate', 'hr_diet_rate', 'diet_rate', 'amount', 'net_amount', 'gross_amount', 'unit_price'], true)) {
            $precision = in_array($field, ['hr_km_rate', 'km_rate'], true) ? max(2, min(4, strlen(rtrim(explode('.', (string) $value)[1] ?? '', '0')))) : 2;

            return number_format((float) $value, $precision, ',', ' ').(in_array($field, ['hr_km_rate', 'km_rate'], true) ? ' zł/km' : ' zł');
        }
        if (is_numeric($value) && in_array($field, ['vat_percent', 'cit_percent', 'progress'], true)) {
            return rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ',').' %';
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T].*)?$/', $value) && preg_match('/(_at|_date|_until)$/', $field)) {
            try {
                return Carbon::parse($value)->format(strlen($value) === 10 ? 'd.m.Y' : 'd.m.Y H:i:s');
            } catch (\Throwable) {
                // Preserve unparseable historical values instead of guessing.
            }
        }
        if ($field === 'priority') {
            return ['low' => 'Niski', 'medium' => 'Średni', 'high' => 'Wysoki', 'urgent' => 'Pilny'][$value] ?? (string) $value;
        }
        if ($field === 'status') {
            return ['todo' => 'Do zrobienia', 'in_progress' => 'W trakcie', 'done' => 'Zrobione', 'active' => 'Aktywny', 'cancelled' => 'Anulowane', 'completed' => 'Zakończone', 'draft' => 'W przygotowaniu'][$value] ?? (string) $value;
        }

        return (string) $value;
    }

    public function visibleChanges(ActivityLog $log): array
    {
        $excluded = ActivityLogService::HIDDEN_FIELDS;
        if ($log->auditable_type === User::class) {
            $excluded = [...$excluded, ...ActivityLogService::VOLATILE_USER_FIELDS];
        }

        $changes = array_filter($log->changes ?? [], fn ($field) => ! in_array($field, $excluded, true)
            && ! preg_match('/password|token|secret|signature_data|avatar_data|logo_data/i', $field), ARRAY_FILTER_USE_KEY);

        return array_map(fn ($change) => is_array($change) && (array_key_exists('old', $change) || array_key_exists('new', $change))
            ? $change : ['old' => null, 'new' => $change, 'old_display' => 'Brak zapisanej poprzedniej wartości'], $changes);
    }

    public function changeValue(string $field, array $change, string $side): string
    {
        return $change[$side.'_display'] ?? $this->value($field, $change[$side] ?? null);
    }

    public function indexData(ActivityLog $log): array
    {
        $changes = $this->visibleChanges($log);
        $text = [$this->subject($log), $log->areaLabel(), $log->actionLabel(), $log->action, $log->route_name, $this->context($log),
            $log->user?->name ?? $log->actor_name, $log->user?->email, $log->created_at?->format('d.m.Y H:i:s')];
        foreach ($changes as $field => $change) {
            $text = [...$text, $field, $this->fieldLabel($field), $this->changeValue($field, $change, 'old'), $this->changeValue($field, $change, 'new')];
        }

        return ['search_text' => mb_strtolower(implode("\n", array_filter($text, fn ($value) => $value !== null))),
            'actor_name' => $log->actor_name ?? $log->user?->name,
            'is_noise' => $log->action === 'updated' && $log->auditable_type === User::class && ! empty($log->changes) && $changes === []];
    }

    public function snapshotUserNames(ActivityLog $log): void
    {
        $changes = $log->changes ?? [];
        foreach ($this->visibleChanges($log) as $field => $change) {
            if (in_array($field, [...self::USER_FIELDS, ...self::USER_LIST_FIELDS], true)) {
                $changes[$field] = $change;
                foreach (['old', 'new'] as $side) {
                    $changes[$field][$side.'_display'] = $change[$side.'_display'] ?? $this->value($field, $change[$side] ?? null);
                }
            }
        }
        $log->changes = $changes ?: null;
    }
}
