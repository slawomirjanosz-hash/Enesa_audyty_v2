<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfferFormTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'fields', 'is_active', 'created_by',
    ];

    protected $casts = [
        'fields'    => 'array',
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Spłaszcza zagnieżdżoną strukturę (sekcje + gałęzie) do płaskiej
     * listy pól-liści z key/label/type/required/options. Zachowuje kolejność.
     * Zgodne wstecznie ze starą płaską strukturą.
     */
    public function flatFields(): array
    {
        return $this->flattenNodes($this->fields ?? []);
    }

    /**
     * Pola pogrupowane po sekcjach (gałęzie spłaszczone w obrębie sekcji).
     * Zwraca [ ['title' => ?string, 'fields' => [...]], ... ].
     * Dla starych, płaskich szablonów zwraca jedną grupę bez tytułu.
     */
    public function sectionedFields(): array
    {
        $nodes = $this->fields ?? [];
        $hasSections = collect($nodes)->contains(
            fn ($n) => is_array($n) && ($n['type'] ?? null) === 'section'
        );

        if (!$hasSections) {
            return [['title' => null, 'fields' => $this->flattenNodes($nodes)]];
        }

        $out = [];
        foreach ($nodes as $node) {
            if (!is_array($node) || ($node['type'] ?? null) !== 'section') {
                continue;
            }
            $out[] = [
                'title'  => $node['title'] ?? null,
                'fields' => $this->flattenNodes($node['fields'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * Zamienia wartość odpowiedzi na tekst (adres zapisany jest jako tablica zip/city/street/no).
     */
    public static function displayValue($value): string
    {
        if (!is_array($value)) {
            return (string) $value;
        }

        if (isset($value['city']) || isset($value['street']) || isset($value['zip'])) {
            $line1 = trim(($value['zip'] ?? '') . ' ' . ($value['city'] ?? ''));
            $line2 = trim(($value['street'] ?? '') . ' ' . ($value['no'] ?? ''));

            return trim(implode(', ', array_filter([$line1, $line2])));
        }

        return implode(', ', array_filter($value, fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Postęp wypełnienia: ['answered' => int, 'total' => int, 'percent' => int].
     * Pola z gałęzi liczone tylko wtedy, gdy wybrano prowadzącą do nich odpowiedź.
     */
    public function progress(array $responses): array
    {
        $counts = ['answered' => 0, 'total' => 0];
        $this->countNodes($this->fields ?? [], $responses, $counts);

        $counts['percent'] = $counts['total'] > 0
            ? (int) round($counts['answered'] / $counts['total'] * 100)
            : 0;

        return $counts;
    }

    private function countNodes(array $nodes, array $responses, array &$counts): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'section') {
                $this->countNodes($node['fields'] ?? [], $responses, $counts);
                continue;
            }

            $key = $node['key'] ?? null;
            if (!$key) {
                continue;
            }

            $counts['total']++;

            $value = $responses[$key] ?? null;
            $isAnswered = $value !== null && $value !== '' && $value !== [];

            if ($isAnswered) {
                $counts['answered']++;
            }

            if (($node['type'] ?? null) === 'select'
                && $isAnswered
                && is_scalar($value)
                && !empty($node['branches'][$value])
                && is_array($node['branches'][$value])
            ) {
                $this->countNodes($node['branches'][$value], $responses, $counts);
            }
        }
    }

    private function flattenNodes(array $nodes): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'section') {
                $out = array_merge($out, $this->flattenNodes($node['fields'] ?? []));
                continue;
            }

            $out[] = [
                'key'      => $node['key']      ?? null,
                'label'    => $node['label']    ?? '',
                'type'     => $node['type']     ?? 'text',
                'required' => $node['required'] ?? false,
                'options'  => $node['options']  ?? [],
            ];

            if (!empty($node['branches']) && is_array($node['branches'])) {
                foreach ($node['branches'] as $branchFields) {
                    if (is_array($branchFields)) {
                        $out = array_merge($out, $this->flattenNodes($branchFields));
                    }
                }
            }
        }

        return $out;
    }
}
