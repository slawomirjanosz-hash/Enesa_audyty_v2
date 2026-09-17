<?php

namespace App\Services;

/** Versioned source data is inert JSON; conditions are never evaluated as PHP/JS. */
class IsoContextLibrary
{
    private ?array $loaded = null;

    public function data(): array
    {
        return $this->loaded ??= json_decode(file_get_contents(resource_path('iso50001/library-v1.3.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function questions(): array
    {
        return array_map(function ($row) {
            $options = [];
            if (str_contains($row['typ_i_opcje'], '|')) {
                foreach (explode('|', preg_replace('/\s*\(\+ osobne pole:.*$/u', '', $row['typ_i_opcje'])) as $option) {
                    $label = trim($option);
                    $value = trim(preg_replace('/\s*\(.*$/u', '', $label));
                    $value = match ($value) {
                        'częściowo' => 'czesciowo', 'ruch ciągły' => '4',
                        'tak, formalnie' => 'tak', 'podmiot publiczny' => 'publiczny',
                        'spółka komunalna' => 'komunalny', default => $value,
                    };
                    $options[$value] = $label;
                }
            }
            $options['nie wiem'] = 'Nie wiem — do wyjaśnienia z konsultantem';

            return $row + ['options' => $options, 'numeric' => ! str_contains($row['typ_i_opcje'], '|')];
        }, $this->data()['pytania_faktograficzne']);
    }

    public function matches(string $condition, array $facts): bool
    {
        if ($condition === 'ZAWSZE') {
            return true;
        }
        $result = false;
        foreach (explode(' OR ', $condition) as $alternative) {
            $all = true;
            foreach (explode(' AND ', $alternative) as $clause) {
                if (! preg_match('/^([A-Z][A-Z0-9_]*)\s+(IN|!=|>=|<=|=|>|<)\s+(.+)$/D', trim($clause), $parts)) {
                    return false;
                }
                [, $key, $operator, $expected] = $parts;
                $actual = $facts[$key] ?? '';
                $known = is_scalar($actual) && ! in_array((string) $actual, ['', 'nie wiem', 'nie_wiem'], true);
                if ($operator === 'IN' && ! preg_match('/^\([\w ,.-]+\)$/uD', $expected)) {
                    return false;
                }
                $match = $known && match ($operator) {
                    '=' => (string) $actual === $expected,
                    '!=' => (string) $actual !== $expected,
                    'IN' => in_array((string) $actual, array_map('trim', explode(',', trim($expected, '()'))), true),
                    '>', '>=', '<', '<=' => is_numeric($actual) && is_numeric($expected) && match ($operator) {
                        '>' => (float) $actual > (float) $expected,
                        '>=' => (float) $actual >= (float) $expected,
                        '<' => (float) $actual < (float) $expected,
                        '<=' => (float) $actual <= (float) $expected,
                    },
                    default => false,
                };
                $all = $all && $match;
            }
            $result = $result || $all;
        }

        return $result;
    }

    public function mode(array $facts): string
    {
        return ! in_array($facts['FAKT_SYSTEM_BAZOWY'] ?? '', ['', 'brak', 'nie wiem'], true)
            && ($facts['FAKT_ZGODA_INTEGRACJA'] ?? '') === 'tak' ? 'nadbudowa' : 'greenfield';
    }

    public function factors(array $answers): array
    {
        $rows = [];
        foreach ($this->data()['czynniki_kontekstowe_4_1'] as $row) {
            $id = $row['kod'];
            // Unconfirmed legal conclusions and contradictory WO-03 wording are not presented as facts.
            $pending = str_starts_with($id, 'KTX-ZR-') || $id === 'KTX-WO-03';
            $matches = $this->matches($row['warunek'], $answers['facts'] ?? []);
            $choice = $answers['factors'][$id] ?? [];
            if (! $matches && $choice === []) {
                continue;
            }
            $selected = $row['rodzaj'] === 'AUTO' && ! $pending ? $matches
                : ($choice['selected'] ?? ($matches && $row['rodzaj'] === 'PROPOZYCJA' && ! $pending));
            $rows[] = $row + [
                'matches' => $matches, 'pending' => $pending, 'selected' => (bool) $selected,
                'text' => $choice['text'] ?? $row['sformulowanie'],
                'reason' => $choice['reason'] ?? '', 'stale' => ! $matches && (bool) $selected,
            ];
        }

        return $rows;
    }

    public function stakeholders(array $answers): array
    {
        $selected = array_column(array_filter($this->factors($answers), fn ($f) => $f['selected'] && ! $f['pending'] && ! $f['stale']), 'kod');
        $rows = [];
        foreach ($this->data()['strony_zainteresowane_4_2'] as $row) {
            $id = $row['kod'];
            $requirements = [];
            foreach ($this->data()['mapowanie_4_1_na_4_2'] as $map) {
                if (in_array($map['kod_czynnika'], $selected, true) && str_contains($map['generuje_strone'], $id)) {
                    $requirements[] = ['source' => $map['kod_czynnika'], 'text' => $map['wymaganie_do_rejestru'], 'compliance' => $map['wymog_zgodnosci']];
                }
            }
            $matches = $requirements !== [] || $this->matches($row['warunek'], $answers['facts'] ?? []);
            $choice = $answers['stakeholders'][$id] ?? [];
            if (! $matches && $choice === []) {
                continue;
            }
            $rows[] = $row + ['requirements' => $requirements, 'matches' => $matches,
                'selected' => (bool) ($choice['selected'] ?? $matches), 'reason' => $choice['reason'] ?? '',
                'text' => $choice['text'] ?? $row['typowe_wymagania'],
                'source' => $choice['source'] ?? '', 'compliance' => $choice['compliance'] ?? 'pending'];
        }

        return $rows;
    }

    public function blockers(array $answers): array
    {
        $errors = [];
        if (! filled($answers['scope'] ?? null)) {
            $errors[] = 'Uzupełnij zakres systemu zarządzania energią.';
        }
        if (! filled($answers['climate_reason'] ?? null)) {
            $errors[] = 'Uzasadnij ocenę istotności zmiany klimatu.';
        }
        foreach ($this->questions() as $question) {
            if (! filled($answers['facts'][$question['kod']] ?? null)) {
                $errors[] = 'Brak odpowiedzi: '.$question['pytanie'];
            }
        }
        if (! in_array($answers['facts']['FAKT_KLIMAT_ISTOTNY'] ?? '', ['tak', 'nie'], true)) {
            $errors[] = 'Konsultant musi rozstrzygnąć istotność zmiany klimatu.';
        }
        foreach (['strengths', 'weaknesses', 'opportunities', 'threats'] as $key) {
            if (! filled($answers['swot'][$key] ?? null)) {
                $errors[] = 'Uzupełnij wszystkie cztery obszary SWOT (lub uzasadnij brak pozycji).';
                break;
            }
        }
        $complete = array_filter($answers['conclusions'] ?? [], fn ($row) => filled($row['finding'] ?? '') && filled($row['decision'] ?? '') && filled($row['document'] ?? ''));
        if (count($complete) < 4) {
            $errors[] = 'Metodyka ENESA: wymagane są cztery kompletne wnioski, decyzje i dokumenty.';
        }
        foreach ($this->factors($answers) as $row) {
            if ($row['selected'] && ! filled($row['text'])) {
                $errors[] = $row['kod'].': uzupełnij treść wybranego czynnika.';
            }
            if ($row['stale']) {
                $errors[] = $row['kod'].': wybór nie odpowiada aktualnym danym — odznacz lub wyjaśnij dane.';
            }
            if ($row['pending'] && $row['selected']) {
                $errors[] = $row['kod'].': treść oczekuje na potwierdzenie autora biblioteki.';
            }
        }
        foreach ($this->stakeholders($answers) as $row) {
            if ((! $row['selected'] || $row['compliance'] !== 'pending') && ! filled($row['reason'])) {
                $errors[] = $row['kod'].': podaj uzasadnienie decyzji.';
            }
            if ($row['selected'] && (! $row['matches'] || $row['compliance'] === 'pending')) {
                $errors[] = $row['kod'].': wymaga rozstrzygnięcia przez konsultanta.';
            }
        }

        return array_values(array_unique($errors));
    }
}
