<?php

namespace App\Services;

use App\Models\IsoFactorReview;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** One registry, with explicit adapters for records created before HTML 4.1/3. */
class IsoFactorConclusions
{
    public function registry(IsoFactorReview $review): array
    {
        $rows = $review->swot['wnioski'] ?? [];
        foreach ($rows as $i => &$row) {
            $row['id'] ??= 'WN-legacy-'.($i + 1);
            $row['czynniki'] ??= array_values(array_filter([$row['czynnik'] ?? null]));
            $row['scope'] ??= 'consultant';
            unset($row['czynnik']);
        }
        unset($row);
        foreach ($review->answers['factors'] ?? [] as $code => $answer) {
            if (! collect(['znaczenie', 'decyzja_w', 'dzialanie', 'sprawdzenie'])->contains(fn ($key) => filled($answer[$key] ?? null))) {
                continue;
            }
            if (collect($rows)->contains(fn ($row) => in_array($code, $row['czynniki'], true) && $row['scope'] === 'client')) {
                continue;
            }
            // Old forms could store the same conclusion at a factor and in SWOT.
            // Reuse an identical single-factor record; different decisions stay distinct.
            $same = false;
            foreach ($rows as &$existing) {
                if ($existing['czynniki'] !== [$code]) {
                    continue;
                }
                if (collect(['znaczenie' => 'znaczenie', 'decyzja' => 'decyzja_w', 'dzialanie' => 'dzialanie', 'sprawdzenie' => 'sprawdzenie'])
                    ->every(fn ($field, $key) => trim($existing[$key] ?? '') === trim($answer[$field] ?? ''))) {
                    $existing['scope'] = 'client';
                    $same = true;
                    break;
                }
            }
            unset($existing);
            if ($same) {
                continue;
            }
            $rows[] = ['id' => 'WN-'.$code, 'scope' => 'client', 'czynniki' => [$code], 'znaczenie' => $answer['znaczenie'] ?? '', 'decyzja' => $answer['decyzja_w'] ?? '', 'dzialanie' => $answer['dzialanie'] ?? '', 'sprawdzenie' => $answer['sprawdzenie'] ?? ''];
        }

        return array_values($rows);
    }

    public function validate(array $rows, array $codes, bool $complete = false): array
    {
        $rules = ['rows' => 'array|max:100', 'rows.*' => 'array', 'rows.*.id' => 'required|string|max:100|distinct',
            'rows.*.czynniki' => 'array|max:90', 'rows.*.czynniki.*' => ['string', Rule::in($codes)],
            'rows.*.przeglad' => 'nullable|boolean', 'rows.*.scope' => ['nullable', Rule::in(['client', 'consultant'])]];
        foreach (['znaczenie', 'decyzja', 'dzialanie', 'sprawdzenie'] as $key) {
            $rules['rows.*.'.$key] = ($complete ? 'required' : 'nullable').'|string|max:5000';
        }

        return Validator::make(['rows' => $rows], $rules)->validate()['rows'];
    }

    public function forClient(array $rows): array
    {
        return array_values(array_filter($rows, fn ($row) => ($row['scope'] ?? 'consultant') === 'client'));
    }

    public function legacyFields(array $answers, array $rows): array
    {
        $answers['factors'] ??= [];
        foreach ($answers['factors'] as $code => &$answer) {
            $row = collect($rows)->first(fn ($row) => in_array($code, $row['czynniki'] ?? [], true) && ($row['scope'] ?? '') === 'client');
            foreach (['znaczenie' => 'znaczenie', 'decyzja_w' => 'decyzja', 'dzialanie' => 'dzialanie', 'sprawdzenie' => 'sprawdzenie'] as $field => $key) {
                $answer[$field] = $row[$key] ?? '';
            }
        }
        unset($answer);

        return $answers;
    }
}
