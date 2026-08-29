<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $company = request()->user()->companies()->first();
        if (! $company) {
            return redirect()->route('client.dashboard')->with('error', 'Brak przypisanej firmy.');
        }
        $audits = $company->audits()->with('manager')
            ->withCount(['tasks', 'documents', 'surveys', 'energyPassports'])
            ->orderByDesc('created_at')->get();

        return view('client.audits', compact('company', 'audits'));
    }
}
