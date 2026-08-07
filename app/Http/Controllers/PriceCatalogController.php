<?php

namespace App\Http\Controllers;

use App\Models\PriceCatalogItem;
use App\Services\AuditorAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceCatalogController extends Controller
{
    public function index(): View
    {
        return view('pricing-catalog.index', [
            'items' => PriceCatalogItem::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        PriceCatalogItem::create($this->validatedData($request));

        return redirect()->route('pricing-catalog.index')->with('success', 'Pozycja cennika została dodana.');
    }

    public function update(Request $request, PriceCatalogItem $priceCatalogItem): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $priceCatalogItem->update($this->validatedData($request, $priceCatalogItem));

        return redirect()->route('pricing-catalog.index')->with('success', 'Pozycja cennika została zapisana.');
    }

    public function toggle(PriceCatalogItem $priceCatalogItem): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess(request()->user()), 403);

        $priceCatalogItem->update(['is_active' => ! $priceCatalogItem->is_active]);

        return redirect()->route('pricing-catalog.index')->with('success', 'Status pozycji cennika został zmieniony.');
    }

    private function validatedData(Request $request, ?PriceCatalogItem $item = null): array
    {
        $codeRule = [
            'nullable',
            'string',
            'max:50',
            Rule::unique('price_catalog_items', 'code')->ignore($item?->id),
        ];

        return $request->validate([
            'code' => $codeRule,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit' => ['required', 'string', 'max:30'],
            'net_unit_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
