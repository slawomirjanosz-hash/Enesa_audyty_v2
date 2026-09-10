<?php

namespace App\Services;

class IsoContextService
{
    public function normalize(array $answers): array
    {
        $facts = $answers['facts'] ?? [];
        if (! isset($facts['shifts']) && ($facts['continuous_work'] ?? '') === 'tak') {
            $facts['shifts'] = '4';
        }
        foreach ($facts as $key => $value) {
            $facts[$key] = match ($value) {
                'nie_wiem' => 'nie wiem', 'plan' => 'w planach', default => $value
            };
        }
        $answers['facts'] = $facts;

        return $answers;
    }

    public function matches(array $rule, array $facts): bool
    {
        if ($rule['always'] ?? false) {
            return true;
        }
        if (isset($rule['any'])) {
            return collect($rule['any'])->contains(fn ($child) => $this->matches($child, $facts));
        }
        if (isset($rule['all'])) {
            return collect($rule['all'])->every(fn ($child) => $this->matches($child, $facts));
        }
        $value = $facts[$rule['field']] ?? null;
        if ($value === null || $value === '') {
            return false;
        }

        return match ($rule['op']) {
            '===' => (string) $value === (string) $rule['value'],
            '!==' => (string) $value !== (string) $rule['value'],
            '>' => (float) $value > $rule['value'],
            '>=' => (float) $value >= $rule['value'],
            '<' => (float) $value < $rule['value'],
            '<=' => (float) $value <= $rule['value'],
            default => false,
        };
    }

    public function selected(array $answers): array
    {
        $answers = $this->normalize($answers);
        $result = [];
        foreach (config('iso50001-context.factors') as $factor) {
            if (! $this->matches($factor['condition'], $answers['facts'])) {
                continue;
            }
            if (! $factor['automatic'] && ! in_array($factor['id'], $answers['selected'] ?? [], true)) {
                continue;
            }
            $result[] = [
                'id' => $factor['id'], 'dimension' => $factor['dimension'],
                'area' => config('iso50001-context.dimensions.'.$factor['dimension']),
                'impact' => match ($factor['impact']) {
                    '+' => '+', '-' => '−', default => '○'
                },
                'text' => $factor['automatic'] ? $factor['text'] : ($answers['edits'][$factor['id']] ?? $factor['text']),
                'effect' => $factor['effect'],
            ];
        }

        return $result;
    }

    public function stakeholders(array $answers): array
    {
        $customers = ($answers['facts']['customers_co2'] ?? '') === 'tak';

        return array_map(fn ($row) => array_map(fn ($cell) => match ($cell) {
            '@customers' => $customers ? 'Informacja o śladzie węglowym i efektywności wyrobu' : 'Brak zgłoszonych wymagań energetycznych',
            '@customerCompliance' => $customers ? 'do rozstrzygnięcia' : 'nie',
            default => $cell,
        }, $row), config('iso50001-context.stakeholders'));
    }
}
