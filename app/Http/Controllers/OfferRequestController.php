<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OfferFormTemplate;
use App\Models\OfferRequest;
use Illuminate\Http\Request;

class OfferRequestController extends Controller
{
    public function create(Request $request)
    {
        $companies = Company::active()->orderBy('name')->get();
        $templates = OfferFormTemplate::where('is_active', true)->orderBy('name')->get();
        $preselectedCompanyId = $request->integer('company_id') ?: null;

        return view('offer-requests.create', compact('companies', 'templates', 'preselectedCompanyId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id'              => ['required', 'exists:companies,id'],
            'offer_form_template_id'  => ['nullable', 'exists:offer_form_templates,id'],
            'form_responses'          => ['nullable', 'array'],
            'tresc'                   => ['nullable', 'string', 'max:65000'],
        ]);

        if (empty($data['offer_form_template_id']) && empty($data['form_responses']) && empty($data['tresc'])) {
            return back()->withInput()->withErrors([
                'tresc' => 'Wypełnij formularz lub wklej treść zapytania z maila klienta.',
            ]);
        }

        $offerRequest = OfferRequest::create([
            'company_id'             => $data['company_id'],
            'created_by_id'          => auth()->id(),
            'offer_form_template_id' => $data['offer_form_template_id'] ?? null,
            'form_responses'         => $data['form_responses'] ?? [],
            'tresc'                  => $data['tresc'] ?? null,
            'status'                 => 'nowe',
            'completion_percent'     => 0,
        ]);

        return redirect(route('companies.show', $data['company_id']) . '#zapytania')
            ->with('success', 'Zapytanie zostało utworzone i jest widoczne w karcie firmy.');
    }

    public function show(OfferRequest $offerRequest)
    {
        $offerRequest->load(['company', 'offerFormTemplate', 'createdBy']);
        return view('offer-requests.show', compact('offerRequest'));
    }

    public function updateStatus(Request $request, OfferRequest $offerRequest)
    {
        $request->validate(['status' => 'required|in:nowe,w_toku,zamknięte']);
        $offerRequest->update(['status' => $request->status]);
        return back()->with('success', 'Status zapytania został zaktualizowany.');
    }

    public function edit(OfferRequest $offerRequest)
    {
        $offerRequest->load(['company', 'offerFormTemplate']);
        return view('offer-requests.edit', compact('offerRequest'));
    }

    public function update(Request $request, OfferRequest $offerRequest)
    {
        $data = $request->validate([
            'form_responses' => ['nullable', 'array'],
            'tresc'          => ['nullable', 'string', 'max:65000'],
        ]);

        $offerRequest->update([
            'form_responses' => $data['form_responses'] ?? [],
            'tresc'          => $data['tresc'] ?? null,
        ]);

        return redirect(route('companies.show', $offerRequest->company_id) . '#zapytania')
            ->with('success', 'Zapytanie zostało zaktualizowane.');
    }
}
