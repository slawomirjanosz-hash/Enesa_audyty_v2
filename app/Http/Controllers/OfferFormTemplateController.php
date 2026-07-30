<?php

namespace App\Http\Controllers;

use App\Models\OfferFormTemplate;
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

        return view('offer-forms.index', compact('templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fields'      => ['required', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $fields = json_decode($data['fields'], true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($fields)) {
            return back()->withErrors(['fields' => 'Nieprawidłowa struktura pól formularza.']);
        }

        OfferFormTemplate::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'fields'      => $fields,
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
            'is_active'   => ['boolean'],
        ]);

        $fields = json_decode($data['fields'], true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($fields)) {
            return back()->withErrors(['fields' => 'Nieprawidłowa struktura pól formularza.']);
        }

        $offerForm->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'fields'      => $fields,
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

}
