<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ExpenseInvoice;
use App\Models\User;
use App\Services\AuditorAccessService;
use App\Services\ExpenseScan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseInvoiceController extends Controller
{
    private function accounting(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can('expenses.accounting');
    }

    private function access(User $user): void
    {
        abort_if($user->hasAnyRole(['client_admin', 'client_user']), 403);
        abort_unless($this->accounting($user) || $user->can('expenses.use'), 403);
    }

    private function visible(User $user): Builder
    {
        $query = ExpenseInvoice::query();
        if (! $this->accounting($user)) {
            $query->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere(fn ($pool) => $pool->where('is_unassigned', true)->whereNull('owner_id')));
        }

        return $query;
    }

    public function index(Request $request)
    {
        $this->access($request->user());
        $accounting = $this->accounting($request->user());
        $tab = $request->input('tab', 'own');
        abort_unless(in_array($tab, $accounting ? ['own', 'pool', 'all'] : ['own', 'pool'], true), 403);
        $query = $this->visible($request->user())->with('owner');
        if ($tab === 'own') {
            $query->where('owner_id', $request->user()->id);
        } elseif ($tab === 'pool') {
            $query->where('is_unassigned', true)->whereNull('owner_id');
        }
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'search' => ['nullable', 'string', 'max:100']]);
        if (! empty($filters['month'])) {
            $start = Carbon::createFromFormat('Y-m-d', $filters['month'].'-01');
            $query->whereBetween('purchase_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
        }
        if (! empty($filters['search'])) {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']).'%';
            $query->where(function ($q) use ($search) {
                foreach (['invoice_number', 'wz_number', 'ksef_number', 'supplier_name', 'supplier_nip', 'owner_name', 'cost_center', 'paid_by', 'description'] as $field) {
                    $q->orWhere($field, 'like', $search);
                }
            });
        }
        $sorts = ['id', 'purchase_date', 'invoice_number', 'ksef_number', 'wz_number', 'supplier_name', 'net_amount', 'gross_amount', 'owner_name', 'payment_method', 'payment_source', 'payment_status', 'paid_by', 'cost_center', 'description'];
        $sort = in_array($request->input('table_sort'), $sorts, true) ? $request->input('table_sort') : 'purchase_date';
        $direction = $request->input('table_direction') === 'asc' ? 'asc' : 'desc';
        if ($request->input('table_sort') === 'status') {
            $query->orderByRaw("CASE WHEN settled_at IS NOT NULL THEN 'Rozliczona' WHEN is_unassigned = true THEN 'Do przypisania' ELSE 'Do rozliczenia' END ".$direction);
        } else {
            $labels = ['payment_method' => ExpenseInvoice::METHODS, 'payment_source' => ExpenseInvoice::SOURCES, 'payment_status' => ExpenseInvoice::PAYMENTS];
            if (isset($labels[$sort])) {
                $expression = $sort === 'payment_status' ? "CASE WHEN settled_at IS NOT NULL THEN 'Rozliczona — zamknięta' ELSE CASE ".$sort : 'CASE '.$sort;
                $bindings = [];
                foreach ($labels[$sort] as $key => $label) {
                    $expression .= ' WHEN ? THEN ?';
                    $bindings[] = $key;
                    $bindings[] = $label;
                }
                $query->orderByRaw($expression.' ELSE NULL END'.($sort === 'payment_status' ? ' END' : '').' '.$direction, $bindings);
            } else {
                $query->orderBy($sort, $direction);
            }
        }
        $invoices = $query->orderBy('id', $direction)->paginate(30)->withQueryString();

        return view($request->header('X-Expense-Results') === '1' ? 'expenses.results' : 'expenses.index', compact('invoices', 'accounting', 'tab'));
    }

    public function create(Request $request)
    {
        $this->access($request->user());

        return $this->form($request, new ExpenseInvoice(['purchase_date' => today(), 'payment_method' => 'unknown', 'payment_source' => 'unknown', 'payment_status' => 'unknown']));
    }

    public function edit(Request $request, ExpenseInvoice $invoice)
    {
        $this->access($request->user());
        abort_unless($this->visible($request->user())->whereKey($invoice->id)->exists(), 404);

        return $this->form($request, $invoice);
    }

    private function form(Request $request, ExpenseInvoice $invoice)
    {
        $accounting = $this->accounting($request->user());
        $editable = ! $invoice->settled_at && ($accounting || ! $invoice->exists || $invoice->owner_id === $request->user()->id);
        $employees = $accounting ? User::where('is_active', true)->whereHas('roles')->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))->orderBy('name')->get(['id', 'name']) : collect();
        // Expense entry needs only supplier names and NIPs, not access to CRM records.
        $supplierQuery = Company::suppliers()->active();
        $companyAccess = app(AuditorAccessService::class);
        if ($companyAccess->isDelegatedAuditor($request->user())) {
            $companyAccess->scopeByCompanyAccess($supplierQuery, $request->user(), 'can_view_dashboard', 'id');
        }
        $suppliers = $supplierQuery->orderBy('name')->get(['id', 'name', 'nip']);

        return view('expenses.form', compact('invoice', 'accounting', 'editable', 'employees', 'suppliers'));
    }

    private function data(Request $request, ?ExpenseInvoice $invoice = null): array
    {
        $rules = [
            'purchase_date' => ['required', 'date_format:Y-m-d'], 'invoice_number' => ['nullable', 'string', 'max:255'],
            'ksef_number' => ['nullable', 'string', 'max:100', Rule::unique('expense_invoices', 'ksef_number')->ignore($invoice?->id)], 'wz_number' => ['nullable', 'string', 'max:255'],
            'supplier_name' => ['required', 'string', 'max:255'], 'supplier_nip' => ['nullable', 'string', 'max:20'],
            'net_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'gross_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'vat_rate' => ['nullable', Rule::in(['0', '5', '8', '23', '0.00', '5.00', '8.00', '23.00'])],
            'payment_method' => ['required', Rule::in(array_keys(ExpenseInvoice::METHODS))],
            'payment_source' => ['required', Rule::in(array_keys(ExpenseInvoice::SOURCES))],
            'payment_status' => ['required', Rule::in(array_keys(ExpenseInvoice::PAYMENTS))],
            'paid_by' => ['nullable', 'string', 'max:255'], 'cost_center' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
        ];
        if ($request->filled('net_amount')) {
            $rules['gross_amount'][] = 'gte:net_amount';
        }
        if ($invoice) {
            $rules['revision'] = ['required', 'integer', 'min:1'];
        }
        if ($this->accounting($request->user())) {
            $rules['owner_mode'] = ['nullable', Rule::in(['user', 'manual', 'pool'])];
            $rules['owner_name'] = ['nullable', 'string', 'max:255', 'required_if:owner_mode,manual'];
            $rules['owner_id'] = ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')->where('is_active', true)];
            $rules['accounting_note'] = ['nullable', 'string', 'max:5000'];
        }
        $data = $request->validate($rules);
        if ($this->accounting($request->user())) {
            $mode = $data['owner_mode'] ?? (! empty($data['owner_id']) ? 'user' : 'pool');
            if ($mode === 'user') {
                $request->validate(['owner_id' => ['required', 'integer']]);
            }
            $owner = $mode === 'user' && ! empty($data['owner_id']) ? User::findOrFail($data['owner_id']) : null;
            abort_if($owner && ($owner->roles->isEmpty() || $owner->hasAnyRole(['client_admin', 'client_user'])), 422, 'Wybierz pracownika.');
            $data['owner_id'] = $owner?->id;
            $data['owner_name'] = $mode === 'manual' ? $data['owner_name'] : $owner?->name;
            $data['is_unassigned'] = $mode === 'pool';
        } elseif (! $invoice) {
            $data['owner_id'] = $request->user()->id;
            $data['owner_name'] = $request->user()->name;
            $data['is_unassigned'] = false;
        }
        if ($data['payment_status'] === 'settled') {
            abort_unless($this->accounting($request->user()), 403);
            $request->validate([
                'gross_amount' => ['required', 'numeric'],
                'payment_method' => [Rule::notIn(['unknown'])],
                'payment_source' => [Rule::notIn(['unknown'])],
                'paid_by' => ['required', 'string'],
            ], [
                'gross_amount.required' => 'Przed zamknięciem wpisz kwotę brutto.',
                'payment_method.not_in' => 'Przed zamknięciem wybierz sposób płatności.',
                'payment_source.not_in' => 'Przed zamknięciem wybierz źródło środków.',
                'paid_by.required' => 'Przed zamknięciem wpisz, kto zapłacił.',
            ]);
            if ($data['is_unassigned'] ?? false) {
                throw ValidationException::withMessages(['owner_id' => 'Przed zamknięciem wybierz lub wpisz kupującego.']);
            }
            $data['settled_at'] = now();
            $data['settled_by'] = $request->user()->id;
        }
        unset($data['revision'], $data['owner_mode']);

        return $data;
    }

    public function store(Request $request)
    {
        $this->access($request->user());
        $invoice = ExpenseInvoice::create($this->data($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('expenses.edit', $invoice)->with('success', 'Zakup został zapisany.');
    }

    public function update(Request $request, ExpenseInvoice $invoice)
    {
        $this->access($request->user());
        DB::transaction(function () use ($request, $invoice): void {
            $current = ExpenseInvoice::lockForUpdate()->findOrFail($invoice->id);
            abort_if($current->settled_at, 403, 'Rozliczona faktura jest zamknięta do edycji.');
            abort_unless($this->accounting($request->user()) || ($current->owner_id === $request->user()->id && ! $current->settled_at && ! $current->is_unassigned), 403);
            $data = $this->data($request, $current);
            abort_unless($current->revision === (int) $request->input('revision'), 409, 'Faktura została zmieniona. Odśwież stronę.');
            abort_if($current->settled_at && (($data['owner_id'] ?? $current->owner_id) !== $current->owner_id || ($data['is_unassigned'] ?? false)), 422, 'Rozliczonej faktury nie można ponownie publikować ani przypisywać.');
            $current->fill($data)->forceFill(['revision' => $current->revision + 1])->save();
        });

        return redirect()->route('expenses.edit', $invoice)->with('success', 'Dane zostały zapisane.');
    }

    public function claim(Request $request, ExpenseInvoice $invoice)
    {
        $this->access($request->user());
        $request->validate(['confirm' => ['required', 'accepted']]);
        DB::transaction(function () use ($request, $invoice): void {
            $current = ExpenseInvoice::lockForUpdate()->findOrFail($invoice->id);
            abort_unless($current->is_unassigned && $current->owner_id === null && ! $current->settled_at, 409, 'Ta faktura nie jest już dostępna do przypisania.');
            $current->forceFill(['owner_id' => $request->user()->id, 'owner_name' => $request->user()->name, 'is_unassigned' => false, 'revision' => $current->revision + 1])->save();
        });

        return redirect()->route('expenses.edit', $invoice)->with('success', 'Faktura została przypisana do Ciebie i zniknęła ze wspólnej listy.');
    }

    public function settle(Request $request, ExpenseInvoice $invoice)
    {
        $this->access($request->user());
        abort_unless($this->accounting($request->user()), 403);
        $request->validate(['confirm' => ['required', 'accepted'], 'revision' => ['required', 'integer']]);
        DB::transaction(function () use ($invoice, $request): void {
            $current = ExpenseInvoice::lockForUpdate()->findOrFail($invoice->id);
            abort_unless($current->owner_id && ! $current->is_unassigned && ! $current->settled_at && $current->revision === (int) $request->input('revision'), 409, 'Faktura jest nieprzypisana, rozliczona lub została zmieniona.');
            abort_unless($current->gross_amount !== null && $current->payment_status !== 'unknown' && $current->payment_method !== 'unknown' && $current->payment_source !== 'unknown' && filled($current->paid_by), 422, 'Uzupełnij kwotę brutto, status i sposób płatności, źródło środków oraz osobę płacącą.');
            $current->forceFill(['settled_at' => now(), 'settled_by' => $request->user()->id, 'revision' => $current->revision + 1])->save();
        });

        return back()->with('success', 'Faktura została oznaczona jako rozliczona.');
    }

    public function scan(Request $request, ExpenseScan $scanner)
    {
        $this->access($request->user());
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $file = $request->file('photo');
        try {
            return response()->json(['proposal' => $scanner->read($file, $request->user()), 'notice' => 'Sprawdź propozycję przed zapisaniem. Zdjęcie nie jest przechowywane.']);
        } finally {
            if (is_file($file->getRealPath())) {
                unlink($file->getRealPath());
            }
        }
    }
}
