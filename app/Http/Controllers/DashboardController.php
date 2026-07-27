<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferRequest;
use App\Models\Task;
use App\Services\AuditorAccessService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $access = app(AuditorAccessService::class);
        $user = $request->user();
        $companies = $access->scopeByCompanyAccess(
            Company::active()->where('show_in_dashboard', true)->with(['audits', 'offers', 'users']),
            $user,
            'can_view_dashboard',
            'id'
        )->get();

        $stats = [
            'active_audits'      => $access->scopeByCompanyAccess(Audit::where('status', 'in_progress'), $user, 'can_view_audits')->count(),
            'pending_offers'     => $access->scopeByCompanyAccess(Offer::whereIn('status', ['draft', 'sent']), $user, 'can_view_offers')->count(),
            'new_registrations'  => $access->hasFullAccess($user) ? Company::where('status', 'pending')->count() : 0,
            'overdue_tasks'      => $access->scopeByCompanyAccess(Task::where('due_date', '<', now())->where('status', '!=', 'done'), $user, 'can_view_dashboard')->count(),
        ];

        $newRequests = $access->scopeByCompanyAccess(OfferRequest::with(['offerFormTemplate', 'createdBy'])
            ->where('status', 'nowe')
            ->orderByDesc('created_at'), $user, 'can_view_offer_requests')
            ->get()
            ->groupBy('company_id');

        $acceptedOffers = $access->scopeByCompanyAccess(Offer::with(['company', 'assignedUser'])
            ->where('status', 'wygrana')
            ->where('updated_at', '>=', now()->subDays(30))
            ->orderByDesc('updated_at')
            ->limit(10), $user, 'can_view_offers')
            ->get();

        return view('dashboard', compact('companies', 'stats', 'newRequests', 'acceptedOffers'));
    }
}
