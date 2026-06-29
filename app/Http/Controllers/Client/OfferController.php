<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Offer;

class OfferController extends Controller
{
    public function index()
    {
        $company = auth()->user()->companies->first();
        
        if (!$company) {
            return redirect()->route('client.dashboard')
                ->with('error', 'Brak przypisanej firmy.');
        }

        $offers = Offer::where('company_id', $company->id)
            ->where('is_template', false)
            ->orderByDesc('created_at')
            ->get();

        return view('client.offers', compact('offers', 'company'));
    }

    public function show(Offer $offer)
    {
        $company = auth()->user()->companies->first();

        if (!$company || $company->id !== $offer->company_id) {
            abort(403);
        }

        $offer->load('company', 'assignedUser', 'offerDelegation');

        return view('client.offer-show', compact('offer', 'company'));
    }
}
