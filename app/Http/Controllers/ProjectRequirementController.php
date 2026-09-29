<?php

namespace App\Http\Controllers;

use App\Exports\ProjectRequirementsListExport;
use App\Exports\ProjectRequirementsTemplateExport;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\User;
use App\Services\ProjectRequirementsImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectRequirementController extends Controller
{
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
            'include_project_context' => ['nullable', 'boolean'],
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
        $includeProjectContext = $request->boolean('include_project_context');
        $canViewMaterialPrices = $this->canViewRequirementPrice($request->user(), 'material');
        $canViewServicePrices = $this->canViewRequirementPrice($request->user(), 'service');
        $filename = implode('_', array_filter([
            $documentLabel,
            $includeProjectContext ? Str::slug($project->number, '_') : null,
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
            $includeProjectContext,
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

    private function throwRequirementsExportValidation(string $field, string $message): never
    {
        $exception = ValidationException::withMessages([$field => $message]);
        $exception->errorBag = 'requirementsExport';

        throw $exception;
    }

    private function canViewRequirementPrice(User $user, string $type): bool
    {
        return $user->hasRole('superadmin')
            || $user->can("projects.requirements.{$type}_prices.view");
    }
}
