<?php

namespace App\Services;

use App\Models\OfferRequest;
use App\Models\OfferFormTemplate;
use App\Models\PriceCatalogItem;

class OfferPricingSuggestionService
{
    /**
     * Zwraca wyłącznie podpowiedź do edytora oferty. Nie zapisuje ani nie
     * zmienia oferty bez świadomego działania użytkownika.
     */
    public function forOfferRequest(OfferRequest $offerRequest): array
    {
        $template = $offerRequest->offerFormTemplate;
        $rules = $template?->pricing_rules ?? [];
        $responses = $offerRequest->form_responses ?? [];

        if (! is_array($responses)) {
            return ['rows' => [], 'delegations' => [], 'net_total' => 0.0, 'matched_rules' => 0];
        }

        $fields = $template?->flatFields() ?? [];
        $rules = is_array($rules) ? $rules : [];

        if ($rules === [] && $fields === []) {
            return ['rows' => [], 'delegations' => [], 'net_total' => 0.0, 'matched_rules' => 0];
        }
        $fieldItemIds = collect($fields)
            ->map(fn (array $field) => $field['pricing']['price_catalog_item_id'] ?? null)
            ->filter()
            ->values();
        $itemIds = collect($rules)->pluck('price_catalog_item_id')->filter()->merge($fieldItemIds)->unique()->values();
        $items = PriceCatalogItem::whereIn('id', $itemIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $questionKey = $rule['question_key'] ?? null;
            $expectedAnswer = (string) ($rule['answer'] ?? '');
            $answer = $questionKey ? ($responses[$questionKey] ?? null) : null;
            $item = $items->get((int) ($rule['price_catalog_item_id'] ?? 0));

            if (! $item || is_array($answer) || (string) $answer !== $expectedAnswer) {
                continue;
            }

            $quantity = max(0.01, (float) ($rule['quantity'] ?? 1));
            $price = (float) $item->net_unit_price;
            $rows[] = [
                'opis' => $item->name,
                'jedn' => $item->unit,
                'ilosc' => $quantity,
                'cena_jedn' => $price,
                'z_narzutem' => $quantity * $price,
            ];
        }

        foreach ($fields as $field) {
            $pricing = $field['pricing'] ?? null;
            $answer = $responses[$field['key'] ?? ''] ?? null;

            if (! is_array($pricing) || $answer === null || $answer === '') {
                continue;
            }

            if (($pricing['type'] ?? null) === 'quantity') {
                $item = $items->get((int) ($pricing['price_catalog_item_id'] ?? 0));
                $quantity = (float) $answer * max(0.0001, (float) ($pricing['multiplier'] ?? 1));

                if (! $item || ! is_numeric($answer) || $quantity <= 0) {
                    continue;
                }

                $rows[] = [
                    'opis' => $item->name . ' (' . ($field['label'] ?? 'ilość') . ': ' . rtrim(rtrim(number_format((float) $answer, 2, '.', ''), '0'), '.') . ')',
                    'jedn' => $item->unit,
                    'ilosc' => $quantity,
                    'cena_jedn' => (float) $item->net_unit_price,
                    'z_narzutem' => $quantity * (float) $item->net_unit_price,
                ];
            }
        }

        $delegations = [];
        foreach ($fields as $field) {
            $pricing = $field['pricing'] ?? null;
            $answer = $responses[$field['key'] ?? ''] ?? null;

            if (! is_array($pricing) || ($pricing['type'] ?? null) !== 'travel' || ! is_array($answer)) {
                continue;
            }

            $address = OfferFormTemplate::displayValue($answer);
            if ($address === '') {
                continue;
            }

            $delegations[] = [
                'nazwa' => $field['label'] ?? 'Lokalizacja z ankiety',
                'adres' => $address,
                'km' => 0,
                'wyjazdy' => max(1, (int) ($pricing['trips'] ?? 1)),
                'osoby' => max(1, (int) ($pricing['people'] ?? 1)),
                'noce' => 0,
                'stawka_km' => max(0, (float) ($pricing['rate_per_km'] ?? 1.10)),
                'stawka_noc' => 200,
            ];
        }

        return [
            'rows' => $rows,
            'delegations' => $delegations,
            'net_total' => round(collect($rows)->sum(fn (array $row) => $row['ilosc'] * $row['cena_jedn']), 2),
            'matched_rules' => count($rows) + count($delegations),
        ];
    }
}
