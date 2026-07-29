<?php

namespace App\Services;

use App\Models\OfferRequest;
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

        if (! is_array($rules) || $rules === [] || ! is_array($responses)) {
            return ['rows' => [], 'net_total' => 0.0, 'matched_rules' => 0];
        }

        $itemIds = collect($rules)->pluck('price_catalog_item_id')->filter()->unique()->values();
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

        return [
            'rows' => $rows,
            'net_total' => round(collect($rows)->sum(fn (array $row) => $row['ilosc'] * $row['cena_jedn']), 2),
            'matched_rules' => count($rows),
        ];
    }
}
