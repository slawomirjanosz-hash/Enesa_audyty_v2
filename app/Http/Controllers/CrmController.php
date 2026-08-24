<?php

namespace App\Http\Controllers;

use App\Mail\TaskAssigned;
use App\Models\Audit;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\CrmOpportunity;
use App\Models\Offer;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditorAccessService;
use App\Services\CrmActivityLogger;
use App\Services\OfferCrmStageSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CrmController extends Controller
{
    public function __construct(private readonly OfferCrmStageSynchronizer $crmStageSynchronizer) {}

    public function index(): View
    {
        $access = app(AuditorAccessService::class);
        $authUser = auth()->user();
        $auditsEnabled = CompanySettings::moduleIsEnabled('audits');
        $canManageCrm = $access->hasFullAccess($authUser);
        $canManageTeamTasks = $canManageCrm || $authUser->can('crm.tasks.team.manage');
        $canManageOwnTasks = $canManageTeamTasks || $authUser->can('crm.tasks.own.manage');
        $canViewTeamTasks = $canManageTeamTasks;
        $canViewCrmData = $canManageCrm || $authUser->can('crm.view');
        $availableTabs = $canViewCrmData ? ['companies', 'suppliers', 'pipeline', 'archive'] : [];
        if ($canManageOwnTasks) {
            $availableTabs[] = 'tasks';
        }
        if ($canManageTeamTasks) {
            $availableTabs[] = 'trash';
        }
        if ($auditsEnabled) {
            $availableTabs[] = 'audits';
        }
        $currentTab = in_array(request('tab'), $availableTabs, true)
            ? request('tab')
            : ($canManageOwnTasks ? 'tasks' : 'companies');

        $companyQuery = Company::clients()
            ->where('status', '!=', 'archived')
            ->orderBy('name');
        if ($currentTab === 'companies') {
            $companyQuery->withCount(['offers', 'audits']);
        }
        $companies = $access->scopeByCompanyAccess($companyQuery, $authUser, 'can_view_dashboard', 'id')->get();

        $suppliers = $currentTab === 'suppliers' ? $access->scopeByCompanyAccess(Company::suppliers()
            ->withCount(['supplierRequirements', 'supplierFinancialEntries'])
            ->with(['supplierRequirements.project', 'supplierFinancialEntries.project'])
            ->where('status', '!=', 'archived')
            ->orderBy('name'), $authUser, 'can_view_dashboard', 'id')
            ->get() : collect();

        $opportunitiesQuery = CrmOpportunity::query()->whereNull('deleted_at')->orderByDesc('created_at');
        if (! $access->isDelegatedAuditor($authUser)) {
            $opportunitiesQuery = $access->scopeByCompanyAccess($opportunitiesQuery, $authUser, 'can_view_dashboard');
        }
        if ($access->isDelegatedAuditor($authUser) || request()->boolean('related_to_me')) {
            $opportunitiesQuery->where(function ($query) use ($authUser): void {
                $query->where('assigned_to', $authUser->id)
                    ->orWhere('created_by', $authUser->id)
                    ->orWhereHas('relatedUsers', fn ($users) => $users->whereKey($authUser->id));
            });
        }
        $opportunities = $currentTab === 'pipeline'
            ? (clone $opportunitiesQuery)->with([
                'company', 'assignedUser', 'relatedUsers', 'offers',
                'tasks' => fn ($tasks) => $tasks->crm()->with('assignedUser'),
            ])->get()
            : collect();

        $taskOpportunities = $currentTab === 'tasks'
            ? (clone $opportunitiesQuery)->with('company')->get()
            : collect();

        $unlinkedOffers = $currentTab === 'pipeline' ? $access->scopeByCompanyAccess(
            Offer::with('company')->where('is_template', false)->whereNull('crm_opportunity_id')->orderByDesc('created_at'),
            $authUser,
            'can_view_offers'
        )->get() : collect();

        $tasks = $currentTab === 'tasks' && $canManageTeamTasks ? Task::crm()->with(['assignedUser', 'company', 'offer', 'crmOpportunity'])
            ->orderBy('due_date')
            ->get() : collect();

        $myTasks = $currentTab === 'tasks' && $canManageOwnTasks ? Task::crm()->forUser(auth()->id())
            ->with(['assignedUser', 'company', 'offer', 'crmOpportunity'])->orderBy('due_date')
            ->get() : collect();

        $trashTasksQuery = Task::onlyTrashed()->crm();
        $trashTasksCount = $canManageTeamTasks ? (clone $trashTasksQuery)->count() : 0;
        $trashTasks = $currentTab === 'trash' && $canManageTeamTasks
            ? $trashTasksQuery->with(['assignedUser', 'company', 'deletedBy'])
                ->orderByDesc('deleted_at')
                ->get()
            : collect();
        $trashTaskSummary = $trashTasks
            ->groupBy(fn (Task $task) => $task->assignedUser?->name ?? 'Nieprzypisane')
            ->map->count()
            ->sortDesc();

        $audits = $auditsEnabled && $currentTab === 'audits'
            ? $access->scopeByCompanyAccess(Audit::with('company')->orderByDesc('created_at'), $authUser, 'can_view_audits')->get()
            : collect();

        $users = $canManageTeamTasks
            ? User::query()->where('is_active', true)
                ->whereHas('roles', fn ($roles) => $roles->whereNotIn('name', ['client_admin', 'client_user']))
                ->orderBy('name')->get()
            : User::query()->whereKey($authUser->id)->get();

        $stats = [
            'active_companies' => $companies->count(),
            'active_suppliers' => $access->scopeByCompanyAccess(
                Company::suppliers()->where('status', '!=', 'archived'),
                $authUser,
                'can_view_dashboard',
                'id'
            )->count(),
            'dashboard_companies' => $companies->where('show_in_dashboard', true)->count(),
            'active_opps' => (clone $opportunitiesQuery)->whereNotIn('stage', ['won', 'lost', 'rejected'])->count(),
            'open_tasks' => $canManageOwnTasks
                ? Task::crm()->where('status', '!=', 'done')
                    ->when(! $canManageTeamTasks, fn ($tasks) => $tasks->forUser($authUser->id))
                    ->count()
                : 0,
            'active_audits' => $auditsEnabled
                ? $access->scopeByCompanyAccess(Audit::where('status', 'in_progress'), $authUser, 'can_view_audits')->count()
                : 0,
        ];

        $archivedCompanies = $currentTab === 'archive' ? Company::with(['offers', 'audits'])
            ->whereNotNull('archived_at')
            ->orderBy('name')
            ->get() : collect();

        // Orphaned user-company assignments (assigned to archived/deleted companies)
        $orphanedAssignments = $currentTab === 'archive' ? DB::table('company_user')
            ->leftJoin('companies', 'company_user.company_id', '=', 'companies.id')
            ->leftJoin('users', 'company_user.user_id', '=', 'users.id')
            ->where(function ($q) {
                $q->whereNull('companies.id')  // Company doesn't exist (hard deleted)
                    ->orWhereNotNull('companies.archived_at');  // Or company is archived
            })
            ->whereNull('company_user.deleted_at')  // And assignment is not soft-deleted
            ->select(
                'company_user.id',
                'company_user.user_id',
                'company_user.company_id',
                'users.name as user_name',
                'users.email as user_email',
                'companies.name as company_name',
                'companies.archived_at'
            )
            ->orderBy('users.name')
            ->get() : collect();

        return view('crm.index', compact(
            'companies', 'suppliers', 'opportunities', 'taskOpportunities', 'unlinkedOffers', 'canManageCrm', 'canManageOwnTasks', 'canManageTeamTasks', 'canViewTeamTasks', 'canViewCrmData', 'tasks', 'myTasks', 'trashTasks', 'trashTasksCount', 'trashTaskSummary', 'audits', 'users', 'stats', 'archivedCompanies', 'orphanedAssignments', 'currentTab', 'auditsEnabled'
        ));
    }

    public function toggleDashboard(Request $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);
        $company->update(['show_in_dashboard' => ! $company->show_in_dashboard]);

        return response()->json(['show_in_dashboard' => $company->show_in_dashboard]);
    }

    public function archiveCompany(Company $company): RedirectResponse
    {
        $this->authorize('update', $company);
        $company->update(['status' => 'archived', 'show_in_dashboard' => false, 'archived_at' => now()]);

        return redirect()->route('crm.index')->with('success', 'Firma została zarchiwizowana.');
    }

    public function restoreCompany(Company $company): RedirectResponse
    {
        $this->authorize('update', $company);
        $company->update(['status' => 'active', 'archived_at' => null]);

        return redirect()->route('crm.index')->with('success', 'Firma została przywrócona.');
    }

    public function destroyCompany(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);
        if ($company->offers()->exists() || $company->audits()->exists()) {
            return redirect()->route('crm.index')->with('error', 'Nie można usunąć firmy która ma oferty lub audyty.');
        }
        $company->delete();

        return redirect()->route('crm.index')->with('success', 'Firma została usunięta.');
    }

    public function storeOpportunity(Request $request): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $data = $request->validateWithBag('leadCreate', [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'stage' => ['required', 'in:new_lead,contact,offer,negotiation,realization,won,lost,rejected'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'company_context_id' => ['nullable', 'integer', 'exists:companies,id'],
            'related_users' => ['nullable', 'array'],
            'related_users.*' => ['integer', 'exists:users,id'],
        ]);

        $companyContextId = $data['company_context_id'] ?? null;
        $relatedUsers = $data['related_users'] ?? [];
        unset($data['company_context_id']);
        unset($data['related_users']);
        $opportunity = CrmOpportunity::create(array_merge($data, ['created_by' => auth()->id()]));
        $opportunity->relatedUsers()->sync($relatedUsers);
        app(CrmActivityLogger::class)->leadCreated($opportunity);

        if ($companyContextId && (int) $companyContextId === (int) $opportunity->company_id) {
            return redirect()->to(route('companies.show', $opportunity->company_id).'#crm')
                ->with('success', 'Lead został dodany do klienta.');
        }

        return redirect()->route('crm.index', ['tab' => 'pipeline'])->with('success', 'Szansa została dodana.');
    }

    public function updateOpportunityStage(Request $request, CrmOpportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate([
            'stage' => ['required', 'in:new_lead,contact,offer,negotiation,realization,won,lost,rejected'],
        ]);
        $previousStage = $opportunity->stage;
        $opportunity->update($data);
        if ($previousStage !== $opportunity->stage) {
            app(CrmActivityLogger::class)->leadStageChanged($opportunity, $previousStage, $opportunity->stage);
        }

        return response()->json(['stage' => $opportunity->stage]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $canManageTeam = app(AuditorAccessService::class)->hasFullAccess($request->user())
            || $request->user()->can('crm.tasks.team.manage');
        abort_unless($canManageTeam || $request->user()->can('crm.tasks.own.manage'), 403);
        $data = $request->validateWithBag('taskCreate', [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'crm_opportunity_id' => ['nullable', 'exists:crm_opportunities,id'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'status' => ['required', 'in:todo,in_progress,done'],
            'priority' => ['required', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
            'company_context_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $companyContextId = $data['company_context_id'] ?? null;
        unset($data['company_context_id']);

        if (! $canManageTeam) {
            $data['assigned_to'] = $request->user()->id;
        }

        if (! empty($data['crm_opportunity_id'])) {
            $leadBelongsToCompany = CrmOpportunity::query()
                ->whereKey($data['crm_opportunity_id'])
                ->where('company_id', $data['company_id'] ?? null)
                ->exists();

            if (! $leadBelongsToCompany) {
                return back()->withErrors([
                    'crm_opportunity_id' => 'Wybrany lead nie należy do wskazanego klienta.',
                ], 'taskCreate')->withInput();
            }
        }

        $task = Task::create(array_merge($data, ['created_by' => auth()->id()]));

        if ($task->assigned_to && $task->assigned_to !== auth()->id()) {
            $assignedUser = $task->assignedUser;
            if ($assignedUser && $assignedUser->email) {
                Mail::to($assignedUser->email)
                    ->send(new TaskAssigned($task));
            }
        }

        if ($companyContextId && (int) $companyContextId === (int) $task->company_id) {
            return redirect()->to(route('companies.show', $companyContextId).'#crm')
                ->with('success', 'Zadanie zostało dodane do klienta.');
        }

        return redirect()->route('crm.index', ['tab' => 'tasks'])->with('success', 'Zadanie zostało dodane.');
    }

    public function updateTask(Request $request, Task $task): RedirectResponse
    {
        abort_if($task->project_id !== null, 404);
        $this->authorize('update', $task);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'crm_opportunity_id' => ['nullable', 'exists:crm_opportunities,id'],
            'status' => ['required', 'in:todo,in_progress,done'],
            'priority' => ['required', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
            'company_context_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $companyContextId = $data['company_context_id'] ?? null;
        unset($data['company_context_id']);

        if (! empty($data['crm_opportunity_id'])) {
            $leadBelongsToCompany = CrmOpportunity::query()
                ->whereKey($data['crm_opportunity_id'])
                ->where('company_id', $data['company_id'] ?? null)
                ->exists();

            if (! $leadBelongsToCompany) {
                return back()->withErrors([
                    'crm_opportunity_id' => 'Wybrany lead nie należy do wskazanego klienta.',
                ])->withInput();
            }
        }

        $task->update($data);

        if ($companyContextId && (int) $companyContextId === (int) $task->company_id) {
            return redirect()->to(route('companies.show', $companyContextId).'#crm')
                ->with('success', 'Zadanie zostało zaktualizowane.');
        }

        return redirect()->route('crm.index', ['tab' => 'tasks'])->with('success', 'Zadanie zostało zaktualizowane.');
    }

    public function destroyTask(Task $task): RedirectResponse
    {
        abort_if($task->project_id !== null, 404);
        $this->authorize('delete', $task);
        $task->update(['deleted_by' => auth()->id()]);
        $task->delete();

        return redirect()->route('crm.index', ['tab' => 'tasks'])->with('success', 'Zadanie zostało przeniesione do kosza.');
    }

    public function restoreTask(Request $request, int $taskId): RedirectResponse
    {
        $task = Task::onlyTrashed()->crm()->findOrFail($taskId);
        $this->authorize('update', $task);
        $task->restore();
        $task->update(['deleted_by' => null]);

        return redirect()->route('crm.index', ['tab' => 'trash'])
            ->with('success', 'Zadanie zostało przywrócone.');
    }

    public function updateTaskStatus(Request $request, Task $task): JsonResponse
    {
        abort_if($task->project_id !== null, 404);
        $this->authorize('update', $task);
        $data = $request->validate([
            'status' => ['required', 'in:todo,in_progress,done'],
        ]);
        $task->update($data);

        return response()->json(['status' => $task->status]);
    }

    public function updateOpportunity(Request $request, CrmOpportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'stage' => ['required', 'in:new_lead,contact,offer,negotiation,realization,won,lost,rejected'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'related_users' => ['nullable', 'array'],
            'related_users.*' => ['integer', 'exists:users,id'],
            'company_context_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $relatedUsers = $data['related_users'] ?? [];
        $companyContextId = $data['company_context_id'] ?? null;
        unset($data['related_users']);
        unset($data['company_context_id']);
        $previousStage = $opportunity->stage;
        $opportunity->update($data);
        $opportunity->relatedUsers()->sync($relatedUsers);
        if ($previousStage !== $opportunity->stage) {
            app(CrmActivityLogger::class)->leadStageChanged($opportunity, $previousStage, $opportunity->stage);
        }

        if ($companyContextId && (int) $companyContextId === (int) $opportunity->company_id) {
            return redirect()->to(route('companies.show', $companyContextId).'#crm')
                ->with('success', 'Szansa została zaktualizowana.');
        }

        return redirect()->route('crm.index', ['tab' => 'pipeline'])->with('success', 'Szansa została zaktualizowana.');
    }

    public function duplicateOpportunity(Request $request, CrmOpportunity $opportunity): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $this->authorize('update', $opportunity);

        $copy = $opportunity->replicate();
        $copy->title = 'Kopia — '.$opportunity->title;
        $copy->stage = 'new_lead';
        $copy->created_by = $request->user()->id;
        $copy->save();

        app(CrmActivityLogger::class)->leadCreated($copy);

        return redirect()->route('crm.index', ['tab' => 'pipeline'])
            ->with('success', 'Utworzono kopię szansy.');
    }

    public function attachOffer(Request $request, CrmOpportunity $opportunity): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $this->authorize('update', $opportunity);

        $data = $request->validate([
            'offer_id' => ['required', 'exists:offers,id'],
        ]);

        $offer = Offer::findOrFail($data['offer_id']);
        $this->authorize('update', $offer);

        if (! $opportunity->company_id || $offer->is_template || $offer->company_id !== $opportunity->company_id) {
            return redirect()->route('crm.index', ['tab' => 'pipeline'])
                ->with('error', 'Można przypiąć tylko ofertę tej samej firmy.');
        }

        if ($offer->crm_opportunity_id && $offer->crm_opportunity_id !== $opportunity->id) {
            return redirect()->route('crm.index', ['tab' => 'pipeline'])
                ->with('error', 'Ta oferta jest już przypięta do innego leada.');
        }

        $offer->update(['crm_opportunity_id' => $opportunity->id]);
        app(CrmActivityLogger::class)->offerLinked($offer, $opportunity);
        $this->crmStageSynchronizer->synchronize($offer, $opportunity);

        return redirect()->route('crm.index', ['tab' => 'pipeline'])
            ->with('success', 'Oferta została przypięta do leada.');
    }

    public function destroyOpportunity(CrmOpportunity $opportunity): RedirectResponse
    {
        $this->authorize('delete', $opportunity);
        $opportunity->delete();

        return redirect()->route('crm.index', ['tab' => 'pipeline'])->with('success', 'Szansa została usunięta.');
    }

    public function detachOrphanedUser($assignmentId): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess(request()->user()), 403);

        $assignment = DB::table('company_user')->find($assignmentId);

        if (! $assignment) {
            return redirect()->route('crm.index')->with('error', 'Powiązanie nie znalezione.');
        }

        // Soft-delete the assignment
        DB::table('company_user')
            ->where('id', $assignmentId)
            ->update(['deleted_at' => now()]);

        return redirect()->route('crm.index')->with('success', 'Powiązanie użytkownika zostało usunięte.');
    }
}
