<?php

namespace App\Http\Controllers;

use App\Exports\ProjectGanttExport;
use App\Exports\ProjectRequirementsListExport;
use App\Exports\ProjectRequirementsTemplateExport;
use App\Models\Company;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectFinanceGroup;
use App\Models\ProjectFinancialEntry;
use App\Models\ProjectRequirement;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditorAccessService;
use App\Services\ProjectGanttImportService;
use App\Services\ProjectRequirementsImportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Project::with(['company', 'manager', 'members'])
            ->withCount(['tasks', 'requirements'])
            ->orderByDesc('created_at');

        if (! app(AuditorAccessService::class)->hasFullAccess($user)) {
            $query->where(fn ($q) => $q->where('manager_id', $user->id)
                ->orWhereHas('members', fn ($members) => $members->whereKey($user->id)));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('projects.index', [
            'projects' => $query->paginate(20)->withQueryString(),
            'companies' => Company::clients()->active()->orderBy('name')->get(),
            'users' => $this->staffUsers(),
            'canViewFinances' => $this->canViewProjectFinances($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
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
        $project->load([
            'company', 'manager', 'members', 'tasks.assignedUser', 'tasks.dependency',
            'financialEntries.financeGroup', 'financialEntries.supplierCompany', 'financialEntries.projectRequirement', 'financeGroups.entries',
            'requirements.responsible', 'requirements.supplierCompany', 'documents.uploader',
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
            'canDeleteProject' => $user->hasAnyRole(['admin', 'superadmin']),
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
            $project->tasks()->update(['company_id' => $project->company_id]);
        }

        return redirect()->route('projects.show', $project)->with('success', 'Dane projektu zostały zapisane.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Projekt został usunięty z aktywnej listy.');
    }

    public function storeTask(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'depends_on_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:todo,in_progress,done'],
            'priority' => ['required', 'in:low,medium,high'],
            'progress' => ['required', 'integer', 'between:0,100'],
            'is_milestone' => ['sometimes', 'boolean'],
        ]);
        if ($data['is_milestone'] ?? false) {
            $data['due_date'] = $data['start_date'];
        }
        $this->validateProjectTaskAssignee($project, $data['assigned_to'] ?? null);
        $this->validateTaskDependency($project, null, $data['depends_on_task_id'] ?? null);
        $data['project_position'] = ((int) $project->tasks()->max('project_position')) + 1;
        $task = $project->tasks()->create($data + [
            'company_id' => $project->company_id,
            'created_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            $task->load('assignedUser');

            return response()->json($this->taskTimelinePayload($project, $task), 201);
        }

        return redirect()->back()->with('success', 'Zadanie zostało dodane.');
    }

    public function updateTask(Request $request, Project $project, Task $task): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($task->project_id === $project->id, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'depends_on_task_id' => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'status' => ['sometimes', 'in:todo,in_progress,done'],
            'progress' => ['required', 'integer', 'between:0,100'],
            'start_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'is_milestone' => ['sometimes', 'boolean'],
        ]);
        if (($data['is_milestone'] ?? $task->is_milestone) && isset($data['start_date'])) {
            $data['due_date'] = $data['start_date'];
        }
        if (array_key_exists('assigned_to', $data)) {
            $this->validateProjectTaskAssignee($project, $data['assigned_to']);
        }
        if (array_key_exists('depends_on_task_id', $data)) {
            $this->validateTaskDependency($project, $task, $data['depends_on_task_id']);
        }
        $oldEnd = $task->due_date?->copy();
        $data['status'] ??= match (true) {
            $data['progress'] >= 100 => 'done',
            $data['progress'] > 0 => 'in_progress',
            default => 'todo',
        };
        if ($data['status'] === 'done') {
            $data['progress'] = 100;
        }
        $task->update($data);
        if ($oldEnd && $task->due_date) {
            $shiftDays = (int) $oldEnd->diffInDays($task->due_date, false);
            if ($shiftDays !== 0) {
                $visited = [$task->id];
                $this->shiftDependentTasks($project, $task, $shiftDays, $visited);
            }
        }

        if ($request->expectsJson()) {
            $task->load('assignedUser');
            $payload = $this->taskTimelinePayload($project, $task);
            $payload['project_tasks'] = $project->tasks()->with('assignedUser')->get()
                ->map(fn (Task $projectTask) => $this->taskTimelinePayload($project, $projectTask))->values();

            return response()->json($payload);
        }

        return redirect()->back()->with('success', 'Zadanie zostało zaktualizowane.');
    }

    public function destroyTask(Request $request, Project $project, Task $task): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($task->project_id === $project->id, 404);
        $task->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Zadanie zostało usunięte.');
    }

    public function bulkDestroyTasks(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $data = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $taskIds = collect($data['task_ids'])->map(fn ($id) => (int) $id)->values();
        $tasks = $project->tasks()->whereKey($taskIds)->get();
        if ($tasks->count() !== $taskIds->count()) {
            throw ValidationException::withMessages([
                'task_ids' => 'Wybrane zadania muszą należeć do tego projektu.',
            ]);
        }

        DB::transaction(function () use ($project, $tasks) {
            $tasks->each->delete();
            $project->tasks()->orderBy('project_position')->orderBy('id')->pluck('id')
                ->each(fn ($taskId, $position) => Task::whereKey($taskId)->update(['project_position' => $position]));
        });

        return response()->json([
            'success' => true,
            'deleted' => $tasks->count(),
        ]);
    }

    public function reorderTasks(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'distinct', 'exists:tasks,id'],
        ]);
        $projectTaskIds = $project->tasks()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $requestedIds = collect($data['order'])->map(fn ($id) => (int) $id)->sort()->values();
        if ($projectTaskIds->all() !== $requestedIds->all()) {
            throw ValidationException::withMessages(['order' => 'Kolejność musi zawierać wszystkie zadania tego projektu.']);
        }
        foreach ($data['order'] as $position => $taskId) {
            Task::where('project_id', $project->id)->whereKey($taskId)->update(['project_position' => $position]);
        }

        return response()->json(['success' => true]);
    }

    public function exportGantt(Project $project): BinaryFileResponse
    {
        $this->authorize('view', $project);

        $filename = 'Harmonogram_'.Str::slug($project->number ?: $project->name, '_').'.xlsx';

        return Excel::download(new ProjectGanttExport($project), $filename);
    }

    public function importGantt(Request $request, Project $project, ProjectGanttImportService $importer): RedirectResponse
    {
        $this->authorize('view', $project);
        $data = $request->validateWithBag('ganttImport', [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'new_start_date' => ['nullable', 'date'],
        ]);

        $report = $importer->import($project, $data['file'], $data['new_start_date'] ?? null, $request->user());

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'gantt'])
            ->with('success', "Import harmonogramu zakończony: dodano {$report['inserted']} zadań, pominięto {$report['duplicates']} duplikatów.")
            ->with('gantt_import_report', $report);
    }

    public function storeFinancialEntry(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'type' => ['required', 'in:cost,invoice'],
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'supplier_company_id' => [
                'nullable', 'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query->where('company_type', 'supplier')->whereNull('archived_at')),
            ],
            'finance_group_id' => ['nullable', 'integer'],
            'entry_date' => ['required', 'date'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:planned,issued,paid'],
            'notes' => ['nullable', 'string'],
        ]);
        $data = $this->normalizeFinancialEntryRelations($project, $data);
        $project->financialEntries()->create($data + ['created_by' => $request->user()->id]);

        return $this->financeRedirect($project, 'Pozycja finansowa została dodana.');
    }

    public function updateFinancialEntry(Request $request, Project $project, ProjectFinancialEntry $entry): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($entry->project_id === $project->id, 404);
        $data = $request->validate([
            'type' => ['required', 'in:cost,invoice'],
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'supplier_company_id' => [
                'nullable', 'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query->where('company_type', 'supplier')->whereNull('archived_at')),
            ],
            'finance_group_id' => ['nullable', 'integer'],
            'entry_date' => ['required', 'date'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:planned,issued,paid'],
            'notes' => ['nullable', 'string'],
        ]);
        $data = $this->normalizeFinancialEntryRelations($project, $data);
        $entry->update($data);

        return $this->financeRedirect($project, 'Pozycja finansowa została zaktualizowana.');
    }

    public function updateFinancialEntryStatus(Request $request, Project $project, ProjectFinancialEntry $entry): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($entry->project_id === $project->id, 404);
        $data = $request->validate(['status' => ['required', 'in:planned,issued,paid']]);
        $entry->update(['status' => $data['status']]);
        $project->load('financialEntries.projectRequirement');

        return response()->json([
            'status' => $entry->status,
            'summary' => [
                'invoiced' => $project->totalInvoiced(),
                'planned_invoiced' => $project->plannedInvoiced(),
                'costs' => $project->totalCosts(),
                'planned_costs' => $project->plannedCosts(),
                'result' => $project->result(),
            ],
        ]);
    }

    public function importFinancialEntries(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'type' => ['required', 'in:cost,invoice'],
            'finance_group_id' => ['nullable', 'integer'],
            'new_group_name' => ['nullable', 'string', 'max:120'],
        ]);

        $groupId = $data['type'] === 'invoice'
            ? $this->issuedFinanceGroup($project)->id
            : $this->resolveFinanceGroup($project, $data['finance_group_id'] ?? null, $data['new_group_name'] ?? null);
        $sheet = Excel::toCollection(null, $data['file'])->first();
        if (! $sheet || $sheet->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'Plik nie zawiera danych.']);
        }

        $rows = $sheet->map(fn ($row) => collect($row)->values());
        $headerRowIndex = $rows->search(function ($row) {
            $candidate = $row->map(fn ($value) => $this->normalizeFinanceHeader($value));

            return $this->findFinanceColumn($candidate, ['data', 'data ksiegowania', 'data dokumentu', 'data wystawienia']) !== null
                && $this->findFinanceColumn($candidate, ['kwota netto', 'netto', 'wartosc netto', 'kwota wn', 'kwota', 'wartosc']) !== null;
        });
        if ($headerRowIndex === false) {
            throw ValidationException::withMessages(['file' => 'Nie znaleziono wiersza nagłówków.']);
        }
        $headers = $rows[$headerRowIndex]->map(fn ($value) => $this->normalizeFinanceHeader($value));
        $columns = [
            'date' => $this->findFinanceColumn($headers, ['data', 'data ksiegowania', 'data dokumentu', 'data wystawienia']),
            'supplier' => $this->findFinanceColumn($headers, ['podmiot', 'przedmiot', 'dostawca', 'kontrahent', 'klient']),
            'document' => $this->findFinanceColumn($headers, ['dokument', 'nr dokumentu', 'numer dokumentu', 'numer faktury', 'nr faktury']),
            'amount' => $this->findFinanceColumn($headers, ['kwota netto', 'netto', 'wartosc netto', 'kwota wn', 'kwota', 'wartosc']),
            'description' => $this->findFinanceColumn($headers, ['opis', 'nazwa', 'tytul', 'pozycja']),
            'status' => $this->findFinanceColumn($headers, ['status', 'stan']),
            'payment_date' => $this->findFinanceColumn($headers, ['data platnosci', 'termin platnosci', 'platnosc do']),
        ];
        if ($columns['date'] === null || $columns['amount'] === null) {
            throw ValidationException::withMessages(['file' => 'Plik musi zawierać kolumny Data oraz Kwota (np. Kwota netto).']);
        }

        $report = ['inserted' => 0, 'duplicates' => 0, 'invalid' => 0, 'inserted_amount' => 0.0, 'duplicate_amount' => 0.0, 'preview' => [], 'duplicate_preview' => []];
        $knownFingerprints = $project->financialEntries()->whereNotNull('import_fingerprint')->pluck('import_fingerprint')->flip();

        foreach ($rows->slice($headerRowIndex + 1) as $rowIndex => $row) {
            if ($row->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
                continue;
            }
            $date = $this->parseFinanceDate($this->financeCell($row, $columns['date']));
            $amount = $this->parseFinanceAmount($this->financeCell($row, $columns['amount']));
            if (! $date || $amount === null || $amount < 0) {
                $report['invalid']++;

                continue;
            }

            $supplier = $this->nullableFinanceText($this->financeCell($row, $columns['supplier']));
            $supplierCompanyId = $supplier
                ? Company::suppliers()->whereRaw('LOWER(name) = LOWER(?)', [$supplier])->value('id')
                : null;
            $document = $this->nullableFinanceText($this->financeCell($row, $columns['document']));
            $description = $this->nullableFinanceText($this->financeCell($row, $columns['description']));
            $paymentDate = $this->parseFinanceDate($this->financeCell($row, $columns['payment_date']));
            $status = $this->parseFinanceStatus($this->financeCell($row, $columns['status']), $data['type']);
            $name = $description ?: $supplier ?: $document ?: ($data['type'] === 'cost' ? 'Koszt z importu' : 'Faktura z importu');
            $fingerprint = $this->financeFingerprint($data['type'], $document, $date, $supplier, $amount, $description);
            $previewRow = ['row' => $rowIndex + 1, 'date' => $date, 'document' => $document, 'name' => $name, 'amount' => $amount];

            if ($knownFingerprints->has($fingerprint)) {
                $report['duplicates']++;
                $report['duplicate_amount'] += $amount;
                if (count($report['duplicate_preview']) < 15) {
                    $report['duplicate_preview'][] = $previewRow;
                }

                continue;
            }

            try {
                $project->financialEntries()->create([
                    'finance_group_id' => $groupId,
                    'type' => $data['type'],
                    'name' => Str::limit($name, 255, ''),
                    'document_number' => $document ? Str::limit($document, 100, '') : null,
                    'supplier' => $data['type'] === 'invoice' ? null : ($supplier ? Str::limit($supplier, 255, '') : null),
                    'supplier_company_id' => $data['type'] === 'invoice' ? null : $supplierCompanyId,
                    'entry_date' => $date,
                    'payment_date' => $paymentDate,
                    'amount' => $amount,
                    'status' => $status,
                    'source' => 'excel_import',
                    'import_row_order' => $rowIndex + 1,
                    'import_fingerprint' => $fingerprint,
                    'notes' => $description,
                    'created_by' => $request->user()->id,
                ]);
                $knownFingerprints->put($fingerprint, true);
                $report['inserted']++;
                $report['inserted_amount'] += $amount;
                if (count($report['preview']) < 15) {
                    $report['preview'][] = $previewRow;
                }
            } catch (Throwable $exception) {
                if ($project->financialEntries()->where('import_fingerprint', $fingerprint)->exists()) {
                    $knownFingerprints->put($fingerprint, true);
                    $report['duplicates']++;
                    $report['duplicate_amount'] += $amount;

                    continue;
                }
                throw $exception;
            }
        }

        return $this->financeRedirect($project, "Import zakończony: dodano {$report['inserted']}, pominięto duplikatów {$report['duplicates']} i błędnych wierszy {$report['invalid']}.")
            ->with('finance_import_report', $report);
    }

    public function storeFinanceGroup(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $project->financeGroups()->firstOrCreate(['name' => trim($data['name'])]);

        return $this->financeRedirect($project, 'Grupa finansowa została zapisana.');
    }

    public function bulkUpdateFinancialEntries(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', 'in:planned,issued,paid,delete'],
        ]);
        $entries = $project->financialEntries()->whereKey($data['entry_ids']);
        $count = (clone $entries)->count();
        if ($count !== count($data['entry_ids'])) {
            throw ValidationException::withMessages(['entry_ids' => 'Co najmniej jedna pozycja nie należy do tego projektu.']);
        }
        if ($data['action'] === 'delete') {
            $entries->delete();
            $message = "Usunięto {$count} pozycji finansowych.";
        } else {
            $entries->update(['status' => $data['action']]);
            $message = "Zmieniono status {$count} pozycji finansowych.";
        }

        return $this->financeRedirect($project, $message);
    }

    public function destroyFinanceGroup(Project $project, ProjectFinanceGroup $group): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($group->project_id === $project->id, 404);
        $group->delete();

        return $this->financeRedirect($project, 'Grupa została usunięta. Pozycje finansowe pozostały w rejestrze.');
    }

    public function destroyFinancialEntry(Project $project, ProjectFinancialEntry $entry): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($entry->project_id === $project->id, 404);
        $entry->delete();

        return $this->financeRedirect($project, 'Pozycja finansowa została usunięta.');
    }

    public function storeRequirement(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $this->requirementData($request);
        $project->requirements()->create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'requirements'])
            ->with('success', 'Zapotrzebowanie zostało dodane.');
    }

    public function downloadRequirementsTemplate(Project $project): BinaryFileResponse
    {
        $this->authorize('view', $project);

        return Excel::download(
            new ProjectRequirementsTemplateExport,
            'Wzor_materialy_i_uslugi.xlsx'
        );
    }

    public function exportRequirements(Request $request, Project $project): BinaryFileResponse
    {
        $this->authorize('view', $project);
        $statusLabels = [
            'planned' => 'Planowane',
            'requested' => 'Zapotrzebowanie',
            'ordered' => 'Zamówione',
            'in_progress' => 'W realizacji',
            'purchased' => 'Kupione',
            'cancelled' => 'Anulowane',
        ];
        $data = $request->validateWithBag('requirementsExport', [
            'document_type' => ['required', 'in:inquiry,order'],
            'supplier_filter' => ['nullable', 'string', 'max:500'],
            'all_statuses' => ['nullable', 'boolean'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', Rule::in(array_keys($statusLabels))],
            'include_prices' => ['nullable', 'boolean'],
        ]);

        $statuses = $request->boolean('all_statuses')
            ? array_keys($statusLabels)
            : array_values(array_unique($data['statuses'] ?? []));
        if ($statuses === []) {
            $this->throwRequirementsExportValidation('statuses', 'Wybierz co najmniej jeden status do eksportu.');
        }

        $query = $project->requirements()
            ->with(['supplierCompany'])
            ->whereIn('status', $statuses)
            ->reorder()
            ->orderBy('name');
        $supplierLabel = 'Wszyscy dostawcy';
        $supplierFilter = (string) ($data['supplier_filter'] ?? '');

        if (str_starts_with($supplierFilter, 'company:')) {
            $supplierId = (int) Str::after($supplierFilter, 'company:');
            $supplier = Company::suppliers()->active()->find($supplierId);
            if (! $supplier) {
                $this->throwRequirementsExportValidation('supplier_filter', 'Wybrany dostawca nie jest dostępny.');
            }
            $query->where('supplier_company_id', $supplier->id);
            $supplierLabel = $supplier->name;
        } elseif (str_starts_with($supplierFilter, 'external:')) {
            $supplierName = trim(Str::after($supplierFilter, 'external:'));
            if ($supplierName === '') {
                $this->throwRequirementsExportValidation('supplier_filter', 'Wybierz prawidłowego dostawcę.');
            }
            $query->whereNull('supplier_company_id')->where('supplier', $supplierName);
            $supplierLabel = $supplierName;
        } elseif ($supplierFilter !== '') {
            $this->throwRequirementsExportValidation('supplier_filter', 'Wybierz prawidłowego dostawcę.');
        }

        $requirements = $query->get();
        if ($requirements->isEmpty()) {
            $this->throwRequirementsExportValidation('statuses', 'Brak pozycji pasujących do wybranego dostawcy i statusów.');
        }

        $documentLabel = $data['document_type'] === 'order' ? 'Zamowienie' : 'Zapytanie_ofertowe';
        $includePrices = $data['document_type'] === 'order' || $request->boolean('include_prices');
        $canViewMaterialPrices = $this->canViewRequirementPrice($request->user(), 'material');
        $canViewServicePrices = $this->canViewRequirementPrice($request->user(), 'service');
        $filename = implode('_', array_filter([
            $documentLabel,
            Str::slug($project->number, '_'),
            Str::slug($supplierLabel, '_'),
            now()->format('Y-m-d'),
        ])).'.xlsx';

        return Excel::download(new ProjectRequirementsListExport(
            $project,
            $requirements,
            $data['document_type'],
            $supplierLabel,
            $statuses,
            $includePrices,
            $canViewMaterialPrices,
            $canViewServicePrices,
        ), $filename);
    }

    public function importRequirements(Request $request, Project $project, ProjectRequirementsImportService $importer): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validateWithBag('requirementsImport', [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);
        $report = $importer->import($project, $data['file'], $request->user());

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'requirements'])
            ->with('success', "Import zakończony: dodano {$report['inserted']}, pominięto duplikatów {$report['duplicates']} i błędnych wierszy {$report['invalid']}.")
            ->with('requirements_import_report', $report);
    }

    public function updateRequirement(Request $request, Project $project, ProjectRequirement $requirement): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($requirement->project_id === $project->id, 404);
        $data = $this->requirementData($request);
        $requirement->update($data);

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'requirements'])
            ->with('success', 'Materiał lub usługa zostały zaktualizowane.');
    }

    public function updateRequirementStatus(Request $request, Project $project, ProjectRequirement $requirement): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($requirement->project_id === $project->id, 404);
        $data = $request->validate(['status' => ['required', 'in:planned,requested,ordered,in_progress,purchased,cancelled']]);
        $previousFinancialEntryId = $requirement->financialEntry()->where('source', 'requirement')->value('id');
        $requirement->update(['status' => $data['status']]);
        $requirement->load('financialEntry');
        $project->load('financialEntries.projectRequirement');

        return response()->json([
            'status' => $requirement->status,
            'committed_requirements' => (float) $project->requirements()
                ->whereIn('status', ['ordered', 'in_progress', 'purchased'])
                ->sum('estimated_cost'),
            'planned_requirements' => (float) $project->requirements()->where('status', 'planned')->sum('estimated_cost'),
            'financial_entry' => $requirement->status === 'purchased' && $requirement->financialEntry ? [
                'id' => $requirement->financialEntry->id,
                'date' => $requirement->financialEntry->entry_date->format('Y-m-d'),
                'amount' => (float) $requirement->financialEntry->amount,
                'type' => $requirement->financialEntry->type,
                'status' => $requirement->financialEntry->status,
                'name' => $requirement->financialEntry->name,
            ] : null,
            'removed_financial_entry_id' => $requirement->status !== 'purchased' ? $previousFinancialEntryId : null,
            'planned_requirement_entry' => $requirement->status === 'planned' && $requirement->estimated_cost !== null ? [
                'id' => 'requirement-'.$requirement->id,
                'date' => $requirement->needed_by?->format('Y-m-d') ?? now()->toDateString(),
                'amount' => (float) $requirement->estimated_cost,
                'type' => 'cost',
                'status' => 'planned',
                'name' => $requirement->name,
            ] : null,
            'summary' => [
                'invoiced' => $project->totalInvoiced(),
                'planned_invoiced' => $project->plannedInvoiced(),
                'costs' => $project->totalCosts(),
                'planned_costs' => $project->plannedCosts(),
                'result' => $project->result(),
            ],
        ]);
    }

    public function bulkUpdateRequirements(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'requirement_ids' => ['required', 'array', 'min:1'],
            'requirement_ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', 'in:delete,set_status,set_supplier,set_responsible,set_needed_by,set_type,set_technology'],
            'status' => ['nullable', 'required_if:action,set_status', 'in:planned,requested,ordered,in_progress,purchased,cancelled'],
            'supplier_company_id' => [
                'nullable', 'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query->where('company_type', 'supplier')->where('status', 'active')->whereNull('archived_at')),
            ],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'needed_by' => ['nullable', 'date'],
            'type' => ['nullable', 'required_if:action,set_type', 'in:material,service'],
            'technology' => ['nullable', 'string', 'max:255'],
        ]);

        $requirements = $project->requirements()->whereKey($data['requirement_ids'])->get();
        if ($requirements->count() !== count($data['requirement_ids'])) {
            throw ValidationException::withMessages(['requirement_ids' => 'Co najmniej jedna pozycja nie należy do tego projektu.']);
        }

        if ($data['action'] === 'set_responsible' && ! empty($data['responsible_id'])) {
            $eligibleIds = $project->members()->pluck('users.id')->push($project->manager_id)->filter()->map(fn ($id) => (int) $id);
            if (! $eligibleIds->contains((int) $data['responsible_id'])) {
                throw ValidationException::withMessages(['responsible_id' => 'Odpowiedzialny musi należeć do zespołu projektu.']);
            }
        }

        $supplier = $data['action'] === 'set_supplier' && ! empty($data['supplier_company_id'])
            ? Company::find($data['supplier_company_id'])
            : null;

        DB::transaction(function () use ($requirements, $data, $supplier): void {
            foreach ($requirements as $requirement) {
                match ($data['action']) {
                    'delete' => $requirement->delete(),
                    'set_status' => $requirement->update(['status' => $data['status']]),
                    'set_supplier' => $requirement->update([
                        'supplier_company_id' => $supplier?->id,
                        'supplier' => $supplier?->name,
                    ]),
                    'set_responsible' => $requirement->update(['responsible_id' => $data['responsible_id'] ?? null]),
                    'set_needed_by' => $requirement->update(['needed_by' => $data['needed_by'] ?? null]),
                    'set_type' => $requirement->update(['type' => $data['type']]),
                    'set_technology' => $requirement->update(['technology' => $data['technology'] ?? null]),
                };
            }
        });

        $messages = [
            'delete' => 'Usunięto zaznaczone materiały i usługi.',
            'set_status' => 'Zmieniono status zaznaczonych pozycji.',
            'set_supplier' => 'Zmieniono dostawcę zaznaczonych pozycji.',
            'set_responsible' => 'Zmieniono osobę odpowiedzialną za zaznaczone pozycje.',
            'set_needed_by' => 'Zmieniono termin zaznaczonych pozycji.',
            'set_type' => 'Zmieniono rodzaj zaznaczonych pozycji.',
            'set_technology' => 'Zmieniono technologię zaznaczonych pozycji.',
        ];

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'requirements'])
            ->with('success', $messages[$data['action']]);
    }

    public function destroyRequirement(Project $project, ProjectRequirement $requirement): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($requirement->project_id === $project->id, 404);
        $requirement->delete();

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'requirements'])
            ->with('success', 'Zapotrzebowanie zostało usunięte.');
    }

    private function requirementData(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:material,service'],
            'name' => ['required', 'string', 'max:255'],
            'technology' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:30'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'supplier_company_id' => [
                'nullable', 'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query->where('company_type', 'supplier')->whereNull('archived_at')),
            ],
            'status' => ['required', 'in:planned,requested,ordered,in_progress,purchased,cancelled'],
            'needed_by' => ['nullable', 'date'],
            'responsible_id' => ['nullable', 'exists:users,id'],
        ]);
        $unit = trim((string) ($data['unit'] ?? ''));
        if ($unit === '' || is_numeric(str_replace(',', '.', $unit))) {
            $unit = $data['type'] === 'material' ? 'szt.' : 'usł.';
        }
        $data['unit'] = $unit;
        if ($request->exists('unit_cost')) {
            $unitCost = $data['unit_cost'] ?? null;
            $data['estimated_cost'] = $unitCost === null
                ? null
                : round((float) $unitCost * (float) $data['quantity'], 2);
        }
        if (! $this->canViewRequirementPrice($request->user(), $data['type'])) {
            unset($data['estimated_cost']);
        }
        unset($data['unit_cost']);
        if (! empty($data['supplier_company_id'])) {
            $data['supplier'] = Company::find($data['supplier_company_id'])?->name;
        }

        return $data;
    }

    public function storeDocument(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
        ]);
        $file = $data['file'];
        $originalName = $file->getClientOriginalName();
        $safeName = now()->format('YmdHis').'_'.Str::random(10).'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $relativePath = 'projects/'.$project->id.'/'.$safeName;
        Storage::disk('local')->put($relativePath, $file->getContent());
        Document::create([
            'project_id' => $project->id,
            'company_id' => $project->company_id,
            'type' => 'upload',
            'original_filename' => $originalName,
            'stored_path' => $relativePath,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'documents'])
            ->with('success', 'Dokument projektu został dodany i zapisany.');
    }

    public function downloadDocument(Project $project, Document $document)
    {
        $this->authorize('view', $project);
        abort_unless($document->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function destroyDocument(Project $project, Document $document): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($document->project_id === $project->id, 404);
        Storage::disk('local')->delete($document->stored_path);
        $document->delete();

        return redirect()->back()->with('success', 'Dokument projektu został usunięty.');
    }

    public function generatePublicGantt(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        if (! $project->public_gantt_token) {
            $project->update(['public_gantt_token' => Str::random(48)]);
        }

        return response()->json(['url' => route('projects.public-gantt', $project->public_gantt_token)]);
    }

    public function publicGantt(string $token): View
    {
        $project = Project::where('public_gantt_token', $token)
            ->with(['tasks.assignedUser', 'tasks.dependency'])
            ->firstOrFail();
        $timelineItems = $project->tasks->filter(fn ($task) => $task->start_date && $task->due_date)->map(fn ($task) => [
            'id' => 'task-'.$task->id,
            'name' => $task->title,
            'start' => $task->start_date->format('Y-m-d'),
            'end' => $task->due_date->format('Y-m-d'),
            'progress' => $task->progress,
            'dependencies' => $task->depends_on_task_id ? 'task-'.$task->depends_on_task_id : '',
            'kind' => $task->is_milestone ? 'milestone' : 'task',
            'is_milestone' => $task->is_milestone,
            'assignee' => $task->assignedUser?->name,
        ])->values();

        return view('projects.public-gantt', compact('project', 'timelineItems'));
    }

    private function validateTaskDependency(Project $project, ?Task $task, ?int $dependencyId): void
    {
        if (! $dependencyId) {
            return;
        }
        $dependency = Task::where('project_id', $project->id)->find($dependencyId);
        if (! $dependency) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'Zadanie zależne musi należeć do tego samego projektu.']);
        }
        if ($task && $dependency->id === $task->id) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'Zadanie nie może zależeć samo od siebie.']);
        }
        $visited = [];
        while ($dependency) {
            if (in_array($dependency->id, $visited, true) || ($task && $dependency->id === $task->id)) {
                throw ValidationException::withMessages(['depends_on_task_id' => 'Ta zależność utworzyłaby zamkniętą pętlę zadań.']);
            }
            $visited[] = $dependency->id;
            $dependency = $dependency->depends_on_task_id
                ? Task::where('project_id', $project->id)->find($dependency->depends_on_task_id)
                : null;
        }
    }

    private function shiftDependentTasks(Project $project, Task $task, int $days, array &$visited): void
    {
        $dependents = Task::where('project_id', $project->id)->where('depends_on_task_id', $task->id)->get();
        foreach ($dependents as $dependent) {
            if (in_array($dependent->id, $visited, true)) {
                continue;
            }
            $visited[] = $dependent->id;
            $dependent->update([
                'start_date' => Carbon::parse($dependent->start_date)->addDays($days),
                'due_date' => Carbon::parse($dependent->due_date)->addDays($days),
            ]);
            $this->shiftDependentTasks($project, $dependent, $days, $visited);
        }
    }

    private function taskTimelinePayload(Project $project, Task $task): array
    {
        return [
            'kind' => $task->is_milestone ? 'milestone' : 'task',
            'id' => 'task-'.$task->id,
            'db_id' => $task->id,
            'name' => $task->title,
            'start' => $task->start_date?->format('Y-m-d'),
            'end' => $task->due_date?->format('Y-m-d'),
            'progress' => $task->progress,
            'status' => $task->status,
            'priority' => $task->priority,
            'description' => $task->description,
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->assignedUser?->name,
            'is_milestone' => $task->is_milestone,
            'dependencies' => $task->depends_on_task_id ? 'task-'.$task->depends_on_task_id : '',
            'update_url' => route('projects.tasks.update', [$project, $task]),
            'delete_url' => route('projects.tasks.destroy', [$project, $task]),
            'position' => $task->project_position,
        ];
    }

    private function validateFinanceGroup(Project $project, ?int $groupId): void
    {
        if ($groupId && ! $project->financeGroups()->whereKey($groupId)->exists()) {
            throw ValidationException::withMessages(['finance_group_id' => 'Wybrana grupa nie należy do tego projektu.']);
        }
    }

    private function normalizeFinancialEntryRelations(Project $project, array $data): array
    {
        if ($data['type'] === 'invoice') {
            $data['finance_group_id'] = $this->issuedFinanceGroup($project)->id;
            $data['supplier'] = null;
            $data['supplier_company_id'] = null;

            return $data;
        }

        $this->validateFinanceGroup($project, $data['finance_group_id'] ?? null);
        if (! empty($data['supplier_company_id'])) {
            $data['supplier'] = Company::find($data['supplier_company_id'])?->name;
        }

        return $data;
    }

    private function issuedFinanceGroup(Project $project): ProjectFinanceGroup
    {
        return $project->financeGroups()->firstOrCreate(['name' => 'Wystawione']);
    }

    private function resolveFinanceGroup(Project $project, ?int $groupId, ?string $newGroupName): ?int
    {
        if ($newGroupName && trim($newGroupName) !== '') {
            return $project->financeGroups()->firstOrCreate(['name' => trim($newGroupName)])->id;
        }
        $this->validateFinanceGroup($project, $groupId);

        return $groupId;
    }

    private function normalizeFinanceHeader(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }

    private function findFinanceColumn($headers, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $index = $headers->search($alias, true);
            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }

    private function financeCell($row, ?int $column): mixed
    {
        return $column === null ? null : $row->get($column);
    }

    private function nullableFinanceText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function parseFinanceDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            }
            $text = trim((string) $value);
            foreach (['d.m.Y', 'd-m-Y', 'Y-m-d', 'd/m/Y', 'Y/m/d'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $text)->format('Y-m-d');
                } catch (Throwable) {
                    // Try the next common spreadsheet date format.
                }
            }

            return Carbon::parse($text)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseFinanceAmount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }
        $text = preg_replace('/[^0-9,.-]/u', '', (string) ($value ?? ''));
        if ($text === '' || $text === null) {
            return null;
        }
        $lastComma = strrpos($text, ',');
        $lastDot = strrpos($text, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif ($lastDot !== false) {
            $text = str_replace(',', '', $text);
        }

        return is_numeric($text) ? round((float) $text, 2) : null;
    }

    private function parseFinanceStatus(mixed $value, string $type): string
    {
        $status = $this->normalizeFinanceHeader($value);
        if (Str::contains($status, ['oplac', 'zapla', 'paid', 'rozlicz'])) {
            return 'paid';
        }
        if (Str::contains($status, ['wystaw', 'zaksi', 'issued', 'otrzym'])) {
            return 'issued';
        }

        return $type === 'cost' && $status === '' ? 'issued' : 'planned';
    }

    private function financeFingerprint(string $type, ?string $document, string $date, ?string $supplier, float $amount, ?string $description): string
    {
        $normalizedDocument = $this->normalizeFinanceHeader($document);
        $identity = $normalizedDocument !== ''
            ? "document|{$normalizedDocument}|".number_format($amount, 2, '.', '')
            : implode('|', ['row', $date, $this->normalizeFinanceHeader($supplier), number_format($amount, 2, '.', ''), $this->normalizeFinanceHeader($description)]);

        return hash('sha256', $type.'|'.$identity);
    }

    private function financeRedirect(Project $project, string $message): RedirectResponse
    {
        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'finances'])->with('success', $message);
    }

    private function throwRequirementsExportValidation(string $field, string $message): never
    {
        $exception = ValidationException::withMessages([$field => $message]);
        $exception->errorBag = 'requirementsExport';

        throw $exception;
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

    private function canViewProjectFinances(User $user): bool
    {
        return app(AuditorAccessService::class)->hasFullAccess($user)
            || $user->canAny(['projects.finances.view', 'projects.finances.manage']);
    }

    private function canViewRequirementPrice(User $user, string $type): bool
    {
        return $user->hasRole('superadmin')
            || $user->can("projects.requirements.{$type}_prices.view");
    }

    private function validateProjectTaskAssignee(Project $project, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $belongsToTeam = (int) $project->manager_id === $userId
            || $project->members()->whereKey($userId)->exists();

        if (! $belongsToTeam) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Zadanie można przypisać tylko użytkownikowi należącemu do tego projektu.',
            ]);
        }
    }

    private function staffUsers()
    {
        return User::whereHas('roles', fn ($query) => $query->whereNotIn('name', ['client_admin', 'client_user']))
            ->orderBy('name')->get();
    }
}
