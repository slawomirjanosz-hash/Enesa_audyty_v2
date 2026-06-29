<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferRequest;
use App\Models\Task;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $companies = Company::active()->where('show_in_dashboard', true)->with(['audits', 'offers', 'users'])->get();

        $stats = [
            'active_audits'      => Audit::where('status', 'in_progress')->count(),
            'pending_offers'     => Offer::whereIn('status', ['draft', 'sent'])->count(),
            'new_registrations'  => Company::where('status', 'pending')->count(),
            'overdue_tasks'      => Task::where('due_date', '<', now())
                                        ->where('status', '!=', 'done')
                                        ->count(),
        ];

        $newRequests = OfferRequest::with(['offerFormTemplate', 'createdBy'])
            ->where('status', 'nowe')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('company_id');

        $acceptedOffers = Offer::with(['company', 'assignedUser'])
            ->where('status', 'wygrana')
            ->where('updated_at', '>=', now()->subDays(30))
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('dashboard', compact('companies', 'stats', 'newRequests', 'acceptedOffers'));
    }
}
