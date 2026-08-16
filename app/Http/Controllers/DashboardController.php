<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Offer;
use App\Models\OfferRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\AuditorAccessService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $access = app(AuditorAccessService::class);
        $user = $request->user();
        $auditsEnabled = CompanySettings::moduleIsEnabled('audits');
        $projectsEnabled = CompanySettings::moduleIsEnabled('projects');
        $relations = [
            'users',
            'offers' => fn ($query) => $access->scopeByCompanyAccess($query, $user, 'can_view_offers'),
            'offerRequests' => fn ($query) => $access
                ->scopeByCompanyAccess($query, $user, 'can_view_offer_requests')
                ->with('offerFormTemplate:id,name'),
        ];
        if ($auditsEnabled) {
            $relations['audits'] = fn ($query) => $access->scopeByCompanyAccess($query, $user, 'can_view_audits');
        }
        if ($projectsEnabled) {
            $relations['projects'] = function ($query) use ($access, $user) {
                if (! $access->hasFullAccess($user)) {
                    $query->where(fn ($projects) => $projects
                        ->where('manager_id', $user->id)
                        ->orWhereHas('members', fn ($members) => $members->whereKey($user->id)));
                }
            };
        }
        $companies = $access->scopeByCompanyAccess(
            Company::clients()->active()->where('show_in_dashboard', true)->with($relations),
            $user,
            'can_view_dashboard',
            'id'
        )->get();
        if (! $auditsEnabled) {
            $companies->each(fn (Company $company) => $company->setRelation('audits', collect()));
        }
        if (! $projectsEnabled) {
            $companies->each(fn (Company $company) => $company->setRelation('projects', collect()));
        }

        $activeProjectsQuery = Project::where('status', 'active');
        if (! $access->hasFullAccess($user)) {
            $activeProjectsQuery->where(fn ($query) => $query
                ->where('manager_id', $user->id)
                ->orWhereHas('members', fn ($members) => $members->whereKey($user->id)));
        }

        $stats = [
            'active_audits' => $auditsEnabled ? $access->scopeByCompanyAccess(Audit::where('status', 'in_progress'), $user, 'can_view_audits')->count() : 0,
            'active_projects' => $projectsEnabled ? $activeProjectsQuery->count() : 0,
            'pending_offers' => $access->scopeByCompanyAccess(Offer::whereIn('status', ['draft', 'sent']), $user, 'can_view_offers')->count(),
            'new_registrations' => $access->hasFullAccess($user) ? Company::clients()->where('status', 'pending')->count() : 0,
            'my_open_tasks' => Task::forUser($user->id)->where('status', '!=', 'done')->count(),
            'overdue_tasks' => $access->scopeByCompanyAccess(Task::where('due_date', '<', now())->where('status', '!=', 'done'), $user, 'can_view_dashboard')->count(),
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

        return view('dashboard', compact('companies', 'stats', 'newRequests', 'acceptedOffers', 'auditsEnabled', 'projectsEnabled'));
    }
}
