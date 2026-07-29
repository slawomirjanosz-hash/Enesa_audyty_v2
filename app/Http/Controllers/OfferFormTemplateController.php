<?php

namespace App\Http\Controllers;

use App\Models\OfferFormTemplate;
use App\Models\PriceCatalogItem;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OfferFormTemplateController extends Controller
{
    public function index(): View
    {
        $templates = OfferFormTemplate::withTrashed(false)
            ->orderByDesc('created_at')
            ->get();

        $priceItems = PriceCatalogItem::where('is_active', true)->orderBy('name')->get();

        return view('offer-forms.index', compact('templates', 'priceItems'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fields'      => ['required', 'string'],
            'pricing_rules'=> ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $fields = json_decode($data['fields'], true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($fields)) {
            return back()->withErrors(['fields' => 'Nieprawidłowa struktura pól formularza.']);
        }

        $pricingRules = $this->normalizePricingRules($data['pricing_rules'] ?? null);

        OfferFormTemplate::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'fields'      => $fields,
            'pricing_rules' => $pricingRules,
            'is_active'   => $request->boolean('is_active', true),
            'created_by'  => auth()->id(),
        ]);

        return redirect()->route('offer-forms.index')
            ->with('success', 'Formularz został utworzony.');
    }

    public function update(Request $request, OfferFormTemplate $offerForm): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fields'      => ['required', 'string'],
            'pricing_rules'=> ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $fields = json_decode($data['fields'], true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($fields)) {
            return back()->withErrors(['fields' => 'Nieprawidłowa struktura pól formularza.']);
        }

        $pricingRules = $this->normalizePricingRules($data['pricing_rules'] ?? null);

        $offerForm->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'fields'      => $fields,
            'pricing_rules' => $pricingRules,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return redirect()->route('offer-forms.index')
            ->with('success', 'Formularz został zaktualizowany.');
    }

    public function destroy(OfferFormTemplate $offerForm): RedirectResponse
    {
        $offerForm->delete();

        return redirect()->route('offer-forms.index')
            ->with('success', 'Formularz został usunięty.');
    }

    public function toggleActive(OfferFormTemplate $offerForm): \Illuminate\Http\JsonResponse
    {
        $offerForm->update(['is_active' => !$offerForm->is_active]);

        return response()->json(['is_active' => $offerForm->is_active]);
    }

    private function normalizePricingRules(?string $json): array
    {
        if (blank($json)) {
            return [];
        }

        $rules = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($rules)) {
            abort(422, 'Nieprawidłowa konfiguracja reguł wyceny.');
        }

        $rules = collect($rules)->map(function ($rule) {
            if (! is_array($rule)) {
                return null;
            }

            $questionKey = trim((string) ($rule['question_key'] ?? ''));
            $answer = trim((string) ($rule['answer'] ?? ''));
            $itemId = (int) ($rule['price_catalog_item_id'] ?? 0);
            $quantity = (float) ($rule['quantity'] ?? 0);

            if ($questionKey === '' || $answer === '' || $itemId < 1 || $quantity <= 0) {
                return null;
            }

            return [
                'question_key' => $questionKey,
                'answer' => $answer,
                'price_catalog_item_id' => $itemId,
                'quantity' => min($quantity, 1000000),
            ];
        })->filter()->values();

        $validIds = PriceCatalogItem::where('is_active', true)
            ->whereIn('id', $rules->pluck('price_catalog_item_id')->unique())
            ->pluck('id')
            ->all();

        return $rules->whereIn('price_catalog_item_id', $validIds)->values()->all();
    }
}
