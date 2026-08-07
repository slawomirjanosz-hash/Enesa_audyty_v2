<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OfferFormTemplate;
use App\Models\OfferRequest;
use App\Services\AuditorAccessService;
use Illuminate\Http\Request;

class OfferRequestController extends Controller
{
    public function create(Request $request)
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $companies = Company::clients()->active()->orderBy('name')->get();
        $templates = OfferFormTemplate::where('is_active', true)->orderBy('name')->get();
        $preselectedCompanyId = $request->integer('company_id') ?: null;

        return view('offer-requests.create', compact('companies', 'templates', 'preselectedCompanyId'));
    }

    public function store(Request $request)
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $data = $request->validate([
            'company_id'              => ['required', 'exists:companies,id'],
            'title'                   => ['nullable', 'string', 'max:255'],
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
            'title'                  => $data['title'] ?? null,
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

    public function savePublic(Request $request, OfferRequest $offerRequest)
    {
        $this->authorize('update', $offerRequest);

        $data = $request->validate([
            'end_client_name'    => ['nullable', 'string', 'max:255'],
            'end_client_company' => ['nullable', 'string', 'max:255'],
            'end_client_email'   => ['nullable', 'email', 'max:255'],
            'end_client_phone'   => ['nullable', 'string', 'max:30'],
        ]);

        $offerRequest->fill($data);
        $offerRequest->ensurePublicToken();
        $offerRequest->save();

        return redirect(route('offer-requests.show', $offerRequest) . '#klient-koncowy')
            ->with('success', 'Zapisano dane klienta końcowego. Link do ankiety jest gotowy do skopiowania.');
    }

    public function show(OfferRequest $offerRequest)
    {
        $this->authorize('view', $offerRequest);

        return view('offer-requests.show', compact('offerRequest'));
    }

    public function pdf(OfferRequest $offerRequest)
    {
        $this->authorize('view', $offerRequest);

        $template = $offerRequest->offerFormTemplate;
        abort_if(!$template, 404, 'Do tego zapytania nie przypisano formularza.');

        $responses = $offerRequest->form_responses ?? [];
        $broker = $offerRequest->company;

        $html = view('public.survey-pdf', compact('template', 'broker', 'offerRequest', 'responses'))->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 14,
            'margin_bottom' => 16,
            'margin_left'   => 15,
            'margin_right'  => 15,
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'ankieta-' . $offerRequest->id . '.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function destroy(OfferRequest $offerRequest)
    {
        $this->authorize('delete', $offerRequest);

        $companyId = $offerRequest->company_id;
        $offerRequest->forceDelete();

        return redirect(route('companies.show', $companyId) . '#zapytania')
            ->with('success', 'Zapytanie zostało trwale usunięte.');
    }

    public function updateStatus(Request $request, OfferRequest $offerRequest)
    {
        $this->authorize('update', $offerRequest);

        $request->validate(['status' => 'required|in:nowe,w_toku,zamknięte']);
        $offerRequest->update(['status' => $request->status]);
        return back()->with('success', 'Status zapytania został zaktualizowany.');
    }

    public function edit(OfferRequest $offerRequest)
    {
        $this->authorize('update', $offerRequest);

        $offerRequest->load(['company', 'offerFormTemplate']);
        return view('offer-requests.edit', compact('offerRequest'));
    }

    public function update(Request $request, OfferRequest $offerRequest)
    {
        $this->authorize('update', $offerRequest);

        $data = $request->validate([
            'title'          => ['nullable', 'string', 'max:255'],
            'form_responses' => ['nullable', 'array'],
            'tresc'          => ['nullable', 'string', 'max:65000'],
        ]);

        $offerRequest->update([
            'title'          => $data['title'] ?? null,
            'form_responses' => $data['form_responses'] ?? [],
            'tresc'          => $data['tresc'] ?? null,
        ]);

        return redirect(route('companies.show', $offerRequest->company_id) . '#zapytania')
            ->with('success', 'Zapytanie zostało zaktualizowane.');
    }
}
