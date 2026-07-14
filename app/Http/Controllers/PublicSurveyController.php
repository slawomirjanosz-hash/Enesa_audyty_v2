<?php

namespace App\Http\Controllers;

use App\Models\OfferRequest;
use Illuminate\Http\Request;

class PublicSurveyController extends Controller
{
    public function show(string $token)
    {
        $offerRequest = OfferRequest::where('public_token', $token)->firstOrFail();
        $template = $offerRequest->offerFormTemplate;
        abort_if(!$template, 404, 'Do tego zapytania nie przypisano formularza.');

        return view('public.survey', [
            'offerRequest' => $offerRequest,
            'template'     => $template,
            'broker'       => $offerRequest->company,
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $offerRequest = OfferRequest::where('public_token', $token)->firstOrFail();

        $data = $request->validate([
            'form_responses' => ['nullable', 'array'],
        ]);

        $offerRequest->update([
            'form_responses'   => $data['form_responses'] ?? [],
            'public_filled_at' => now(),
            'status'           => $offerRequest->status === 'nowe' ? 'w_toku' : $offerRequest->status,
        ]);

        return view('public.survey', [
            'offerRequest' => $offerRequest,
            'template'     => $offerRequest->offerFormTemplate,
            'broker'       => $offerRequest->company,
            'submitted'    => true,
        ]);
    }
}
