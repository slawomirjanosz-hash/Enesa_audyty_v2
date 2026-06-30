<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CrmOpportunity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class CrmController extends Controller
{
    public function index(): View
    {
        $companies = Company::with(['offers', 'audits', 'tasks', 'crmOpportunities'])
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        $opportunities = CrmOpportunity::with(['company', 'assignedUser'])
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        $tasks = Task::with(['assignedUser', 'company', 'offer'])
            ->orderBy('due_date')
            ->get();

        $authUser = auth()->user();

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
            'companies', 'opportunities', 'tasks', 'users', 'stats', 'archivedCompanies', 'orphanedAssignments', 'currentTab'
        ));
    }

    public function toggleDashboard(Request $request, Company $company): \Illuminate\Http\JsonResponse
    {
        $company->update(['show_in_dashboard' => !$company->show_in_dashboard]);
        return response()->json(['show_in_dashboard' => $company->show_in_dashboard]);
    }

    public function archiveCompany(Company $company): RedirectResponse
    {
        $company->update(['status' => 'archived', 'show_in_dashboard' => false]);
        return redirect()->route('crm.index')->with('success', 'Firma została zarchiwizowana.');
    }

    public function restoreCompany(Company $company): RedirectResponse
    {
        $company->update(['status' => 'active']);
        return redirect()->route('crm.index')->with('success', 'Firma została przywrócona.');
    }

    public function destroyCompany(Company $company): RedirectResponse
    {
        if ($company->offers()->exists() || $company->audits()->exists()) {
            return redirect()->route('crm.index')->with('error', 'Nie można usunąć firmy która ma oferty lub audyty.');
        }
        $company->delete();
        return redirect()->route('crm.index')->with('success', 'Firma została usunięta.');
    }

    public function storeOpportunity(Request $request): RedirectResponse
    {
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

        CrmOpportunity::create(array_merge($data, ['created_by' => auth()->id()]));
        return redirect()->route('crm.index', ['tab' => 'pipeline'])->with('success', 'Szansa została dodana.');
    }

    public function updateOpportunityStage(Request $request, CrmOpportunity $opportunity): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'in:new_lead,contact,offer,negotiation,realization,won,lost,rejected'],
        ]);
        $opportunity->update($data);
        return response()->json(['stage' => $opportunity->stage]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
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

        Task::create(array_merge($data, ['created_by' => auth()->id()]));
        return redirect()->route('crm.index', ['tab' => 'tasks'])->with('success', 'Zadanie zostało dodane.');
    }

    public function updateTask(Request $request, Task $task): RedirectResponse
    {
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
        $task->delete();
        return redirect()->route('crm.index', ['tab' => 'tasks'])->with('success', 'Zadanie zostało usunięte.');
    }

    public function updateTaskStatus(Request $request, Task $task): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:todo,in_progress,done'],
        ]);
        $task->update($data);
        return response()->json(['status' => $task->status]);
    }

    public function updateOpportunity(Request $request, CrmOpportunity $opportunity): RedirectResponse
    {
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

        $opportunity->update($data);
        return redirect()->route('crm.index', ['tab' => 'pipeline'])->with('success', 'Szansa została zaktualizowana.');
    }

    public function destroyOpportunity(CrmOpportunity $opportunity): RedirectResponse
    {
        $opportunity->delete();
        return redirect()->route('crm.index', ['tab' => 'pipeline'])->with('success', 'Szansa została usunięta.');
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
