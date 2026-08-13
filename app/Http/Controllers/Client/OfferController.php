<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Offer;

class OfferController extends Controller
{
    public function index()
    {
        $company = auth()->user()->companies->first();

        if (! $company) {
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

        if (! $company || $company->id !== $offer->company_id) {
            abort(403);
        }

        $offer->load('company', 'assignedUser', 'offerDelegation');

        return view('client.offer-show', compact('offer', 'company'));
    }

    public function accept(Offer $offer)
    {
        $company = auth()->user()->companies->first();

        if (! $company || $company->id !== $offer->company_id) {
            abort(403);
        }

        if ($offer->status !== 'w_toku') {
            return back()->with('error', 'Nie można zaakceptować tej oferty.');
        }

        $offer->update([
            'status' => 'wygrana',
            'won_as' => 'audyt',
        ]);

        return redirect()->route('client.offers.show', $offer)
            ->with('success', 'Oferta została zaakceptowana');
    }

    public function reject(Offer $offer)
    {
        $company = auth()->user()->companies->first();

        if (! $company || $company->id !== $offer->company_id) {
            abort(403);
        }

        if ($offer->status !== 'w_toku') {
            return back()->with('error', 'Nie można odrzucić tej oferty.');
        }

        $offer->update(['status' => 'przegrana']);

        return redirect()->route('client.offers.show', $offer)
            ->with('success', 'Oferta została odrzucona');
    }

    public function negotiate(Offer $offer)
    {
        $company = auth()->user()->companies->first();

        if (! $company || $company->id !== $offer->company_id) {
            abort(403);
        }

        if ($offer->status !== 'w_toku') {
            return back()->with('error', 'Nie można przesłać tej oferty do negocjacji.');
        }

        $offer->update(['status' => 'w_negocjacji']);

        return redirect()->route('client.offers.show', $offer)
            ->with('success', 'Oferta została przesłana do negocjacji');
    }
}
