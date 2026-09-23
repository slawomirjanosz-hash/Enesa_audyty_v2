<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IsoPlantQuestionnaire
{
    public function definition(): array
    {
        return json_decode(file_get_contents(resource_path('iso50001/plant-profile-v2.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function questions(array $definition): array
    {
        return array_merge(...array_column($definition['groups'], 'questions'));
    }

    /** Stable identifiers for later questionnaires; inactive/unknown values are never facts. */
    public function facts(array $definition, array $answers): array
    {
        $answers = app(IsoPlantCalculations::class)->apply($definition, $answers);
        $facts = [];
        foreach ($this->questions($definition) as $q) {
            $answer = $answers[$q['key']] ?? [];
            $value = $answer['value'] ?? null;
            $facts[$q['key']] = ! $this->visible($q, $answers) || ($answer['unknown'] ?? false) || in_array($value, ['unknown', 'nie wiem', 'do potwierdzenia'], true) ? null : $value;
        }

        return $facts;
    }

    public function visible(array $question, array $answers): bool
    {
        $condition = $question['condition'] ?? null;

        if (! $condition) {
            return true;
        }
        $answer = $answers[$condition['key']] ?? [];
        $value = $answer['value'] ?? null;
        if (($answer['unknown'] ?? false) || $value === null || $value === '' || in_array($value, ['unknown', 'nie wiem'], true)) {
            return false;
        }

        return match ($condition['operator'] ?? 'in') {
            '>' => is_numeric($value) && (float) $value > (float) $condition['values'][0],
            '!=' => ! in_array((string) $value, $condition['values'], true),
            default => in_array((string) $value, $condition['values'], true),
        };
    }

    public function normalize(array $input, array $definition, array $previous, int $userId, bool $submit): array
    {
        // Retain retired identifiers for future references and the previous-answer panel.
        $answers = array_diff_key($previous, array_flip(array_column($this->questions($definition), 'key')));
        $errors = [];
        foreach ($this->questions($definition) as $q) {
            if ($q['type'] === 'auto') {
                continue;
            }
            $prefix = 'answers.'.$q['key'];
            $raw = $input[$q['key']] ?? [];
            if (! is_array($raw)) {
                $errors[$prefix.'.value'][] = 'Nieprawidłowa odpowiedź: '.$q['label'];

                continue;
            }
            $rules = ['unknown' => 'nullable|boolean', 'detail' => 'nullable|string|max:3000', 'source' => 'nullable|string|max:500'];
            $valueRule = match ($q['type']) {
                'select' => ['nullable', Rule::in(array_keys($q['options']))],
                'multi', 'rows' => ['nullable', 'array', 'max:'.($q['max_items'] ?? 50)],
                'number' => ['nullable', ($q['integer'] ?? false) ? 'integer' : 'numeric', 'min:'.($q['min'] ?? 0), 'max:'.($q['max'] ?? 1000000000000)],
                'date' => ['nullable', 'date_format:Y-m-d'],
                default => ['nullable', 'string', 'max:2000'],
            };
            $rules['value'] = $valueRule;
            if ($q['type'] === 'multi') {
                $rules['value.*'] = ['required', Rule::in(array_keys($q['options'])), 'distinct'];
            }
            if ($q['type'] === 'rows') {
                $rules['value.*'] = 'array:'.implode(',', ['id', ...array_keys($q['fields'])]);
                $rules['value.*.id'] = 'nullable|uuid|distinct';
                foreach ($q['fields'] as $key => $field) {
                    $rules['value.*.'.$key] = match ($field['type']) {
                        'select' => ['nullable', Rule::in(array_keys($field['options']))],
                        'number' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
                        'date' => ['nullable', 'date_format:Y-m-d'],
                        default => ['nullable', 'string', 'max:500'],
                    };
                }
            }
            $validator = Validator::make($raw, $rules, [], ['value' => $q['label'], 'detail' => 'Uzupełnienie: '.$q['label']]);
            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors[$prefix.'.'.$field] = $messages;
                }

                continue;
            }
            $valid = $validator->validated();
            $value = $valid['value'] ?? null;
            $unknown = (bool) ($valid['unknown'] ?? false);
            if (in_array($q['type'], ['select', 'multi'])) {
                $unknown = false;
            }
            if ($q['type'] === 'multi' && is_array($value)) {
                $exclusive = array_keys(array_filter($q['options'], fn ($label) => in_array($label, ['Nie wiem', 'Brak', 'Brak pomiarów', 'Brak znanych zmian'])));
                if (count($value) > 1 && array_intersect($value, $exclusive)) {
                    $errors[$prefix.'.value'][] = $q['label'].' — „Brak” lub „Nie wiem” wybierz osobno.';
                }
            }
            if ($q['type'] === 'rows') {
                $value = array_filter($value ?? [], fn ($row) => collect($row)->except('id')->contains(fn ($v) => filled($v)));
                foreach ($value as $rowIndex => &$row) {
                    $row['id'] = $row['id'] ?? (string) Str::uuid();
                    if (in_array($q['key'], ['FAKT_NOSNIKI', 'FAKT_LOKALIZACJE_LISTA'])) {
                        $requiredFields = $q['key'] === 'FAKT_NOSNIKI' ? ['c0', 'c1', 'c2'] : ['c0', 'c1', 'c2', 'c3'];
                        foreach ($requiredFields as $field) {
                            if (blank($row[$field] ?? null)) {
                                $errors[$prefix.'.value.'.$rowIndex.'.'.$field][] = 'Uzupełnij pole „'.$q['fields'][$field]['label'].'” w tym wierszu.';
                            }
                        }
                    }
                    if ($q['key'] === 'energy.records' && filled($row['period_start'] ?? null) && filled($row['period_end'] ?? null) && $row['period_end'] < $row['period_start']) {
                        $errors[$prefix.'.value.'.$rowIndex.'.period_end'][] = 'Koniec okresu zużycia nie może poprzedzać początku.';
                    }
                    if ($q['key'] === 'energy.records' && filled($row['quantity'] ?? null)) {
                        foreach (['unit', 'carrier', 'period_start', 'period_end', 'boundary', 'flow_type', 'quality'] as $field) {
                            if (blank($row[$field] ?? null)) {
                                $errors[$prefix.'.value.'.$rowIndex.'.'.$field][] = 'Uzupełnij pole „'.$q['fields'][$field]['label'].'” dla wpisanego zużycia.';
                            }
                        }
                    }
                    if (filled($row['cost'] ?? null) && (blank($row['currency'] ?? null) || blank($row['cost_basis'] ?? null))) {
                        foreach (['currency', 'cost_basis'] as $field) {
                            if (blank($row[$field] ?? null)) {
                                $errors[$prefix.'.value.'.$rowIndex.'.'.$field][] = 'Dla kosztu wybierz walutę i netto/brutto.';
                            }
                        }
                    }
                }
                unset($row);
                $value = array_values($value);
            }
            if ($unknown) {
                $value = null;
            }
            $answer = ['value' => $value, 'unknown' => $unknown, 'detail' => $valid['detail'] ?? null, 'source' => $valid['source'] ?? null];
            $old = $previous[$q['key']] ?? [];
            $changed = array_intersect_key($old, $answer) !== $answer;
            $answers[$q['key']] = $answer + ['updated_by' => $changed ? $userId : ($old['updated_by'] ?? $userId), 'updated_at' => $changed ? now()->toIso8601String() : ($old['updated_at'] ?? now()->toIso8601String())];
        }
        if ($submit) {
            foreach ($this->questions($definition) as $q) {
                if ($q['type'] === 'auto' || ! isset($answers[$q['key']])) {
                    continue;
                }
                $answer = $answers[$q['key']];
                if ($this->visible($q, $answers) && (blank($answer['value']) && (! $answer['unknown'] || $q['required']))) {
                    $errors['answers.'.$q['key'].'.value'][] = 'Uzupełnij odpowiedź: '.$q['label'];
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return app(IsoPlantCalculations::class)->apply($definition, $answers);
    }

    /** Preserve scalar user input after errors, but never render malformed nested values. */
    public function formAnswers(mixed $input, array $definition): array
    {
        $input = is_array($input) ? $input : [];
        $answers = [];
        foreach ($this->questions($definition) as $q) {
            $raw = is_array($input[$q['key']] ?? null) ? $input[$q['key']] : [];
            $answer = ['unknown' => ($raw['unknown'] ?? false) === true || ($raw['unknown'] ?? null) === '1' || ($raw['unknown'] ?? null) === 1];
            foreach (['value', 'detail', 'source'] as $field) {
                $answer[$field] = is_scalar($raw[$field] ?? null) ? $raw[$field] : null;
            }
            if ($q['type'] === 'multi') {
                $answer['value'] = is_array($raw['value'] ?? null) ? array_values(array_filter($raw['value'], 'is_scalar')) : [];
            }
            if ($q['type'] === 'rows') {
                $answer['value'] = [];
                foreach (is_array($raw['value'] ?? null) ? $raw['value'] : [] as $index => $row) {
                    if (ctype_digit((string) $index) && is_array($row)) {
                        $answer['value'][$index] = array_filter(array_intersect_key($row, array_flip(['id', ...array_keys($q['fields'])])), fn ($value) => is_scalar($value) || $value === null);
                    }
                }
            }
            $answers[$q['key']] = $answer;
        }

        return $answers;
    }

    public function display(array $q, array $answer): string
    {
        if ($answer['unknown'] ?? false) {
            return 'Nie wiem / dane niedostępne';
        }
        $value = $answer['value'] ?? null;
        if (blank($value)) {
            return 'Nie podano';
        }
        if ($q['type'] === 'rows') {
            return implode("\n", array_map(function ($row) use ($q) {
                $parts = [];
                foreach ($q['fields'] as $key => $field) {
                    if (filled($row[$key] ?? null)) {
                        $parts[] = $field['label'].': '.($field['options'][$row[$key]] ?? $row[$key]);
                    }
                }

                return implode('; ', $parts);
            }, $value));
        }
        if ($q['type'] === 'multi') {
            return implode(', ', array_map(fn ($v) => $q['options'][$v] ?? $v, $value));
        }

        return (string) ($q['options'][$value] ?? $value);
    }
}
