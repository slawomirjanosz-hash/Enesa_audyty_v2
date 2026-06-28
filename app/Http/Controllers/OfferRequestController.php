<?php

namespace App\Http\Controllers;

use App\Models\OfferRequest;
use Illuminate\Http\Request;

class OfferRequestController extends Controller
{
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
}
