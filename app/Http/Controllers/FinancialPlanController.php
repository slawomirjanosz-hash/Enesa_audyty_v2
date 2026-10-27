<?php

namespace App\Http\Controllers;

use App\Models\FinancialDepartment;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanLine;
use App\Models\Offer;
use App\Models\User;
use App\Services\FinancialPlanAccess;
use App\Services\FinancialPlanCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $revision = $request->validate(['revision' => ['required', 'integer', 'min:0']])['revision'];
        DB::transaction(function () use ($request, $plan, $data, $revision) {
            $locked = $this->lockPlan($plan, $revision);
            abort_if($locked->approved_at !== null, 409, 'Okres zatwierdzonego planu jest stały. Utwórz plan na nowy okres.');
            if ($locked->department->plans()->where('id', '!=', $locked->id)->whereDate('start_date', $data['start_date'])->whereDate('end_date', $data['end_date'])->exists()) {
                throw ValidationException::withMessages(['start_month' => 'Plan na ten okres już istnieje. Otwórz go na liście działów.']);
            }
            $before = ['start_date' => $locked->periodStart()->toDateString(), 'end_date' => $locked->periodEnd()->toDateString()];
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

    public function show(Request $request, FinancialPlan $plan)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department);
        $data = $request->validate(['scenario' => ['nullable', Rule::in(array_keys(FinancialPlanCalculator::SCENARIOS))]]);
        $scenario = $data['scenario'] ?? 'base';
        $calculation = $this->calculator->calculate($plan, $request->user(), $scenario);
        $lines = $plan->lines->filter(fn ($line) => isset($calculation['details'][$line->id]));
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
            'offers' => $canManage ? $this->access->offers($request->user())->orderByDesc('id')->get(['id', 'offer_number', 'offer_title', 'crm_opportunity_id']) : collect(),
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

        return redirect()->route('financial-plans.show', $plan)->with('success', 'Zapisano pozycję prognozy. Zatwierdzony plan pozostaje bez zmian.');
    }

    public function destroyLine(Request $request, FinancialPlan $plan, FinancialPlanLine $line)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        abort_unless($line->financial_plan_id === $plan->id, 404);
        $this->authorizeSource($request, $line->toArray());
        $data = $request->validate(['revision' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($plan, $line, $request, $data) {
            $locked = $this->lockPlan($plan, (int) $data['revision']);
            $this->record($locked, $request, 'deleted', ['before' => $line->toArray(), 'reason' => $data['reason']]);
            $line->delete();
        });

        return back()->with('success', 'Usunięto pozycję z prognozy.');
    }

    public function approve(Request $request, FinancialPlan $plan)
    {
        $this->access->authorizeDepartment($request->user(), $plan->department, true);
        $data = $request->validate(['revision' => ['required', 'integer']]);
        DB::transaction(function () use ($request, $plan, $data) {
            $locked = $this->lockPlan($plan, (int) $data['revision']);
            abort_if($locked->approved_at !== null, 409, 'Plan został już zatwierdzony. Aktualizuj prognozę.');
            $result = $this->calculator->calculate($locked, $request->user());
            if (! $result['complete'] || $locked->lines->isEmpty()) {
                throw ValidationException::withMessages(['plan' => 'Zatwierdzenie wymaga pozycji planu i dostępu do wszystkich źródeł.']);
            }
            foreach ($result['warnings'] as $warning) {
                if (str_contains($warning, 'nieoszacowany') || str_contains($warning, 'Brak kwoty przychodu')) {
                    throw ValidationException::withMessages(['plan' => 'Uzupełnij brakujące wyceny przed zatwierdzeniem.']);
                }
            }
            $locked->update(['approved_snapshot' => [
                'rows' => $result['forecast'],
                'project_ids' => $locked->lines->pluck('project_id')->filter()->unique()->values()->all(),
                'offer_ids' => $locked->lines->pluck('offer_id')->filter()->unique()->values()->all(),
            ], 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $this->record($locked, $request, 'approved', ['scenario' => 'base', 'snapshot' => $result['forecast']]);
        });

        return back()->with('success', 'Zatwierdzono plan wybranego okresu według scenariusza bazowego. Kolejne zmiany dotyczą prognozy.');
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
