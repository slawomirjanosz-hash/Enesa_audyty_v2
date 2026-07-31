<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Company;
use App\Models\CrmOpportunity;
use App\Models\Offer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Services\AuditorAccessService;
use App\Services\CrmActivityLogger;

class CrmController extends Controller
{
    public function index(): View
    {
        $access = app(AuditorAccessService::class);
        $authUser = auth()->user();
        $companies = $access->scopeByCompanyAccess(Company::with(['offers', 'audits', 'tasks', 'crmOpportunities'])
            ->where('status', '!=', 'archived')
            ->orderBy('name'), $authUser, 'can_view_dashboard', 'id')
            ->get();

        $opportunities = $access->scopeByCompanyAccess(CrmOpportunity::with(['company', 'assignedUser', 'offers'])
            ->whereNull('deleted_at')
            ->orderByDesc('created_at'), $authUser, 'can_view_dashboard')
            ->get();

        $unlinkedOffers = $access->scopeByCompanyAccess(
            Offer::with('company')->where('is_template', false)->whereNull('crm_opportunity_id')->orderByDesc('created_at'),
            $authUser,
            'can_view_offers'
        )->get();

        $canManageCrm = $access->hasFullAccess($authUser);

        $tasks = $access->scopeByCompanyAccess(Task::with(['assignedUser', 'company', 'offer'])
            ->orderBy('due_date'), $authUser, 'can_view_dashboard')
            ->get();

        $myTasks = $access->scopeByCompanyAccess(Task::forUser(auth()->id())
            ->with(['assignedUser', 'company', 'offer'])->orderBy('due_date'), $authUser, 'can_view_dashboard')
            ->get();

        $audits = $access->scopeByCompanyAccess(Audit::with('company')->orderByDesc('created_at'), $authUser, 'can_view_audits')
            ->get();

if ($authUser->hasRole('superadmin')) {
    $users = User::role(['superadmin', 'admin', 'auditor_senior', 'auditor'])->orderBy('name')->get();
} elseif ($authUser->hasRole('admin')) {
    $users = User::role(['admin', 'auditor_senior', 'auditor'])->orderBy('name')->get();
} elseif ($authUser->hasRole('auditor_senior')) {
    $users = User::role(['auditor_senior', 'auditor'])->orderBy('name')->get();
} elseif ($authUser->hasRole('auditor')) {
    // Audytor widzi tylko siebie
    $users = User::where('id', $authUser->id)->get();
} else {
    $users = collect();
}

        $stats = [
            'active_companies'    => $companies->count(),
            'dashboard_companies' => $companies->where('show_in_dashboard', true)->count(),
            'active_opps'         => $opportunities->whereNotIn('stage', ['won','lost','rejected'])->count(),
            'open_tasks'          => $tasks->where('status', '!=', 'done')->count(),
            'active_audits'       => $audits->where('status', 'in_progress')->count(),
        ];

        $archivedCompanies = Company::with(['offers', 'audits'])
            ->whereNotNull('archived_at')
            ->orderBy('name')
            ->get();

        // Orphaned user-company assignments (assigned to archived/deleted companies)
        $orphanedAssignments = DB::table('company_user')
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
            ->get();

        $currentTab = request('tab', 'companies');

        return view('crm.index', compact(
            'companies', 'opportunities', 'unlinkedOffers', 'canManageCrm', 'tasks', 'myTasks', 'audits', 'users', 'stats', 'archivedCompanies', 'orphanedAssignments', 'currentTab'
        ));
    }

    public function toggleDashboard(Request $request, Company $company): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $company);
        $company->update(['show_in_dashboard' => !$company->show_in_dashboard]);
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
        $data = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'company_id'          => ['nullable', 'exists:companies,id'],
            'assigned_to'         => ['nullable', 'exists:users,id'],
            'stage'               => ['required', 'in:new_lead,contact,offer,negotiation,realization,won,lost,rejected'],
            'value'               => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string'],
        ]);

        $opportunity = CrmOpportunity::create(array_merge($data, ['created_by' => auth()->id()]));
        app(CrmActivityLogger::class)->leadCreated($opportunity);
        return redirect()->route('crm.index', ['tab' => 'pipeline'])->with('success', 'Szansa została dodana.');
    }

    public function updateOpportunityStage(Request $request, CrmOpportunity $opportunity): \Illuminate\Http\JsonResponse
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
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'company_id'  => ['nullable', 'exists:companies,id'],
            'offer_id'    => ['nullable', 'exists:offers,id'],
            'status'      => ['required', 'in:todo,in_progress,done'],
            'priority'    => ['required', 'in:low,medium,high'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $task = Task::create(array_merge($data, ['created_by' => auth()->id()]));

        if ($task->assigned_to && $task->assigned_to !== auth()->id()) {
            $assignedUser = $task->assignedUser;
            if ($assignedUser && $assignedUser->email) {
                \Illuminate\Support\Facades\Mail::to($assignedUser->email)
                    ->send(new \App\Mail\TaskAssigned($task));
            }
        }

        return redirect()->route('crm.index', ['tab' => 'tasks'])->with('success', 'Zadanie zostało dodane.');
    }

    public function updateTask(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'company_id'  => ['nullable', 'exists:companies,id'],
            'status'      => ['required', 'in:todo,in_progress,done'],
            'priority'    => ['required', 'in:low,medium,high'],
            'due_date'    => ['nullable', 'date'],
        ]);
        $task->update($data);
        return redirect()->route('crm.index', ['tab' => 'tasks'])->with('success', 'Zadanie zostało zaktualizowane.');
    }

    public function destroyTask(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $task->delete();
        return redirect()->route('crm.index', ['tab' => 'tasks'])->with('success', 'Zadanie zostało usunięte.');
    }

    public function updateTaskStatus(Request $request, Task $task): \Illuminate\Http\JsonResponse
    {
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
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'company_id'          => ['nullable', 'exists:companies,id'],
            'assigned_to'         => ['nullable', 'exists:users,id'],
            'stage'               => ['required', 'in:new_lead,contact,offer,negotiation,realization,won,lost,rejected'],
            'value'               => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string'],
        ]);

        $previousStage = $opportunity->stage;
        $opportunity->update($data);
        if ($previousStage !== $opportunity->stage) {
            app(CrmActivityLogger::class)->leadStageChanged($opportunity, $previousStage, $opportunity->stage);
        }
        return redirect()->route('crm.index', ['tab' => 'pipeline'])->with('success', 'Szansa została zaktualizowana.');
    }

    public function duplicateOpportunity(Request $request, CrmOpportunity $opportunity): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $this->authorize('update', $opportunity);

        $copy = $opportunity->replicate();
        $copy->title = 'Kopia — ' . $opportunity->title;
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
        $this->synchronizeOpportunityStage($opportunity, $offer);

        return redirect()->route('crm.index', ['tab' => 'pipeline'])
            ->with('success', 'Oferta została przypięta do leada.');
    }

    public function destroyOpportunity(CrmOpportunity $opportunity): RedirectResponse
    {
        $this->authorize('delete', $opportunity);
        $opportunity->delete();
        return redirect()->route('crm.index', ['tab' => 'pipeline'])->with('success', 'Szansa została usunięta.');
    }

    private function synchronizeOpportunityStage(CrmOpportunity $opportunity, Offer $offer): void
    {
        $nextStage = match ($offer->status) {
            'wygrana' => 'realization',
            'przegrana' => 'lost',
            'w_toku' => in_array($opportunity->stage, ['new_lead', 'contact'], true) ? 'offer' : null,
            default => null,
        };

        if ($nextStage && $opportunity->stage !== $nextStage) {
            $previousStage = $opportunity->stage;
            $opportunity->update(['stage' => $nextStage]);
            app(CrmActivityLogger::class)->leadStageChanged($opportunity, $previousStage, $nextStage);
        }
    }

    public function detachOrphanedUser($assignmentId): RedirectResponse
    {
        $assignment = DB::table('company_user')->find($assignmentId);

        if (!$assignment) {
            return redirect()->route('crm.index')->with('error', 'Powiązanie nie znalezione.');
        }

        // Soft-delete the assignment
        DB::table('company_user')
            ->where('id', $assignmentId)
            ->update(['deleted_at' => now()]);

        return redirect()->route('crm.index')->with('success', 'Powiązanie użytkownika zostało usunięte.');
    }
}
