<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Cylinder;
use App\Models\CylinderInspection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CylinderController extends Controller
{
    private function clientView(Request $request): bool
    {
        return $request->routeIs('client.cylinders.*', 'client-zone.cylinders.*');
    }

    private function query(Request $request): Builder
    {
        $query = Cylinder::query();
        if ($request->routeIs('client.cylinders.*')) {
            $query->whereIn('company_id', $request->user()->companies()->select('companies.id'));
        } elseif ($request->routeIs('client-zone.cylinders.*')) {
            $query->where('company_id', $request->session()->get('client_zone_company_id'));
        }

        return $query;
    }

    private function viewData(Request $request): array
    {
        $client = $this->clientView($request);

        return [
            'clientView' => $client,
            'layout' => $request->routeIs('client-zone.*') ? 'layouts.client-zone' : ($client ? 'layouts.client' : 'layouts.app'),
            'routePrefix' => $request->routeIs('client-zone.*') ? 'client-zone.cylinders.' : ($client ? 'client.cylinders.' : 'cylinders.'),
            'canManage' => ! $client && ($request->user()->hasRole('superadmin') || $request->user()->canAny(['system.full_access', 'cylinders.manage'])),
        ];
    }

    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'archived' => ['nullable', 'in:1']]);
        $query = $this->query($request)->with(['company', 'latestInspection']);
        $query->when($request->boolean('archived'), fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'));
        if ($search = trim($data['q'] ?? '')) {
            $query->where(fn ($q) => $q->where('serial_number', 'like', '%'.$search.'%')->orWhere('type', 'like', '%'.$search.'%')->orWhereHas('company', fn ($c) => $c->where('name', 'like', '%'.$search.'%')));
        }

        return view('cylinders.index', $this->viewData($request) + ['cylinders' => $query->orderBy('serial_number')->paginate(30)->withQueryString()]);
    }

    public function create(Request $request): View
    {
        return view('cylinders.form', $this->viewData($request) + ['cylinder' => new Cylinder, 'companies' => Company::clients()->active()->orderBy('name')->get(['id', 'name'])]);
    }

    public function edit(Request $request, Cylinder $cylinder): View
    {
        return view('cylinders.form', $this->viewData($request) + ['cylinder' => $cylinder->load('company'), 'companies' => collect()]);
    }

    private function validated(Request $request, ?Cylinder $cylinder = null): array
    {
        return $request->validate([
            // Owner cannot be changed after creation: history must not move between clients.
            'company_id' => $cylinder ? ['prohibited'] : ['required', 'integer', Rule::exists('companies', 'id')->where('company_type', 'client')->whereNull('archived_at')],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('cylinders')->where('company_id', $cylinder?->company_id ?? $request->input('company_id'))->ignore($cylinder?->id)],
            'manufacturer' => ['nullable', 'string', 'max:160'],
            'type' => ['required', 'string', 'max:160'],
            'manufactured_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'capacity_litres' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'working_pressure_bar' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cylinder = Cylinder::create($this->validated($request));

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Butla została dodana.');
    }

    public function update(Request $request, Cylinder $cylinder): RedirectResponse
    {
        $cylinder->update($this->validated($request, $cylinder));

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Dane butli zostały zapisane.');
    }

    public function show(Request $request, Cylinder $cylinder): View
    {
        $this->query($request)->whereKey($cylinder->id)->firstOrFail();

        return view('cylinders.show', $this->viewData($request) + [
            'cylinder' => $cylinder->load('company'),
            'inspections' => $cylinder->inspections()->orderByDesc('inspected_at')->orderByDesc('id')->paginate(20),
            'results' => CylinderInspection::RESULTS,
        ]);
    }

    public function archive(Request $request, Cylinder $cylinder): RedirectResponse
    {
        $data = $request->validate(['archived' => ['required', 'boolean']]);
        DB::transaction(function () use ($cylinder, $data): void {
            $locked = Cylinder::query()->lockForUpdate()->findOrFail($cylinder->id);
            $locked->update(['archived_at' => $data['archived'] ? now() : null]);
        });

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Zmieniono status archiwizacji. Historia została zachowana.');
    }

    public function storeInspection(Request $request, Cylinder $cylinder): RedirectResponse
    {
        $data = $request->validate([
            'inspected_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'next_due_at' => ['nullable', 'date_format:Y-m-d', 'after:inspected_at'],
            'result' => ['required', Rule::in(array_keys(CylinderInspection::RESULTS))],
            'observations' => ['required', 'string', 'max:20000'],
        ]);
        DB::transaction(function () use ($request, $cylinder, $data): void {
            $locked = Cylinder::query()->lockForUpdate()->findOrFail($cylinder->id);
            abort_if($locked->archived_at, 409, 'Przywróć butlę z archiwum przed zapisaniem przeglądu.');
            $locked->inspections()->create($data + ['inspector_id' => $request->user()->id, 'inspector_name' => $request->user()->name]);
        });

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Zapisano przegląd. Wpis pozostaje w historii bez możliwości nadpisania.');
    }
}
