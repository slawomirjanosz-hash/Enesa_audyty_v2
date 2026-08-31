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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $access = app(AuditorAccessService::class);
        $user = $request->user();
        $lastSeenTaskId = $user->dashboard_tasks_seen_id ?? 0;
        $auditsEnabled = CompanySettings::moduleIsEnabled('audits');
        $projectsEnabled = CompanySettings::moduleIsEnabled('projects');
        $canPrioritizeCompanies = $access->hasFullAccess($user);
        $canViewDashboardDocuments = $access->hasFullAccess($user) || $user->can('dashboard.documents.view');
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
                $query->withCount(['tasks as overdue_tasks_count' => fn ($tasks) => $tasks->overdue()]);
                if (! $access->hasFullAccess($user)) {
                    $query->where(fn ($projects) => $projects
                        ->where('manager_id', $user->id)
                        ->orWhereHas('members', fn ($members) => $members->whereKey($user->id)));
                }
            };
        }
        $companiesQuery = Company::clients()->active()->where('show_in_dashboard', true)->with($relations);
        if ($canViewDashboardDocuments) {
            $companiesQuery->withCount('documents');
        }
        $companies = $access->scopeByCompanyAccess(
            $companiesQuery,
            $user,
            'can_view_dashboard',
            'id'
        )->orderByRaw('dashboard_position IS NULL')
            ->orderBy('dashboard_position')
            ->orderBy('name')
            ->get();
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

        $visibleOverdueTasksQuery = $access->scopeByCompanyAccess(
            Task::crm()->where('due_date', '<', now())->where('status', '!=', 'done'),
            $user,
            'can_view_dashboard'
        );
        $canViewTeamTasks = $access->hasFullAccess($user) || $user->can('calendar.team.view');
        if (! $canViewTeamTasks) {
            $visibleOverdueTasksQuery->where('assigned_to', $user->id);
        }

        $stats = [
            'active_audits' => $auditsEnabled ? $access->scopeByCompanyAccess(Audit::where('status', 'in_progress'), $user, 'can_view_audits')->count() : 0,
            'active_projects' => $projectsEnabled ? $activeProjectsQuery->count() : 0,
            'pending_offers' => $access->scopeByCompanyAccess(Offer::whereIn('status', ['draft', 'sent']), $user, 'can_view_offers')->count(),
            'new_registrations' => $access->hasFullAccess($user) ? Company::clients()->active()->where('status', 'pending')->count() : 0,
            'my_open_tasks' => Task::crm()->forUser($user->id)->where('status', '!=', 'done')->count(),
            'my_new_tasks' => Task::crm()->forUser($user->id)
                ->where('status', '!=', 'done')
                ->where('id', '>', $lastSeenTaskId)
                ->where(fn ($query) => $query->whereNull('created_by')->orWhere('created_by', '!=', $user->id))
                ->count(),
            'overdue_tasks' => (clone $visibleOverdueTasksQuery)->count(),
            'my_overdue_tasks' => (clone $visibleOverdueTasksQuery)->where('assigned_to', $user->id)->count(),
        ];

        $latestAssignedTaskId = Task::crm()->forUser($user->id)->max('id');
        if ($latestAssignedTaskId !== null) {
            DB::table('users')->where('id', $user->id)->update([
                'dashboard_tasks_seen_id' => $latestAssignedTaskId,
            ]);
        }

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

        return view('dashboard', compact('companies', 'stats', 'newRequests', 'acceptedOffers', 'auditsEnabled', 'projectsEnabled', 'canPrioritizeCompanies', 'canViewDashboardDocuments'));
    }

    public function reorderCompanies(Request $request): JsonResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $data = $request->validate([
            'company_ids' => ['required', 'array'],
            'company_ids.*' => ['required', 'integer', 'distinct', 'exists:companies,id'],
        ]);
        $allowedIds = Company::clients()->active()->where('show_in_dashboard', true)->pluck('id')->all();
        abort_unless(count($data['company_ids']) === count($allowedIds)
            && collect($data['company_ids'])->diff($allowedIds)->isEmpty(), 422, 'Kolejność musi zawierać wszystkich klientów z dashboardu.');

        DB::transaction(function () use ($data): void {
            foreach ($data['company_ids'] as $position => $companyId) {
                Company::whereKey($companyId)->update(['dashboard_position' => $position + 1]);
            }
        });

        return response()->json(['saved' => true]);
    }
}
