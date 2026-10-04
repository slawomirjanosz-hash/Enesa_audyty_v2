<?php

namespace App\Services;

class FinancialHealthAssessment
{
    public const LABELS = ['clear' => 'OK w analizowanym zakresie', 'warning' => 'Wymaga uwagi', 'risk' => 'Zagrożenie', 'unknown' => 'Brak wystarczających danych'];

    public const RULES = 'Reguły pomocnicze v1: czerwony — ujemny kapitał własny; żółty — strata netto, zerowy kapitał, udział kapitału w sumie kapitału i zobowiązań poniżej 10%, spadek przychodów lub kapitału co najmniej 20%, spadek dodatniego zysku co najmniej 50% względem poprzedniego roku. Progi są umowne, nie branżowe ani ustawowe. Porównania wymagają porównywalnych okresów. Brak danych lub dane starsze niż dwa lata wykluczają ocenę OK. Analiza nie bada przepływów, płynności ani wszystkich długów.';

    public function assess(array $rows): array
    {
        $periods = [];
        foreach ($rows as $index => $row) {
            $fields = [];
            $findings = [];
            $state = 'clear';
            $add = function (string $level, array $keys, string $message) use (&$state, &$fields, &$findings) {
                $rank = ['clear' => 0, 'unknown' => 1, 'warning' => 2, 'risk' => 3];
                if ($rank[$level] > $rank[$state]) {
                    $state = $level;
                }
                foreach ($keys as $key) {
                    if ($rank[$level] > $rank[$fields[$key] ?? 'clear']) {
                        $fields[$key] = $level;
                    }
                }
                $findings[] = ['state' => $level, 'message' => $message];
            };
            $values = [];
            foreach (['revenue', 'profit', 'equity', 'liabilities'] as $key) {
                $values[$key] = isset($row[$key]) && is_numeric($row[$key]) ? (float) $row[$key] : null;
                if ($values[$key] === null) {
                    $add('unknown', [$key], 'Brak kwoty: '.['revenue' => 'przychody', 'profit' => 'wynik netto', 'equity' => 'kapitał własny', 'liabilities' => 'zobowiązania'][$key].'.');
                }
            }
            if (empty($row['year']) || (int) $row['year'] < now()->year - 2) {
                $add('unknown', ['year'], 'Brak aktualnego roku sprawozdawczego — nie oceniaj bieżącej sytuacji wyłącznie na tej podstawie.');
            }
            if ($values['equity'] !== null && $values['equity'] < 0) {
                $add('risk', ['equity'], 'Ujemny kapitał własny — istotny sygnał zagrożenia finansowego. Wymaga wyjaśnienia przed współpracą; nie jest stwierdzeniem upadłości.');
            } elseif ($values['equity'] === 0.0) {
                $add('warning', ['equity'], 'Kapitał własny wynosi zero — brak bufora kapitałowego.');
            }
            if ($values['profit'] !== null && $values['profit'] < 0) {
                $add('warning', ['profit'], 'Strata netto w tym okresie — wyjaśnij przyczyny i sposób finansowania działalności.');
            }
            if ($values['equity'] !== null && $values['liabilities'] !== null && $values['equity'] > 0 && $values['liabilities'] >= 0) {
                $share = $values['equity'] / ($values['equity'] + $values['liabilities']) * 100;
                if ($share < 10) {
                    $add('warning', ['equity', 'liabilities'], 'Niski udział kapitału własnego: '.number_format($share, 1, ',', ' ').'% (próg ostrzegawczy: poniżej 10%). Zobowiązania obejmują też rezerwy; nie oznacza to zaległych długów.');
                }
            }
            $previous = array_values(array_filter($rows, fn ($candidate) => isset($row['year'], $candidate['year']) && (int) $candidate['year'] === (int) $row['year'] - 1));
            if (count($previous) === 1) {
                foreach (['revenue' => 20, 'equity' => 20, 'profit' => 50] as $key => $threshold) {
                    $before = $previous[0][$key] ?? null;
                    if ($values[$key] !== null && is_numeric($before) && (float) $before > 0) {
                        $drop = ((float) $before - $values[$key]) / (float) $before * 100;
                        if ($drop >= $threshold) {
                            $add('warning', [$key], 'Spadek '.['revenue' => 'przychodów', 'equity' => 'kapitału własnego', 'profit' => 'wyniku netto'][$key].' o '.number_format($drop, 1, ',', ' ').'% względem '.((int) $row['year'] - 1).' r. (próg '.$threshold.'%). Potwierdź porównywalność okresów.');
                        }
                    }
                }
            }
            if (! $findings) {
                $findings[] = ['state' => 'clear', 'message' => 'Nie wykryto sygnałów ostrzegawczych według zastosowanych reguł. To nie jest gwarancja wypłacalności.'];
            }
            $periods[$index] = ['year' => $row['year'] ?? null, 'state' => $state, 'label' => self::LABELS[$state], 'fields' => $fields, 'findings' => $findings];
        }
        $latestYear = $periods ? max(array_column($periods, 'year')) : null;
        $latest = array_values(array_filter($periods, fn ($period) => $period['year'] === $latestYear));
        $state = count($latest) === 1 ? $latest[0]['state'] : 'unknown';

        return ['state' => $state, 'label' => self::LABELS[$state], 'year' => $latestYear, 'periods' => $periods, 'rules' => self::RULES];
    }
}
