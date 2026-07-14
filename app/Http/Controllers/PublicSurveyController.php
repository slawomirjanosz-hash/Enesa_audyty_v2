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
            'responses'    => $offerRequest->form_responses ?? [],
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $offerRequest = OfferRequest::where('public_token', $token)->firstOrFail();

        $data = $request->validate([
            'form_responses' => ['nullable', 'array'],
        ]);

        $responses = $data['form_responses'] ?? [];

        if ($request->input('mode') === 'draft') {
            $offerRequest->update(['form_responses' => $responses]);

            return view('public.survey', [
                'offerRequest' => $offerRequest,
                'template'     => $offerRequest->offerFormTemplate,
                'broker'       => $offerRequest->company,
                'responses'    => $responses,
                'draftSaved'   => true,
            ]);
        }

        $offerRequest->update([
            'form_responses'   => $responses,
            'public_filled_at' => now(),
            'status'           => $offerRequest->status === 'nowe' ? 'w_toku' : $offerRequest->status,
        ]);

        return view('public.survey', [
            'offerRequest' => $offerRequest,
            'template'     => $offerRequest->offerFormTemplate,
            'broker'       => $offerRequest->company,
            'responses'    => $responses,
            'submitted'    => true,
        ]);
    }

    public function pdf(Request $request, string $token)
    {
        $offerRequest = OfferRequest::where('public_token', $token)->firstOrFail();
        $template = $offerRequest->offerFormTemplate;
        abort_if(!$template, 404);

        $responses = (array) $request->input('form_responses', $offerRequest->form_responses ?? []);
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
}