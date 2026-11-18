<?php

namespace App\Services\Enms;

use App\Services\IsoSystemQuestionnaire;
use Illuminate\Support\Str;

/** Read-only presentation of a source; stored citations and their hashes stay intact. */
final class SourcePreview
{
    private const ROOTS = ['ZAKRES' => 'ZAKRES', 'CELE' => 'CELE', 'ENPI' => 'KARTY', 'ENB' => 'KARTY', 'WYNIKI' => 'WSKAZNIKI', 'ROLE' => 'ROLE', 'SEU' => 'SEU'];

    public function __construct(private readonly IsoSystemQuestionnaire $questionnaire) {}

    public function content(array $citation, string $object): array
    {
        $value = $citation['value'] ?? null;
        $root = self::ROOTS[$object];
        $fields = [];
        if (is_array($value) && $value !== []) {
            foreach ($this->questionnaire->sections($citation['section'], [$root => $value], []) as $group) {
                foreach ($group['fields'] ?? [] as $field) {
                    if (str_starts_with($field['key'], $root.'.')) {
                        $fields[$field['key']] = $field;
                    }
                }
            }
        }

        return $this->node($value, $root, $fields);
    }

    private function node(mixed $value, string $path, array $fields, array $options = []): array
    {
        $options = $fields[$path]['options'] ?? $options;
        if (! is_array($value) || $value === []) {
            $text = match (true) {
                $value === null, $value === [], is_string($value) && trim($value) === '' => 'Nie podano',
                is_bool($value) => $value ? 'Tak' : 'Nie',
                default => (string) (is_string($value) || is_int($value) ? ($options[$value] ?? $value) : $value),
            };

            return ['type' => 'text', 'text' => $text];
        }
        $list = collect(array_keys($value))->every(fn ($key) => is_int($key));
        if (! $list) {
            // Follow the source form's field order, not MySQL's JSON member order.
            $ordered = [];
            foreach (array_keys($fields) as $fieldPath) {
                if (str_starts_with($fieldPath, $path.'.')) {
                    $key = explode('.', substr($fieldPath, strlen($path) + 1))[0];
                    if (array_key_exists($key, $value)) {
                        $ordered[$key] = $value[$key];
                    }
                }
            }
            $value = $ordered + $value;
        }
        $children = [];
        foreach ($value as $key => $item) {
            $childPath = $path.'.'.$key;
            $children[] = [
                'label' => $fields[$childPath]['label'] ?? ($key === 'MAT' ? 'Matryca odpowiedzialności' : Str::headline((string) $key)),
                'content' => $this->node($item, $childPath, $fields, $list ? $options : []),
            ];
        }

        return ['type' => $list ? 'list' : 'fields', 'children' => $children];
    }
}
