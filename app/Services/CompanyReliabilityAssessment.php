<?php

namespace App\Services;

use Carbon\Carbon;

class CompanyReliabilityAssessment
{
    public function assess(?array $lookup, array $finances): array
    {
        $checks = [];
        $fresh = $lookup && isset($lookup['checked_at']) && abs(now()->diffInMinutes(Carbon::parse($lookup['checked_at']))) <= 30;
        $checks[] = ['label' => 'Wykaz VAT', 'state' => $fresh && data_get($lookup, 'vat.state') === 'checked' ? (in_array(data_get($lookup, 'vat.status'), ['Czynny', 'Zwolniony']) ? 'clear' : 'warning') : 'unknown',
            'message' => $fresh && data_get($lookup, 'vat.state') === 'checked' ? 'Status: '.data_get($lookup, 'vat.status').'. Status VAT nie jest oceną wypłacalności; możliwe jest m.in. rozliczanie przez grupę VAT.' : 'Brak aktualnego wyniku. Kliknij „Sprawdź KRS i VAT”.'];
        foreach (['section4' => 'KRS dział 4 — wierzytelności i zaległości', 'section6' => 'KRS dział 6 — sytuacja prawna'] as $key => $label) {
            $section = data_get($lookup, 'krs.'.$key);
            $available = $fresh && data_get($lookup, 'krs.state') === 'checked' && is_array($section);
            $entries = $available && $this->hasContent($section);
            $checks[] = ['label' => $label, 'state' => ! $available ? 'unknown' : ($entries ? 'warning' : 'clear'),
                'message' => ! $available ? 'Nie uzyskano aktualnych danych tego działu.' : ($entries ? 'W odpisie są wpisy wymagające weryfikacji. Sam wpis nie potwierdza aktualnego zadłużenia ani upadłości; może dotyczyć np. zakończonego postępowania lub przekształcenia.' : 'Brak wpisów w tym dziale odpisu aktualnego. To nie potwierdza braku wszystkich długów.')];
        }
        if (data_get($lookup, 'krs.state') === 'identity_mismatch') {
            $checks[] = ['label' => 'Tożsamość firmy', 'state' => 'warning', 'message' => 'NIP odpisu KRS jest niezgodny z firmą. Nie użyto tego odpisu.'];
        }
        $checks[] = ['label' => 'KRZ — upadłość i restrukturyzacja', 'state' => 'unknown', 'message' => 'Brak automatycznej weryfikacji KRZ. Sprawdzenie KRS nie zastępuje KRZ.'];
        $checks[] = ['label' => 'Prywatne zobowiązania handlowe (BIG/KRD/ERIF)', 'state' => 'unknown', 'message' => 'Nie sprawdzono. Bilans nie potwierdza braku zaległych płatności.'];
        if (! $finances) {
            $checks[] = ['label' => 'Finanse', 'state' => 'unknown', 'message' => 'Brak danych. Wgraj sprawozdanie XML lub uzupełnij kwoty.'];
        }
        foreach ($finances as $row) {
            $missing = array_filter(['revenue', 'profit', 'equity', 'liabilities'], fn ($key) => ! isset($row[$key]) || $row[$key] === '');
            $negativeEquity = isset($row['equity']) && (float) $row['equity'] < 0;
            $loss = isset($row['profit']) && (float) $row['profit'] < 0;
            $stale = ($row['year'] ?? 0) < now()->year - 2;
            $state = $negativeEquity ? 'risk' : ($loss ? 'warning' : (($missing || $stale) ? 'unknown' : 'clear'));
            $message = $negativeEquity ? 'Ujemny kapitał własny — sygnał zagrożenia finansowego, nie stwierdzenie upadłości.' : ($loss ? 'Wykazano stratę netto.' : 'Nie stwierdzono straty netto ani ujemnego kapitału w podanych danych.');
            if ($missing) {
                $message .= ' Niepełne kwoty — ocena ograniczona.';
            }
            if ($stale) {
                $message .= ' Dane historyczne wymagają aktualizacji.';
            }
            $checks[] = ['label' => 'Finanse '.($row['year'] ?? ''), 'state' => $state, 'message' => $message];
        }
        $status = in_array('risk', array_column($checks, 'state'), true) ? 'red' : 'yellow';

        return ['status' => $status, 'checks' => $checks, 'summary' => $status === 'red'
            ? 'Wykryto sygnał zagrożenia finansowego. Pozostałe ograniczenia sprawdzenia opisano poniżej.'
            : 'Ocena ostrożna: sprawdzono dostępne źródła, ale zakres jest niepełny. Nie potwierdzono pełnej wiarygodności ani braku wszystkich długów.'];
    }

    private function hasContent(array $data): bool
    {
        foreach ($data as $value) {
            if (is_array($value) ? $this->hasContent($value) : ($value !== null && $value !== '' && $value !== false)) {
                return true;
            }
        }

        return false;
    }
}
