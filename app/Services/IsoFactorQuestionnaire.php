<?php

namespace App\Services;

use App\Models\IsoPlantProfile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IsoFactorQuestionnaire
{
    private ?array $schema = null;

    public function schema(): array
    {
        return $this->schema ??= json_decode(file_get_contents(resource_path('iso50001/factors-v1.4.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function facts(IsoPlantProfile $profile): array
    {
        return app(IsoPlantQuestionnaire::class)->facts($profile->definition, $profile->answers);
    }

    public function hash(IsoPlantProfile $profile): string
    {
        return hash('sha256', json_encode([$profile->id, $profile->lock_version, $profile->status, $this->facts($profile)], JSON_THROW_ON_ERROR));
    }

    public function factors(array $facts, array $answers): array
    {
        $facts['FAKT_KLIMAT_ISTOTNY'] = $answers['FAKT_KLIMAT_ISTOTNY'] ?? null;

        return array_map(function ($factor) use ($facts, $answers) {
            preg_match_all('/\b(?:FAKT_[A-Z0-9_]+|ZUZYCIE_TJ)\b/', $factor['pokaz_gdy'], $matches);
            $dependencies = array_intersect_key($facts, array_flip($matches[0]));
            $factor['dependencies'] = array_fill_keys($matches[0], null);
            $factor['dependencies'] = array_replace($factor['dependencies'], $dependencies);
            $factor['basis'] = hash('sha256', json_encode($factor['dependencies'], JSON_THROW_ON_ERROR));
            $factor['visible'] = app(IsoContextLibrary::class)->matches($factor['pokaz_gdy'], $facts);
            // A consumption threshold alone does not establish a legal obligation.
            // Likewise absence of certification is not absence of management processes.
            if ($factor['rodzaj'] === 'AUTO' && (str_starts_with($factor['kod'], 'KTX-ZR-') || $factor['kod'] === 'KTX-WO-03')) {
                $factor['rodzaj'] = 'PROPOZYCJA';
                $factor['verification'] = 'Wniosek wymaga weryfikacji zakresu obowiązków i danych przez audytora; można zmienić treść lub odrzucić.';
            }
            if ($factor['kod'] === 'KTX-ZK-02') {
                $factor['tresc'] = 'Oceniono zmianę klimatu jako nieistotną dla zdolności systemu zarządzania energią do osiągania zamierzonych wyników. Uzasadnienie: '.($answers['climate_reason'] ?? '');
            }

            return $factor;
        }, $this->schema()['czynniki']);
    }

    /** Keep unaffected decisions; never silently reuse decisions based on changed facts. */
    public function currentAnswers(array $answers, array $basis, array $facts): array
    {
        foreach ($this->factors($facts, $answers) as $factor) {
            if (($basis[$factor['kod']] ?? null) !== $factor['basis']) {
                unset($answers['factors'][$factor['kod']]['decyzja']);
            }
        }

        return $answers;
    }

    public function normalize(array $input, array $facts, bool $complete): array
    {
        $data = Validator::make($input, [
            'FAKT_KLIMAT_ISTOTNY' => ['nullable', Rule::in(['tak', 'nie'])],
            'climate_reason' => 'nullable|string|max:3000',
            'factors' => 'nullable|array|max:69',
            'factors.*' => 'array',
            'factors.*.decyzja' => ['nullable', Rule::in(['potwierdzony', 'odrzucony'])],
            'factors.*.tresc' => 'nullable|string|max:5000',
            'factors.*.powod' => 'nullable|string|max:3000',
            'custom' => 'nullable|array|max:20',
            'custom.*.id' => 'required|uuid|distinct',
            'custom.*.tresc' => 'required|string|max:5000',
            'custom.*.wplyw' => ['required', Rule::in(['+', '−', '○'])],
        ])->validate();
        $out = ['FAKT_KLIMAT_ISTOTNY' => $data['FAKT_KLIMAT_ISTOTNY'] ?? null, 'climate_reason' => $data['climate_reason'] ?? null, 'factors' => [], 'custom' => array_values($data['custom'] ?? [])];
        $errors = [];
        if ($complete && (! $out['FAKT_KLIMAT_ISTOTNY'] || ! filled($out['climate_reason']))) {
            $errors['climate_reason'] = 'Oceń istotność zmiany klimatu i podaj uzasadnienie.';
        }
        foreach ($this->factors($facts, $out) as $factor) {
            $key = $factor['kod'];
            if ($factor['rodzaj'] === 'AUTO') {
                continue;
            }
            $row = $data['factors'][$key] ?? [];
            $out['factors'][$key] = ['decyzja' => $row['decyzja'] ?? null, 'tresc' => $row['tresc'] ?? $factor['tresc'], 'powod' => $row['powod'] ?? null];
            if (! $factor['visible']) {
                continue;
            }
            if (($row['decyzja'] ?? '') === 'odrzucony' && ! filled($row['powod'] ?? null)) {
                $errors["factors.$key.powod"] = "$key: podaj powód odrzucenia.";
            }
            if ($complete && ! filled($row['decyzja'] ?? null)) {
                $errors["factors.$key.decyzja"] = "$key: potwierdź lub odrzuć czynnik.";
            }
            if (($row['decyzja'] ?? '') === 'potwierdzony' && ! filled($out['factors'][$key]['tresc'])) {
                $errors["factors.$key.tresc"] = "$key: uzupełnij treść do dokumentu.";
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    public function progress(array $facts, array $answers): array
    {
        $total = 1;
        $done = ! empty($answers['FAKT_KLIMAT_ISTOTNY']) && filled($answers['climate_reason'] ?? null) ? 1 : 0;
        foreach ($this->factors($facts, $answers) as $factor) {
            if (! $factor['visible'] || $factor['rodzaj'] === 'AUTO') {
                continue;
            }
            $total++;
            $row = $answers['factors'][$factor['kod']] ?? [];
            $done += (($row['decyzja'] ?? '') === 'potwierdzony' && filled($row['tresc'] ?? null)) || (($row['decyzja'] ?? '') === 'odrzucony' && filled($row['powod'] ?? null)) ? 1 : 0;
        }

        return app(QuestionnaireCompletion::class)->result($done, $total);
    }

    public function formAnswers(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }
        $text = fn ($value) => is_scalar($value) ? (string) $value : '';
        $out = ['FAKT_KLIMAT_ISTOTNY' => $text($input['FAKT_KLIMAT_ISTOTNY'] ?? null), 'climate_reason' => $text($input['climate_reason'] ?? null), 'factors' => [], 'custom' => []];
        foreach ($this->schema()['czynniki'] as $factor) {
            $row = $input['factors'][$factor['kod']] ?? [];
            foreach (['decyzja', 'tresc', 'powod'] as $field) {
                if (is_array($row) && array_key_exists($field, $row)) {
                    $out['factors'][$factor['kod']][$field] = $text($row[$field]);
                }
            }
        }
        foreach (array_slice(is_array($input['custom'] ?? null) ? $input['custom'] : [], 0, 20) as $row) {
            if (is_array($row)) {
                $out['custom'][] = array_map($text, array_intersect_key($row, array_flip(['id', 'tresc', 'wplyw'])));
            }
        }

        return $out;
    }

    public function describe(mixed $value): string
    {
        if (! is_array($value)) {
            return match ($value) {
                'potwierdzony' => 'Potwierdzony', 'odrzucony' => 'Odrzucony', null, '' => 'Brak odpowiedzi', default => (string) $value,
            };
        }
        $labels = ['decyzja' => 'Decyzja', 'tresc' => 'Treść', 'powod' => 'Uzasadnienie', 'wplyw' => 'Wpływ'];
        $lines = [];
        foreach ($value as $key => $item) {
            if ($key !== 'id') {
                $lines[] = (isset($labels[$key]) ? $labels[$key].': ' : '').$this->describe($item);
            }
        }

        return implode("\n", $lines) ?: 'Brak odpowiedzi';
    }
}
