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
        return json_decode(file_get_contents(resource_path('iso50001/plant-profile-v1.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function questions(array $definition): array
    {
        return array_merge(...array_column($definition['groups'], 'questions'));
    }

    public function visible(array $question, array $answers): bool
    {
        $condition = $question['condition'] ?? null;

        return ! $condition || in_array($answers[$condition['key']]['value'] ?? null, $condition['values'], true);
    }

    public function normalize(array $input, array $definition, array $previous, int $userId, bool $submit): array
    {
        $answers = [];
        foreach ($this->questions($definition) as $q) {
            $raw = $input[$q['key']] ?? [];
            if (! is_array($raw)) {
                throw ValidationException::withMessages(['answers' => 'Nieprawidłowa odpowiedź: '.$q['label']]);
            }
            $rules = ['unknown' => 'nullable|boolean', 'detail' => 'nullable|string|max:3000', 'source' => 'nullable|string|max:500'];
            $valueRule = match ($q['type']) {
                'select' => ['nullable', Rule::in(array_keys($q['options']))],
                'multi', 'rows' => ['nullable', 'array', 'max:50'],
                'number' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
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
            $valid = Validator::make($raw, $rules, [], ['value' => $q['label'], 'detail' => 'Uzupełnienie: '.$q['label']])->validate();
            $value = $valid['value'] ?? null;
            $unknown = (bool) ($valid['unknown'] ?? false);
            if (in_array($q['type'], ['select', 'multi'])) {
                $unknown = false;
            }
            if ($q['type'] === 'multi' && is_array($value)) {
                $exclusive = array_keys(array_filter($q['options'], fn ($label) => in_array($label, ['Nie wiem', 'Brak', 'Brak pomiarów', 'Brak znanych zmian'])));
                if (count($value) > 1 && array_intersect($value, $exclusive)) {
                    throw ValidationException::withMessages(['answers' => $q['label'].' — „Brak” lub „Nie wiem” wybierz osobno.']);
                }
            }
            if ($q['type'] === 'rows') {
                $value = array_values(array_filter($value ?? [], fn ($row) => collect($row)->except('id')->contains(fn ($v) => filled($v))));
                foreach ($value as &$row) {
                    $row['id'] = $row['id'] ?? (string) Str::uuid();
                    if ($q['key'] === 'energy.records' && filled($row['period_start'] ?? null) && filled($row['period_end'] ?? null) && $row['period_end'] < $row['period_start']) {
                        throw ValidationException::withMessages(['answers' => 'Koniec okresu zużycia nie może poprzedzać początku.']);
                    }
                    if ($q['key'] === 'energy.records' && filled($row['quantity'] ?? null)) {
                        foreach (['unit', 'carrier', 'period_start', 'period_end', 'boundary', 'flow_type', 'quality'] as $field) {
                            if (blank($row[$field] ?? null)) {
                                throw ValidationException::withMessages(['answers' => 'Uzupełnij jednostkę, nośnik, okres, obszar, przepływ i źródło wartości zużycia.']);
                            }
                        }
                    }
                    if (filled($row['cost'] ?? null) && (blank($row['currency'] ?? null) || blank($row['cost_basis'] ?? null))) {
                        throw ValidationException::withMessages(['answers' => 'Dla kosztu wybierz walutę i netto/brutto.']);
                    }
                }
                unset($row);
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
                $answer = $answers[$q['key']];
                if ($this->visible($q, $answers) && (blank($answer['value']) && (! $answer['unknown'] || $q['required']))) {
                    throw ValidationException::withMessages(['answers' => 'Uzupełnij odpowiedź: '.$q['label']]);
                }
            }
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
