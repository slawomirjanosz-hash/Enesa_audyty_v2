<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\OfferFormTemplate;
use App\Models\OfferRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferRequestController extends Controller
{
    public function index(): View
    {
        $templates = OfferFormTemplate::where('is_active', true)
            ->orderBy('name')
            ->get();

        $company = auth()->user()->companies->first();

        $myRequests = OfferRequest::where('company_id', $company?->id)
            ->orderByDesc('created_at')
            ->get();

        return view('client.request-offer', compact('templates', 'myRequests', 'company'));
    }

    public function show(OfferRequest $offerRequest): View
    {
        $company = auth()->user()->companies->first();

        if (! $company || $offerRequest->company_id !== $company->id) {
            abort(403, 'Nie masz dostępu do tego zapytania.');
        }

        $offerRequest->load(['company', 'offerFormTemplate', 'createdBy']);

        return view('client.request-show', compact('offerRequest'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'offer_form_template_id' => ['required', 'exists:offer_form_templates,id'],
            'form_responses' => ['nullable', 'array'],
            'tresc' => ['nullable', 'string'],
        ]);

        $company = auth()->user()->companies->first();

        if (! $company) {
            return back()->withErrors(['error' => 'Twoje konto nie jest przypisane do żadnej firmy.']);
        }

        OfferRequest::create([
            'company_id' => $company->id,
            'created_by_id' => auth()->id(),
            'offer_form_template_id' => $data['offer_form_template_id'],
            'form_responses' => $data['form_responses'] ?? [],
            'status' => 'nowe',
            'completion_percent' => 0,
        ]);

        return redirect()->route('client.request-offer')
            ->with('success', 'Zapytanie zostało wysłane. Skontaktujemy się z Tobą wkrótce.');
    }
}
