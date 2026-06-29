<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OfferRequest;
use App\Models\Audit;

class DashboardController extends Controller
{
    public function index()
    {
        $company = auth()->user()->companies->first();

        if (!$company) {
            return redirect()->route('client.login')
                ->with('error', 'Brak przypisanej firmy.');
        }

        $offers = Offer::where('company_id', $company->id)
            ->where('is_template', false)
            ->orderByDesc('created_at')
            ->get();

        $offerRequests = OfferRequest::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->get();

        $audits = Audit::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->get();

        return view('client.dashboard', compact('company', 'offers', 'offerRequests', 'audits'));
    }
}
