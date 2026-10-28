<?php

namespace App\Http\Controllers;

use App\Exports\FinancialPlanExport;
use App\Models\FinancialDepartment;
use App\Models\FinancialDepartmentPayment;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanLine;
use App\Models\Offer;
use App\Models\User;
use App\Services\FinancialPlanAccess;
use App\Services\FinancialPlanCalculator;
use App\Services\FinancialPlanChartData;
use App\Services\FinancialPlanExcelFormulas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class FinancialPlanController extends Controller
{
    public function __construct(private FinancialPlanAccess $access, private FinancialPlanCalculator $calculator) {}

    public function index(Request $request)
    {
        abort_unless($this->access->allowed($request->user()), 403);

        return response()->view('financial-plans.index', [
            'departments' => $this->access->departments($request->user())->with(['members:id,name', 'planners:id,name', 'plans' => fn ($plans) => $plans->orderByDesc('year')])->orderBy('name')->get(),
            'staff' => $this->staff()->get(['id', 'name']),
            'canManage' => $this->access->allowed($request->user(), true),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function storeDepartment(Request $request)
    {
        abort_unless($this->access->allowed($request->user(), true), 403);
        $data = $this->departmentData($request);
        DB::transaction(function () use ($data, $request) {
            $department = FinancialDepartment::create(['name' => $data['name'], 'owner_id' => $request->user()->id]);
            $department->members()->sync($data['member_ids'] ?? []);
            $department->planners()->sync($data['planner_ids'] ?? []);
        });

        return back()->with('success', 'Dział został utworzony. Wybierz zakres miesięcy planu.');
    }

    public function updateDepartment(Request $request, FinancialDepartment $department)
    {
        $this->access->authorizeDepartment($request->user(), $department, true);
        // Only the owner or superadmin may delegate access to confidential salaries.
        abort_unless($request->user()->hasRole('superadmin') || $department->owner_id === $request->user()->id, 403);
        $data = $this->departmentData($request);
        DB::transaction(function () use ($department, $data) {
            $department->update(['name' => $data['name']]);
            $department->members()->sync($data['member_ids'] ?? []);
            $department->planners()->sync($data['planner_ids'] ?? []);
        });

        return back()->with('success', 'Zapisano skład działu i osoby uprawnione do planowania. Historyczne koszty pozostają w planach.');
    }

    public function storePlan(Request $request, FinancialDepartment $department)
    {
        $this->access->authorizeDepartment($request->user(), $department, true);
        $data = $this->periodData($request);
        $data += $request->validate(['name' => ['nullable', 'string', 'max:150']]);
        $plan = DB::transaction(function () use ($department, $data) {
            FinancialDepartment::whereKey($department->id)->lockForUpdate()->firstOrFail();

            return $department->plans()->whereDate('start_date', $data['start_date'])->whereDate('end_date', $data['end_date'])->first()
                ?? $department->plans()->create($data + ['year' => (int) substr($data['start_date'], 0, 4)]);
        });

        return redirect()->route('financial-plans.show', $plan);
    }

    public function updatePeriod(Request $request, FinancialPlan $plan)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        $data = $this->periodData($request);
        $data += $request->validate(['name' => ['sometimes', 'nullable', 'string', 'max:150']]);
        $revision = $request->validate(['revision' => ['required', 'integer', 'min:0']])['revision'];
        DB::transaction(function () use ($request, $plan, $data, $revision) {
            $locked = $this->lockPlan($plan, $revision);
            if ($locked->department->plans()->where('id', '!=', $locked->id)->whereDate('start_date', $data['start_date'])->whereDate('end_date', $data['end_date'])->exists()) {
                throw ValidationException::withMessages(['start_month' => 'Plan na ten okres już istnieje. Otwórz go na liście działów.']);
            }
            $before = ['start_date' => $locked->periodStart()->toDateString(), 'end_date' => $locked->periodEnd()->toDateString(), 'name' => $locked->name];
            if ($before['start_date'] !== $data['start_date']) {
                $locked->opening_balance = null;
            }
            $locked->update($data + ['year' => (int) substr($data['start_date'], 0, 4)]);
            foreach ($locked->lines as $line) {
                $this->authorizeSource($request, $line->toArray());
                $this->validateDuplicates($locked, $line->toArray(), $line);
            }
            $this->record($locked, $request, 'period_updated', ['before' => $before, 'after' => $data]);
        });

        return redirect()->route('financial-plans.show', $plan)->with('success', 'Zmieniono zakres miesięcy planu. Daty źródłowe i okresy kosztów osób pozostają zachowane.');
    }

    private function periodData(Request $request): array
    {
        $data = $request->validate([
            'start_month' => ['required_without:year', 'date_format:Y-m', 'after_or_equal:2020-01', 'before_or_equal:2100-12'],
            'end_month' => ['required_with:start_month', 'date_format:Y-m', 'after_or_equal:start_month', 'before_or_equal:2100-12'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);
        $start = isset($data['start_month']) ? CarbonImmutable::createFromFormat('!Y-m', $data['start_month']) : CarbonImmutable::create($data['year'], 1, 1);
        $end = isset($data['end_month']) ? CarbonImmutable::createFromFormat('!Y-m', $data['end_month'])->endOfMonth() : $start->endOfYear();

        return ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()];
    }

    public function destroyPlan(Request $request, FinancialPlan $plan)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $plan, $data) {
            $locked = $this->lockPlan($plan, (int) $data['revision']);
            foreach ($locked->lines as $line) {
                $this->authorizeSource($request, $line->toArray());
            }
            $locked->delete();
        });

        return redirect()->route('financial-plans.index')->with('success', 'Usunięto plan. Projekty, oferty i płatności działu pozostają zachowane.');
    }

    public function updateOpeningBalance(Request $request, FinancialPlan $plan)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0'], 'opening_balance' => ['required', 'numeric', 'min:-999999999.99', 'max:999999999.99', 'decimal:0,2']]);
        DB::transaction(function () use ($request, $plan, $data) {
            $locked = $this->lockPlan($plan, (int) $data['revision']);
            $before = $locked->opening_balance;
            $locked->update(['opening_balance' => number_format((float) $data['opening_balance'], 2, '.', '')]);
            $this->record($locked, $request, 'opening_balance_updated', ['before' => ['opening_balance' => $before], 'after' => ['opening_balance' => $locked->opening_balance]]);
        });

        return redirect()->route('financial-plans.show', $plan)->with('success', 'Zapisano saldo początkowe konta działu.');
    }

    public function storePayment(Request $request, FinancialPlan $plan)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'], 'direction' => ['required', Rule::in(['in', 'out'])],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$plan->periodStart()->toDateString(), 'before_or_equal:'.$plan->periodEnd()->toDateString()],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99', 'decimal:0,2'],
            'description' => ['required', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($request, $plan, $data) {
            $locked = $this->lockPlan($plan, (int) $data['revision']);
            unset($data['revision']);
            $payment = FinancialDepartmentPayment::create($data + ['financial_department_id' => $locked->financial_department_id, 'created_by' => $request->user()->id]);
            $this->record($locked, $request, 'payment_created', ['after' => $payment->toArray()]);
        });

        return redirect()->route('financial-plans.show', $plan)->with('success', 'Zapisano rzeczywistą płatność działu.');
    }

    public function destroyPayment(Request $request, FinancialPlan $plan, FinancialDepartmentPayment $payment)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        abort_unless($payment->financial_department_id === $plan->financial_department_id, 404);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $plan, $payment, $data) {
            $locked = $this->lockPlan($plan, (int) $data['revision']);
            $this->record($locked, $request, 'payment_deleted', ['before' => $payment->toArray()]);
            $payment->delete();
        });

        return redirect()->route('financial-plans.show', $plan)->with('success', 'Usunięto płatność działu.');
    }

    public function show(Request $request, FinancialPlan $plan)
    {
        $viewData = $this->calculationViewData($request, $plan);
        $scenario = $viewData['scenario'];
        $calculation = $viewData['calculation'];
        $lines = $viewData['lines'];
        $editing = $request->filled('edit') ? $lines->firstWhere('id', (int) $request->input('edit')) : null;
        $canManage = $this->access->allowed($request->user(), true);
        $history = $plan->changes()->with('author:id,name')->latest('id')->paginate(15, ['*'], 'history_page')->withQueryString();
        $visibleProjects = $this->access->projects($request->user())->pluck('id');
        $visibleOffers = $this->access->offers($request->user())->pluck('id');
        $canViewBaseline = $this->access->canViewBaseline($request->user(), $plan);
        $history->setCollection($history->getCollection()->filter(function ($change) use ($visibleProjects, $visibleOffers, $canViewBaseline) {
            if ($change->action === 'approved' && ! $canViewBaseline) {
                return false;
            }
            foreach (['before', 'after'] as $key) {
                $state = $change->payload[$key] ?? [];
                if ((! empty($state['project_id']) && ! $visibleProjects->contains($state['project_id']))
                    || (! empty($state['offer_id']) && ! $visibleOffers->contains($state['offer_id']))) {
                    return false;
                }
            }

            return true;
        }));

        return response()->view('financial-plans.show', [
            'plan' => $plan, 'scenario' => $scenario, 'calculation' => $calculation, 'lines' => $lines, 'editing' => $editing,
            'canManage' => $canManage,
            'canViewBaseline' => $canViewBaseline,
            'projects' => $canManage ? $this->access->projects($request->user())->orderBy('number')->get(['id', 'number', 'name']) : collect(),
            'offers' => $canManage ? $this->access->offers($request->user())->orderByDesc('id')->get(['id', 'offer_number', 'offer_title', 'crm_opportunity_id', 'kwota_netto']) : collect(),
            'members' => $plan->department->members()->orderBy('name')->get(['users.id', 'users.name'])
                ->when($editing?->person, fn ($members) => $members->push($editing->person)->unique('id')),
            'changes' => $history,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function storeLine(Request $request, FinancialPlan $plan)
    {
        return $this->saveLine($request, $plan);
    }

    public function updateLine(Request $request, FinancialPlan $plan, FinancialPlanLine $line)
    {
        abort_unless($line->financial_plan_id === $plan->id, 404);

        return $this->saveLine($request, $plan, $line);
    }

    private function saveLine(Request $request, FinancialPlan $plan, ?FinancialPlanLine $line = null)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        if ($line) {
            $this->authorizeSource($request, $line->toArray());
        }
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'kind' => ['required', Rule::in(array_keys(FinancialPlanLine::KINDS))],
            'name' => ['exclude_if:kind,person,project,offer', 'required_if:kind,revenue,overhead', 'string', 'max:200'],
            'project_id' => ['nullable', 'required_if:kind,project', 'integer'],
            'offer_id' => ['nullable', 'required_if:kind,offer', 'integer'],
            'user_id' => ['nullable', 'required_if:kind,person', 'integer'],
            'qualification' => ['required_if:kind,offer', 'nullable', Rule::in(array_keys(FinancialPlanLine::QUALIFICATIONS))],
            'probability' => ['required_if:kind,offer', 'nullable', 'integer', 'between:0,100'],
            'budget_accuracy' => ['required_if:kind,project', 'nullable', Rule::in(array_keys(FinancialPlanLine::ACCURACY))],
            'start_date' => ['exclude_if:kind,project', 'required_unless:kind,project', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2100-12-31'],
            'end_date' => ['exclude_if:kind,project', 'required_unless:kind,project', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:2100-12-31'],
            ...collect(['revenue_override', 'cost_override', 'salary_cost', 'car_cost', 'risk_reserve', 'actual_salary_cost', 'actual_car_cost', 'actual_cost'])
                ->mapWithKeys(fn ($key) => [$key => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2']])->all(),
            'car_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'offer_schedule' => ['exclude_unless:kind,offer', 'nullable', 'array', 'max:100'],
            'offer_schedule.*' => ['array:direction,date,amount'],
            'offer_schedule.*.direction' => ['required', Rule::in(['in', 'out'])],
            'offer_schedule.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2100-12-31'],
            'offer_schedule.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2'],
        ]);
        if ($data['kind'] === 'project') {
            $data['start_date'] = $plan->periodStart()->toDateString();
            $data['end_date'] = $plan->periodEnd()->toDateString();
        }
        if (CarbonImmutable::parse($data['start_date'])->startOfMonth()->gt($plan->periodEnd()) || CarbonImmutable::parse($data['end_date'])->endOfMonth()->lt($plan->periodStart())) {
            throw ValidationException::withMessages(['start_date' => 'Okres musi obejmować co najmniej jeden miesiąc wybranego planu.']);
        }
        $data['start_date'] = CarbonImmutable::parse($data['start_date'])->startOfMonth()->toDateString();
        $data['end_date'] = CarbonImmutable::parse($data['end_date'])->endOfMonth()->toDateString();
        $data['qualification'] ??= 'real';
        $data['probability'] ??= 50;
        $data['budget_accuracy'] ??= 'initial';
        $data['project_id'] = $data['kind'] === 'project' ? ($data['project_id'] ?? null) : null;
        $data['offer_id'] = in_array($data['kind'], ['project', 'offer'], true) ? ($data['offer_id'] ?? null) : null;
        $data['user_id'] = $data['kind'] === 'person' ? ($data['user_id'] ?? null) : null;
        $this->authorizeSource($request, $data);
        if ($data['user_id'] && $data['user_id'] != $line?->user_id && ! $plan->department->members()->whereKey($data['user_id'])->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Wybierz osobę ze składu tego działu.']);
        }
        $data['name'] = Str::limit(match ($data['kind']) {
            'person' => User::withTrashed()->findOrFail($data['user_id'])->name,
            'project' => $this->access->projects($request->user())->findOrFail($data['project_id'])->name,
            'offer' => ($offer = $this->access->offers($request->user())->findOrFail($data['offer_id']))->offer_title ?: $offer->fullNumber(),
            default => $data['name'],
        }, 200, '');
        $data['notes'] ??= null;
        $data['offer_schedule'] = $data['kind'] === 'offer' ? array_values($data['offer_schedule'] ?? []) : null;
        if ($data['offer_schedule']) {
            $scheduled = collect($data['offer_schedule']);
            $revenue = $data['revenue_override'] ?? $this->access->offers($request->user())->findOrFail($data['offer_id'])->kwota_netto;
            $incoming = $scheduled->where('direction', 'in')->sum(fn ($row) => (int) round((float) $row['amount'] * 100));
            $outgoing = $scheduled->where('direction', 'out')->sum(fn ($row) => (int) round((float) $row['amount'] * 100));
            if ($incoming !== (int) round((float) $revenue * 100)) {
                throw ValidationException::withMessages(['offer_schedule' => 'Suma wpływów musi odpowiadać wartości netto oferty lub ręcznie podanemu przychodowi.']);
            }
            if (! $scheduled->contains('direction', 'out')) {
                throw ValidationException::withMessages(['offer_schedule' => 'Dodaj koszty z datami, także jawne 0, jeśli ich nie przewidujesz.']);
            }
            if (($data['cost_override'] ?? null) !== null && $outgoing !== (int) round((float) $data['cost_override'] * 100)) {
                throw ValidationException::withMessages(['offer_schedule' => 'Suma kosztów harmonogramu musi odpowiadać podanemu kosztowi.']);
            }
            $data['cost_override'] = number_format($outgoing / 100, 2, '.', '');
        }
        if ($data['kind'] === 'person' && ($data['salary_cost'] ?? null) === null) {
            throw ValidationException::withMessages(['salary_cost' => 'Wpisz miesięczny pełny koszt zatrudnienia.']);
        }
        if ($data['kind'] === 'overhead' && ($data['cost_override'] ?? null) === null) {
            throw ValidationException::withMessages(['cost_override' => 'Wpisz miesięczny koszt działu.']);
        }
        if ($data['kind'] === 'revenue' && ($data['revenue_override'] ?? null) === null) {
            throw ValidationException::withMessages(['revenue_override' => 'Wpisz planowany przychód.']);
        }
        $allowedAmounts = match ($data['kind']) {
            'project' => [],
            'person' => ['salary_cost', 'car_cost', 'actual_salary_cost', 'actual_car_cost'],
            'overhead' => ['cost_override', 'actual_cost'],
            default => ['revenue_override', 'cost_override', 'risk_reserve'],
        };
        foreach (['revenue_override', 'cost_override', 'salary_cost', 'car_cost', 'risk_reserve', 'actual_salary_cost', 'actual_car_cost', 'actual_cost'] as $field) {
            $data[$field] = in_array($field, $allowedAmounts, true) ? ($data[$field] ?? null) : null;
        }
        $data['car_name'] = $data['kind'] === 'person' ? ($data['car_name'] ?? null) : null;
        DB::transaction(function () use ($plan, $line, $data, $request) {
            $locked = $this->lockPlan($plan, (int) $data['revision']);
            $this->validateDuplicates($locked, $data, $line);
            $before = $line?->toArray();
            unset($data['revision']);
            $data['updated_by'] = $request->user()->id;
            $saved = $line ?? $locked->lines()->make();
            $saved->fill($data)->save();
            $this->record($locked, $request, $line ? 'updated' : 'created', ['before' => $before, 'after' => $saved->toArray()]);
        });

        return redirect()->route('financial-plans.show', $plan)->with('success', 'Zapisano pozycję prognozy. Wyliczenia korzystają z aktualnych danych.');
    }

    public function updateLineEnabled(Request $request, FinancialPlan $plan, FinancialPlanLine $line)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        abort_unless($line->financial_plan_id === $plan->id, 404);
        $this->authorizeSource($request, $line->toArray());
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0'], 'enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($plan, $line, $request, $data) {
            $locked = $this->lockPlan($plan, (int) $data['revision']);
            $before = $line->toArray();
            $line->update(['enabled' => $data['enabled'], 'updated_by' => $request->user()->id]);
            $this->record($locked, $request, 'updated', ['before' => $before, 'after' => $line->toArray()]);
        });

        return back()->with('success', $data['enabled'] ? 'Włączono pozycję do wyliczeń.' : 'Wyłączono pozycję z wyliczeń.');
    }

    public function destroyLine(Request $request, FinancialPlan $plan, FinancialPlanLine $line)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        abort_unless($line->financial_plan_id === $plan->id, 404);
        $this->authorizeSource($request, $line->toArray());
        $data = $request->validate(['revision' => ['required', 'integer']]);
        DB::transaction(function () use ($plan, $line, $request, $data) {
            $locked = $this->lockPlan($plan, (int) $data['revision']);
            $this->record($locked, $request, 'deleted', ['before' => $line->toArray()]);
            $line->delete();
        });

        return back()->with('success', 'Usunięto pozycję z prognozy.');
    }

    public function calculation(Request $request, FinancialPlan $plan)
    {
        $data = $this->calculationViewData($request, $plan);
        $html = view('financial-plans.calculation', $data)->render();
        $fingerprint = hash('sha256', $html);

        return response()->json([
            'html' => hash_equals($fingerprint, $request->header('X-Plan-Fingerprint', '')) ? null : $html,
            'fingerprint' => $fingerprint,
            'revision' => $plan->revision,
            'period' => $plan->periodLabel(),
            'complete' => $data['calculation']['complete'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, FinancialPlan $plan)
    {
        $section = $request->validate(['section' => ['required', Rule::in(['cost-monthly', 'cost-cumulative', 'cost-values', 'result', 'sources', 'forecast', 'cash', 'people'])]])['section'];
        $data = $this->calculationViewData($request, $plan);
        $calculation = $data['calculation'];
        $label = ['cost-monthly' => 'Koszty miesięczne', 'cost-cumulative' => 'Koszty narastająco', 'cost-values' => 'Wartości kosztów', 'result' => 'Zysk i strata', 'sources' => 'Źródła i założenia', 'forecast' => 'Prognoza miesięczna', 'cash' => 'Przepływy pieniężne', 'people' => 'Koszty wydziałowe'][$section];
        $rows = [[$label], ['Plan', $plan->name ?: $plan->department->name], ['Okres', $plan->periodLabel()], ['Scenariusz', FinancialPlanCalculator::SCENARIOS[$data['scenario']]], ['Zakres', $calculation['complete'] ? 'Dane dostępne w Twoich uprawnieniach' : 'Sumy częściowe — część źródeł jest niedostępna'], [null]];
        $chartColumns = $moneyColumns = [];
        if (in_array($section, ['forecast', 'cash'], true)) {
            $labels = $section === 'forecast' ? FinancialPlanCalculator::ROWS : ['in' => 'Wpływy · projekty i oferty', 'out' => 'Wydatki · projekty i oferty', 'balance' => 'Saldo przepływów'];
            $values = $calculation[$section];
            $rows[] = array_merge(['Pozycja'], array_map(fn ($month) => $month->format('m.Y'), array_values($calculation['months'])), ['Razem w okresie']);
            foreach ($labels as $key => $name) {
                $total = $key === 'gap' ? max(0, -array_sum($values['result'])) : array_sum($values[$key]);
                $rows[] = array_merge([$name], array_map(fn ($amount) => $amount / 100, array_values($values[$key])), [$total / 100]);
            }
            $moneyColumns = range(2, count($rows[6]));
        } elseif ($section === 'people') {
            $rows[] = ['Osoba', 'Okres', 'Wynagrodzenie / mies.', 'Samochód', 'Samochód / mies.', 'Razem w okresie', 'Liczba miesięcy w planie'];
            foreach ($data['lines']->where('kind', 'person')->where('enabled', true) as $line) {
                $months = count(array_filter($calculation['months'], fn ($month) => $month->betweenIncluded($line->start_date->copy()->startOfMonth(), $line->end_date->copy()->endOfMonth())));
                $rows[] = [$line->person?->name ?? 'Osoba archiwalna', $line->start_date->format('m.Y').' – '.$line->end_date->format('m.Y'), (float) $line->salary_cost, $line->car_name, (float) $line->car_cost, $calculation['details'][$line->id]['lineCost'] / 100, $months];
            }
            $moneyColumns = [3, 5, 6];
        } elseif ($section === 'sources') {
            $rows[] = ['Pozycja', 'Rodzaj', 'Okres', 'Uwzględniona', 'Ocena', 'Szansa (%)', 'Przychód źródłowy (PLN)', 'Koszt źródłowy (PLN)', 'Przychód planu (PLN)', 'Koszt planu (PLN)', 'Opis i uwagi'];
            foreach ($calculation['months'] as $month) {
                $rows[6][] = 'Przychód '.$month->format('m.Y');
                $rows[6][] = 'Koszt '.$month->format('m.Y');
            }
            foreach ($data['lines'] as $line) {
                $detail = $calculation['details'][$line->id];
                $rating = $line->kind === 'offer' ? FinancialPlanLine::QUALIFICATIONS[$line->qualification] : ($line->kind === 'project' ? FinancialPlanLine::ACCURACY[$line->budget_accuracy] : '');
                $rows[] = [$line->name, FinancialPlanLine::KINDS[$line->kind], $line->kind === 'project' ? 'Według dat faktur i dostaw' : $line->start_date->format('m.Y').' – '.$line->end_date->format('m.Y'), $line->enabled ? 'Tak' : 'Nie', $rating, $line->kind === 'offer' ? $line->probability : null, $detail['sourceRevenue'] === null ? null : (float) $detail['sourceRevenue'], $detail['sourceCost'] === null ? null : (float) $detail['sourceCost'], $detail['lineRevenue'] / 100, $detail['lineCost'] / 100, implode("\n", array_filter(array_merge([$line->notes], $detail['lineWarnings'])))];
                foreach (array_keys($calculation['months']) as $month) {
                    $income = $cost = 0;
                    foreach (['contracted', 'pipeline', 'manual_revenue', 'direct_cost', 'salary', 'car', 'overhead'] as $key) {
                        $amount = collect($calculation['breakdown'][$key][$month] ?? [])->where('line_id', $line->id)->sum('amount');
                        if (in_array($key, ['contracted', 'pipeline', 'manual_revenue'], true)) {
                            $income += $amount;
                        } else {
                            $cost += $amount;
                        }
                    }
                    $rows[array_key_last($rows)][] = $income / 100;
                    $rows[array_key_last($rows)][] = $cost / 100;
                }
            }
            $moneyColumns = [7, 8, 9, 10];
            $moneyColumns = array_merge($moneyColumns, range(12, count($rows[6])));
        } elseif ($section === 'result') {
            $rows[] = ['Miesiąc', 'Wynik miesiąca bez ofert', 'Zyski narastająco', 'Straty narastająco', 'Wynik bez ofert', 'Prognoza z ofertami', 'Wartość ofert (100%)', 'Przychód bez ofert w miesiącu', 'Koszty realizacji bez ofert w miesiącu', 'Koszty wydziałowe w miesiącu', 'Przychód ofert ważony w miesiącu', 'Koszt ofert ważony w miesiącu', 'Wartość ofert 100% w miesiącu'];
            foreach (FinancialPlanChartData::result($calculation) as $index => $month) {
                $rows[] = [$month['label'], $month['monthlyResult'] / 100, $month['profit'] / 100, $month['loss'] / 100, $month['balance'] / 100, $month['predicted'] / 100, $month['offers'] / 100];
                $key = $index + 1;
                $forecast = $calculation['forecast'];
                $rows[array_key_last($rows)] = array_merge($rows[array_key_last($rows)], [($forecast['contracted'][$key] + $forecast['manual_revenue'][$key]) / 100, ($forecast['direct_cost'][$key] - $calculation['offerForecast']['cost'][$key]) / 100, ($forecast['salary'][$key] + $forecast['car'][$key] + $forecast['overhead'][$key]) / 100, $calculation['offerForecast']['revenue'][$key] / 100, $calculation['offerForecast']['cost'][$key] / 100, $calculation['offerForecast']['value'][$key] / 100]);
            }
            $chartColumns = [3, 4, 5, 6, 7];
            $moneyColumns = range(2, 13);
        } else {
            $cumulative = $section === 'cost-cumulative';
            $rows[] = ['Miesiąc', 'Wynagrodzenia', 'Samochody', 'Pozostałe koszty działu', $cumulative ? 'Razem narastająco' : 'Razem w miesiącu'];
            if ($section === 'cost-values') {
                $rows[6][] = 'Narastająco';
            } elseif ($cumulative) {
                $rows[6] = array_merge($rows[6], ['Wynagrodzenia w miesiącu', 'Samochody w miesiącu', 'Pozostałe koszty w miesiącu']);
            }
            $running = ['salary' => 0, 'car' => 0, 'overhead' => 0];
            foreach (FinancialPlanChartData::costs($calculation) as $month) {
                foreach (array_keys($running) as $key) {
                    $running[$key] += $month[$key];
                }
                $values = $cumulative ? $running : $month;
                $rows[] = [$month['label'], $values['salary'] / 100, $values['car'] / 100, $values['overhead'] / 100, $month[$cumulative ? 'cumulative' : 'monthly'] / 100];
                if ($section === 'cost-values') {
                    $rows[array_key_last($rows)][] = $month['cumulative'] / 100;
                } elseif ($cumulative) {
                    $rows[array_key_last($rows)] = array_merge($rows[array_key_last($rows)], [$month['salary'] / 100, $month['car'] / 100, $month['overhead'] / 100]);
                }
            }
            $chartColumns = $cumulative ? [5] : [2, 3, 4];
            $moneyColumns = [2, 3, 4, 5];
            if ($cumulative) {
                $moneyColumns = range(2, 8);
            }
            if ($section === 'cost-values') {
                $chartColumns = [];
                $moneyColumns[] = 6;
            }
        }

        $response = Excel::download(new FinancialPlanExport($rows, $label, $chartColumns, $moneyColumns, FinancialPlanExcelFormulas::build($section, $rows)), 'plan-'.$plan->id.'-'.$section.'.xlsx');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function calculationViewData(Request $request, FinancialPlan $plan): array
    {
        $this->access->authorizeDepartment($request->user(), $plan->department);
        $data = $request->validate(['scenario' => ['nullable', Rule::in(array_keys(FinancialPlanCalculator::SCENARIOS))]]);
        $scenario = $data['scenario'] ?? 'base';
        $calculation = $this->calculator->calculate($plan, $request->user(), $scenario);
        $lines = $plan->lines->filter(fn ($line) => isset($calculation['details'][$line->id]));
        $canManage = $this->access->allowed($request->user(), true);

        return compact('plan', 'scenario', 'calculation', 'lines', 'canManage');
    }

    private function lockPlan(FinancialPlan $plan, int $revision): FinancialPlan
    {
        // Keep the same lock order across departments, including periods spanning years.
        $locked = FinancialPlan::orderBy('id')->lockForUpdate()->get()->keyBy('id')->get($plan->id);
        abort_unless($locked, 404);
        abort_unless($locked->revision === $revision, 409, 'Plan zmienił się w międzyczasie. Odśwież stronę przed zapisem.');

        return $locked;
    }

    private function record(FinancialPlan $plan, Request $request, string $action, array $payload): void
    {
        $plan->changes()->create(['user_id' => $request->user()->id, 'action' => $action, 'payload' => $payload]);
        $plan->increment('revision');
    }

    private function authorizeSource(Request $request, array $data): void
    {
        if (! empty($data['project_id'])) {
            abort_unless($this->access->projects($request->user())->whereKey($data['project_id'])->exists(), 403);
        }
        if (! empty($data['offer_id'])) {
            abort_unless($this->access->offers($request->user())->whereKey($data['offer_id'])->exists(), 403);
        }
    }

    private function validateDuplicates(FinancialPlan $plan, array $data, ?FinancialPlanLine $line): void
    {
        $query = FinancialPlanLine::whereIn('financial_plan_id', FinancialPlan::overlapping($plan)
            ->where(fn ($q) => $q->whereKey($plan->id)->orWhere('financial_department_id', '!=', $plan->financial_department_id))->select('id'))
            ->when($line, fn ($q) => $q->where('id', '!=', $line->id));
        if ($data['kind'] === 'person') {
            $duplicate = (clone $query)->where('kind', 'person')->where('user_id', $data['user_id'])
                ->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['user_id' => 'Koszt tej osoby jest już ujęty w tym okresie. Zmień okres lub istniejącą pozycję.']);
            }
        }
        foreach (['project_id', 'offer_id'] as $field) {
            if (! empty($data[$field]) && (clone $query)->where($field, $data[$field])->where('kind', $data['kind'])->exists()) {
                throw ValidationException::withMessages([$field => 'To źródło jest już przypisane do tego planu lub innego działu w nakładającym się okresie.']);
            }
        }
        if ($data['kind'] === 'offer') {
            $opportunityId = Offer::find($data['offer_id'])?->crm_opportunity_id;
            if ($opportunityId && (clone $query)->where('kind', 'offer')->whereHas('offer', fn ($q) => $q->where('crm_opportunity_id', $opportunityId))->exists()) {
                throw ValidationException::withMessages(['offer_id' => 'Inna oferta tej samej szansy CRM jest już w planie. Edytuj istniejącą pozycję.']);
            }
        }
    }

    private function staff()
    {
        return User::where('is_active', true)->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['client_admin', 'client_user']))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))->orderBy('name');
    }

    private function departmentData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'member_ids' => ['nullable', 'array'], 'member_ids.*' => ['integer', 'distinct'],
            'planner_ids' => ['nullable', 'array'], 'planner_ids.*' => ['integer', 'distinct'],
        ]);
        $ids = array_unique([...($data['member_ids'] ?? []), ...($data['planner_ids'] ?? [])]);
        if ($this->staff()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['member_ids' => 'Wybierz aktywnych pracowników.']);
        }

        return $data;
    }
}
