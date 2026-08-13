<?php

namespace App\Http\Controllers;

use App\Models\OfferFormTemplate;
use App\Services\AuditorAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $fields = json_decode($data['fields'], true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($fields)) {
            return back()->withErrors(['fields' => 'Nieprawidłowa struktura pól formularza.']);
        }

        OfferFormTemplate::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'fields' => $fields,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('offer-forms.index')
            ->with('success', 'Formularz został utworzony.');
    }

    public function update(Request $request, OfferFormTemplate $offerForm): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $fields = json_decode($data['fields'], true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($fields)) {
            return back()->withErrors(['fields' => 'Nieprawidłowa struktura pól formularza.']);
        }

        $offerForm->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'fields' => $fields,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('offer-forms.index')
            ->with('success', 'Formularz został zaktualizowany.');
    }

    public function destroy(Request $request, OfferFormTemplate $offerForm): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $offerForm->delete();

        return redirect()->route('offer-forms.index')
            ->with('success', 'Formularz został usunięty.');
    }

    public function toggleActive(Request $request, OfferFormTemplate $offerForm): JsonResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $offerForm->update(['is_active' => ! $offerForm->is_active]);

        return response()->json(['is_active' => $offerForm->is_active]);
    }
}
