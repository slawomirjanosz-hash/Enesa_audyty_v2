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
}
