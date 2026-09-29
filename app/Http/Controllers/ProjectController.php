<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditorAccessService;
use App\Support\TableSort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Project::with(['company', 'manager', 'members'])
            ->withCount([
                'tasks',
                'requirements',
                'tasks as overdue_tasks_count' => fn ($tasks) => $tasks->overdue(),
            ])
            ->orderByDesc('created_at');

        if (! app(AuditorAccessService::class)->hasFullAccess($user)) {
            $query->where(fn ($q) => $q->where('manager_id', $user->id)
                ->orWhereHas('members', fn ($members) => $members->whereKey($user->id)));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $completedQuery = (clone $query)->where('status', 'completed')->withCount('members');
        $sortColumns = [
            'number' => 'number', 'name' => 'name', 'team' => 'members_count', 'date' => 'start_date',
            'company' => Company::select('name')->whereColumn('companies.id', 'projects.company_id')->limit(1),
            'manager' => User::select('name')->whereColumn('users.id', 'projects.manager_id')->limit(1),
        ];
        if ($this->canViewProjectFinances($user)) {
            $sortColumns['amount'] = 'contract_value';
        }
        TableSort::apply($completedQuery, $request, $sortColumns, 'completed');

        return view('projects.index', [
            'projects' => (clone $query)->where('status', '!=', 'completed')->paginate(20)->withQueryString(),
            'completedProjects' => $completedQuery->paginate(20, ['*'], 'completed_page')->withQueryString(),
            'companies' => Company::clients()->active()->orderBy('name')->get(),
            'users' => $this->staffUsers(),
            'canViewFinances' => $this->canViewProjectFinances($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $this->prepareNewProjectNumber($request);
        $data = $this->validateProject($request);
        $members = $data['member_ids'] ?? [];
        unset($data['member_ids']);
        $data['created_by'] = $request->user()->id;

        $project = Project::create($data);
        $project->members()->sync(array_unique(array_filter([...$members, $project->manager_id])));

        return redirect()->route('projects.show', $project)->with('success', 'Projekt został utworzony.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);
        $user = request()->user();
        $fullAccess = app(AuditorAccessService::class)->hasFullAccess($user);
        $canViewSchedule = $fullAccess || $user->canAny(['projects.schedule.view', 'projects.schedule.manage']);
        $canManageSchedule = $fullAccess || $user->can('projects.schedule.manage');
        $canViewFinances = $fullAccess || $user->canAny(['projects.finances.view', 'projects.finances.manage']);
        $canViewRequirements = $fullAccess || $user->canAny(['projects.requirements.view', 'projects.requirements.manage']);
        // Price permissions are deliberately independent from operational full access.
        // This prevents a broad role from bypassing a disabled material/service price checkbox.
        $canViewMaterialPrices = $user->hasRole('superadmin') || $user->can('projects.requirements.material_prices.view');
        $canViewServicePrices = $user->hasRole('superadmin') || $user->can('projects.requirements.service_prices.view');
        $canViewDocuments = $fullAccess || $user->canAny(['projects.documents.view', 'projects.documents.manage']);
        $canViewProtocols = $fullAccess || $user->canAny(['projects.protocols.view', 'projects.protocols.manage']);
        $canManageProtocols = $fullAccess || $user->can('projects.protocols.manage');
        $project->load([
            'company', 'manager', 'members', 'tasks.assignedUser', 'tasks.dependency',
            'financialEntries.financeGroup', 'financialEntries.supplierCompany', 'financialEntries.projectRequirement', 'financeGroups.entries',
            'requirements.responsible', 'requirements.supplierCompany', 'documents.uploader',
            'documentFolders.documents.uploader', 'documentFolders.shares',
        ]);

        $timelineItems = $canViewSchedule ? $project->tasks->filter(fn ($task) => $task->start_date && $task->due_date)->map(fn ($task) => [
            'kind' => $task->is_milestone ? 'milestone' : 'task', 'id' => 'task-'.$task->id, 'db_id' => $task->id, 'name' => $task->title,
            'start' => $task->start_date->format('Y-m-d'), 'end' => $task->due_date->format('Y-m-d'),
            'progress' => $task->progress, 'color' => '#7C3AED',
            'assignee' => $task->assignedUser?->name,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'is_milestone' => $task->is_milestone,
            'assigned_to' => $task->assigned_to,
            'dependencies' => $task->depends_on_task_id ? 'task-'.$task->depends_on_task_id : '',
            'update_url' => route('projects.tasks.update', [$project, $task]),
            'delete_url' => route('projects.tasks.destroy', [$project, $task]),
            'position' => $task->project_position,
        ])->values() : collect();

        return view('projects.show', [
            'project' => $project,
            'users' => $this->staffUsers(),
            'companies' => Company::clients()->active()->orderBy('name')->get(),
            'suppliers' => Company::suppliers()->active()->orderBy('name')->get(),
            'timelineItems' => $timelineItems,
            'canViewSchedule' => $canViewSchedule,
            'canManageSchedule' => $canManageSchedule,
            'canViewFinances' => $canViewFinances,
            'canViewRequirements' => $canViewRequirements,
            'canViewMaterialPrices' => $canViewMaterialPrices,
            'canViewServicePrices' => $canViewServicePrices,
            'canViewDocuments' => $canViewDocuments,
            'canViewProtocols' => $canViewProtocols,
            'canManageProtocols' => $canManageProtocols,
            'protocols' => $canViewProtocols ? ProjectProtocol::where('project_id', $project->id)->latest()->get(['id', 'project_id', 'number', 'acceptance_date', 'supplier_snapshot', 'kind', 'outcome', 'invoice_decision', 'items', 'revision', 'created_at']) : collect(),
            'canDeleteProject' => $user->hasAnyRole(['admin', 'superadmin']),
            'canCopyProject' => $fullAccess && $user->can('projects.create'),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $this->validateProject($request, $project, 'projectEdit');
        $members = $data['member_ids'] ?? [];
        unset($data['member_ids']);
        $companyChanged = (int) $project->company_id !== (int) ($data['company_id'] ?? 0);
        $project->update($data);
        $project->members()->sync(array_unique(array_filter([...$members, $project->manager_id])));
        if ($companyChanged) {
            $project->tasks()->get()->each->update(['company_id' => $project->company_id]);
        }

        return redirect()->route('projects.show', $project)->with('success', 'Dane projektu zostały zapisane.');
    }

    public function copy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $this->prepareNewProjectNumber($request, 'projectCopy');

        $data = $request->validateWithBag('projectCopy', [
            'number' => ['required', 'string', 'max:100', Rule::unique('projects', 'number')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $copy = DB::transaction(function () use ($data, $project, $request): Project {
            $project->load(['members', 'tasks', 'requirements']);

            $copy = $project->replicate(['public_gantt_token']);
            $copy->fill([
                'number' => $data['number'],
                'name' => $data['name'],
                'status' => 'planned',
                'created_by' => $request->user()->id,
                'public_gantt_token' => null,
            ]);
            $copy->save();
            $copy->members()->sync($project->members->pluck('id')->push($copy->manager_id)->filter()->unique()->all());

            $taskIds = [];
            foreach ($project->tasks as $task) {
                $taskCopy = $task->replicate(['depends_on_task_id', 'deleted_by']);
                $taskCopy->fill([
                    'project_id' => $copy->id,
                    'company_id' => $copy->company_id,
                    'created_by' => $request->user()->id,
                    'depends_on_task_id' => null,
                    'status' => 'todo',
                    'progress' => 0,
                    'deleted_by' => null,
                ]);
                $taskCopy->save();
                $taskIds[$task->id] = $taskCopy->id;
            }

            foreach ($project->tasks as $task) {
                if ($task->depends_on_task_id && isset($taskIds[$task->depends_on_task_id])) {
                    Task::whereKey($taskIds[$task->id])->update(['depends_on_task_id' => $taskIds[$task->depends_on_task_id]]);
                }
            }

            foreach ($project->requirements as $requirement) {
                $requirementCopy = $requirement->replicate();
                $requirementCopy->fill([
                    'project_id' => $copy->id,
                    'status' => 'planned',
                    'created_by' => $request->user()->id,
                ]);
                $requirementCopy->save();
            }

            return $copy;
        });

        return redirect()->route('projects.show', $copy)
            ->with('success', 'Projekt został skopiowany. Dokumenty i finanse nie zostały przeniesione.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Projekt został usunięty z listy. Powiązane dane i pliki zachowano — nie zostały trwale skasowane.');
    }

    private function validateProject(Request $request, ?Project $project = null, ?string $errorBag = null): array
    {
        $rules = [
            'number' => ['required', 'string', 'max:100', Rule::unique('projects', 'number')->ignore($project?->id)],
            'name' => ['required', 'string', 'max:255'],
            'company_id' => [
                'nullable', 'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query
                    ->where('company_type', 'client')
                    ->whereNull('archived_at')),
            ],
            'manager_id' => ['required', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'status' => ['required', 'in:planned,active,on_hold,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => [$this->canViewProjectFinances($request->user()) ? 'required' : 'nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ];

        $data = $errorBag
            ? $request->validateWithBag($errorBag, $rules)
            : $request->validate($rules);

        if (! $this->canViewProjectFinances($request->user())) {
            $data['contract_value'] = $project?->contract_value ?? 0;
        }

        return $data;
    }

    private function prepareNewProjectNumber(Request $request, string $errorBag = 'default'): void
    {
        // Existing integrations may still submit a complete number. New forms use explicit components.
        if (! $request->hasAny(['number_date', 'number_sequence'])) {
            return;
        }

        $sequence = $request->input('number_sequence');
        if (is_string($sequence) && preg_match('/^[0-9]{1,7}$/D', $sequence)) {
            $request->merge(['number_sequence' => (int) $sequence]);
        }

        $parts = $request->validateWithBag($errorBag, [
            'number_date' => ['required', 'date_format:Y-m-d'],
            'number_sequence' => ['required', 'integer', 'min:1', 'max:9999999'],
        ]);
        $request->merge(['number' => Project::numberPrefix().str_replace('-', '', $parts['number_date']).'_'.str_pad((string) (int) $parts['number_sequence'], 3, '0', STR_PAD_LEFT)]);
    }

    private function canViewProjectFinances(User $user): bool
    {
        return app(AuditorAccessService::class)->hasFullAccess($user)
            || $user->canAny(['projects.finances.view', 'projects.finances.manage']);
    }

    private function staffUsers()
    {
        return User::whereHas('roles', fn ($query) => $query->whereNotIn('name', ['client_admin', 'client_user']))
            ->orderBy('name')->get();
    }
}
