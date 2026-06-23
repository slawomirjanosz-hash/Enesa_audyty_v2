<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientZoneController extends Controller
{
    public function index(): View
    {
        $companies = Company::whereHas('users', function ($query) {
            $query->whereHas('roles', function ($q) {
                $q->whereIn('name', ['client_admin', 'client_user']);
            });
        })->withCount(['users' => function ($query) {
            $query->whereHas('roles', function ($q) {
                $q->whereIn('name', ['client_admin', 'client_user']);
            });
        }])->orderBy('name')->get();

        return view('client-zone.index', compact('companies'));
    }

    public function impersonate(Company $company): RedirectResponse
    {
        session([
            'client_zone_company_id'   => $company->id,
            'client_zone_company_name' => $company->name,
        ]);

        return redirect()->route('client-zone.dashboard');
    }

    public function stopImpersonate(): RedirectResponse
    {
        session()->forget(['client_zone_company_id', 'client_zone_company_name']);

        return redirect()->route('client-zone.index');
    }

    public function dashboard(): View
    {
        $company = Company::findOrFail(session('client_zone_company_id'));

        return view('client-zone.dashboard', compact('company'));
    }

    public function audits(): View
    {
        $company = Company::findOrFail(session('client_zone_company_id'));

        return view('client-zone.audits', compact('company'));
    }

    public function offers(): View
    {
        $company = Company::findOrFail(session('client_zone_company_id'));

        return view('client-zone.offers', compact('company'));
    }

    public function requestOffer(): View
    {
        $company = Company::findOrFail(session('client_zone_company_id'));

        return view('client-zone.request-offer', compact('company'));
    }

    public function documents(): View
    {
        $company = Company::findOrFail(session('client_zone_company_id'));

        return view('client-zone.documents', compact('company'));
    }

    public function chat(): View
    {
        $company = Company::findOrFail(session('client_zone_company_id'));

        return view('client-zone.chat', compact('company'));
    }

    public function users(): View
    {
        $company = Company::findOrFail(session('client_zone_company_id'));

        $users = $company->users()->with('roles')->get();

        return view('client-zone.users', compact('company', 'users'));
    }
}
