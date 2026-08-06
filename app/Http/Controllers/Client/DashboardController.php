<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\CompanySettings;
use App\Models\Offer;
use App\Models\OfferRequest;

class DashboardController extends Controller
{
    public function index()
    {
        $company = auth()->user()->companies->first();

        if (! $company) {
            return redirect()->route('client.login')
                ->with('error', 'Brak przypisanej firmy.');
        }

        $offers = CompanySettings::moduleIsEnabled('offers')
            ? Offer::where('company_id', $company->id)
                ->where('is_template', false)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $offerRequests = CompanySettings::moduleIsEnabled('offers')
            ? OfferRequest::where('company_id', $company->id)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $audits = CompanySettings::moduleIsEnabled('audits')
            ? Audit::where('company_id', $company->id)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('client.dashboard', compact('company', 'offers', 'offerRequests', 'audits'));
    }
}
