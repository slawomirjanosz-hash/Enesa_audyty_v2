<?php

namespace App\Http\Controllers;

use App\Models\Company;
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
            ->with(['supplierRequirements.project', 'supplierFinancialEntries.project'])
            ->orderBy('name');

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

    public function update(Request $request, Company $supplier): RedirectResponse
    {
        abort_unless($supplier->company_type === 'supplier', 404);
        $this->authorize('update', $supplier);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:20'],
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
