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
