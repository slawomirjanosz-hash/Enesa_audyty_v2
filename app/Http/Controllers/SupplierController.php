<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Project;
use App\Services\AuditorAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $query = Company::query()
            ->suppliers()
            ->active()
            ->withCount(['supplierRequirements', 'supplierFinancialEntries'])
            ->with(['supplierRequirements.project', 'supplierFinancialEntries.project']);

        $sortColumns = [
            'name' => 'name', 'contact' => 'email', 'capabilities' => 'supplier_sort_capabilities',
            'items' => 'supplier_requirements_count', 'projects' => 'supplier_projects_count',
        ];
        $query->selectRaw("companies.*, COALESCE(NULLIF(supplier_capabilities, ''), supplier_materials, '') as supplier_sort_capabilities")
            ->selectSub(Project::query()->selectRaw('COUNT(*)')->where(function ($projects) {
                $projects->whereHas('requirements', fn ($requirements) => $requirements->whereColumn('supplier_company_id', 'companies.id'))
                    ->orWhereHas('financialEntries', fn ($entries) => $entries->whereColumn('supplier_company_id', 'companies.id'));
            }), 'supplier_projects_count');
        // Keep counts added by withCount: selectRaw appends rather than replaces columns.
        $sortKey = $request->input('sort', 'name');
        $query->orderBy(is_string($sortKey) ? ($sortColumns[$sortKey] ?? 'name') : 'name', $request->input('direction') === 'desc' ? 'desc' : 'asc')
            ->orderBy('companies.id');

        $query = app(AuditorAccessService::class)->scopeByCompanyAccess(
            $query,
            $request->user(),
            'can_view_dashboard',
            'id'
        );

        if ($request->filled('q')) {
            $term = '%'.trim($request->string('q')->toString()).'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhere('nip', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('supplier_capabilities', 'like', $term)
                    ->orWhere('supplier_materials', 'like', $term);
            });
        }

        return view('suppliers.index', [
            'suppliers' => $query->paginate(24)->withQueryString(),
            'canCreateSupplier' => app(AuditorAccessService::class)->hasFullAccess($request->user())
                || $request->user()->can('crm.companies.manage') || $request->user()->can('crm.suppliers.create'),
        ]);
    }

    public function show(Company $supplier): View
    {
        abort_unless($supplier->company_type === 'supplier', 404);
        $this->authorize('view', $supplier);
        $supplier->load([
            'supplierRequirements.project.manager',
            'supplierFinancialEntries.project',
        ]);

        $projects = $supplier->supplierRequirements
            ->pluck('project')
            ->merge($supplier->supplierFinancialEntries->pluck('project'))
            ->filter()
            ->unique('id')
            ->sortByDesc('created_at')
            ->values();

        return view('suppliers.show', compact('supplier', 'projects'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user())
            || $request->user()->can('crm.companies.manage') || $request->user()->can('crm.suppliers.create'), 403);
        $request->merge(['company_type' => 'supplier']);

        return app(CompanyController::class)->store($request);
    }

    public function update(Request $request, Company $supplier): RedirectResponse
    {
        abort_unless($supplier->company_type === 'supplier', 404);
        $this->authorize('update', $supplier);
        $request->merge(['nip' => Company::normalizeNip($request->nip)]);
        $data = $request->validateWithBag('supplierEdit', [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'digits:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'supplier_capabilities' => ['nullable', 'string'],
            'supplier_materials' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
        $supplier->update($data);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Dane dostawcy zostały zapisane.');
    }
}
