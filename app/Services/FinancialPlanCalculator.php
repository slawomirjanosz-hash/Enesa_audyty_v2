<?php

namespace App\Services;

use App\Models\FinancialDepartmentPayment;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanLine;
use App\Models\User;
use Carbon\CarbonImmutable;

class FinancialPlanCalculator
{
    public const SCENARIOS = ['cautious' => 'Ostrożny', 'base' => 'Bazowy', 'optimistic' => 'Optymistyczny'];

    public const ROWS = [
        'contracted' => 'Przychody z projektów', 'pipeline' => 'Przychody z ofert',
        'manual_revenue' => 'Pozostała planowana sprzedaż', 'direct_cost' => 'Bezpośrednie koszty projektów i ofert',
        'margin' => 'Marża na pokrycie działu', 'salary' => 'Koszty wynagrodzeń',
        'car' => 'Koszty samochodów', 'overhead' => 'Pozostałe koszty działu',
        'result' => 'Wynik działu', 'gap' => 'Brakuje do pokrycia kosztów',
    ];

    public const EXPLANATIONS = [
        'contracted' => 'Suma faktur klienta z dodanych projektów: planowanych, wystawionych i opłaconych, według daty dokumentu. Wartość umowy bez zaplanowanych faktur nie jest doliczana.',
        'pipeline' => 'Przychody ofert według harmonogramu płatności i współczynnika scenariusza. Bez harmonogramu: równy rozkład na okres realizacji, bez uwzględniania w przepływach. Bazowy: realne oferty według szansy, potwierdzone 100%. Przegrane i zastąpione projektem są pomijane.',
        'manual_revenue' => 'Ręcznie wpisana planowana sprzedaż, rozłożona równo na okres pozycji. Wspólna dla wszystkich scenariuszy.',
        'direct_cost' => 'Koszty zapisane w finansach projektów oraz planowane i zrealizowane dostawy, według ich dat. Dostawa ujęta w finansach jest liczona raz. Dodatkowo szacowane koszty ofert i ręcznej sprzedaży z rezerwą, ważone scenariuszem.',
        'margin' => 'Marża = przychody z projektów + przychody z ofert + pozostała sprzedaż − bezpośrednie koszty realizacji. Ta kwota służy pokryciu utrzymania działu.',
        'salary' => 'Miesięczny pełny koszt zatrudnienia wskazanych osób, wraz z narzutami pracodawcy. Naliczany tylko w miesiącach przypisania osoby.',
        'car' => 'Miesięczne koszty samochodów przypisanych do osób, tylko w miesiącach obowiązywania pozycji.',
        'overhead' => 'Pozostałe miesięczne koszty utrzymania działu wpisane ręcznie, np. oprogramowanie i biuro. Bez kosztów realizacji projektów.',
        'result' => 'Wynik działu = marża na pokrycie działu − wynagrodzenia − samochody − pozostałe koszty działu. Kwota ujemna oznacza niedobór pokrycia kosztów.',
        'gap' => 'W miesiącu: brakująca kwota do wyjścia na zero, gdy wynik jest ujemny. W sumie okresu: niedobór po zbilansowaniu wyników wszystkich miesięcy, a nie suma miesięcznych niedoborów.',
        'revenue' => 'Wykonanie: wyłącznie wystawione i opłacone faktury klienta według daty dokumentu. Planowane faktury są tylko w prognozie.',
        'in' => 'Wpływy z faktur klienta według terminu płatności (bez terminu: data dokumentu + 30 dni) oraz raty ofert według harmonogramu, ważone scenariuszem. Obejmuje planowane faktury; kwoty netto.',
        'out' => 'Koszty finansowe projektów według terminu płatności (bez terminu: data dokumentu + 30 dni) oraz koszty ofert z harmonogramu, ważone scenariuszem. Bez kosztów utrzymania działu i dostaw niezapisanych w finansach.',
        'balance' => 'Saldo harmonogramu projektów i ofert = wpływy − wydatki danego miesiąca. Bez VAT, salda rachunku i kosztów utrzymania działu.',
    ];

    public function calculate(FinancialPlan $plan, User $user, string $scenario = 'base'): array
    {
        $access = app(FinancialPlanAccess::class);
        $projectIds = $access->projects($user)->pluck('id')->all();
        $offerIds = $access->offers($user)->pluck('id')->all();
        $plan->load('lines.project.financialEntries.projectRequirement', 'lines.project.requirements', 'lines.offer.crmOpportunity', 'lines.person');
        $months = $plan->months();
        $forecast = $this->emptyRows(array_keys(self::ROWS), $months);
        $actual = $this->emptyRows(['revenue', 'direct_cost', 'salary', 'car', 'overhead', 'result'], $months);
        $cash = $this->emptyRows(['in', 'out', 'balance'], $months);
        $warnings = [];
        $details = [];
        $breakdown = [];
        $accountEntries = [];
        $projectCosts = 0;
        $unpaid = ['revenue' => 0, 'cost' => 0, 'invoiceCount' => 0, 'costCount' => 0];
        $offerForecast = $this->emptyRows(['revenue', 'cost', 'value'], $months);
        $complete = true;
        $linkedProjects = FinancialPlanLine::where('kind', 'project')->where('enabled', true)
            ->whereIn('financial_plan_id', FinancialPlan::overlapping($plan)->where(fn ($q) => $q->whereKey($plan->id)->orWhere('financial_department_id', '!=', $plan->financial_department_id))->select('id'))->with('offer:id,crm_opportunity_id')->get();
        $representedOffers = $linkedProjects->pluck('offer_id')->filter()->all();
        $representedOpportunities = $linkedProjects->map(fn ($line) => $line->offer?->crm_opportunity_id)->filter()->all();
        $seenOpportunities = [];
        foreach ($plan->department->members as $member) {
            $personLines = $plan->lines->where('kind', 'person')->where('user_id', $member->id);
            if (collect($months)->contains(fn ($month) => ! $personLines->contains(fn ($line) => $month->betweenIncluded($line->start_date->copy()->startOfMonth(), $line->end_date->copy()->endOfMonth())))) {
                $warnings[] = $member->name.': koszt osoby nieoszacowany w części okresu — dodaj wynagrodzenie i okres przypisania do działu.';
            }
        }

        foreach ($plan->lines->sortBy('id') as $line) {
            if (($line->project_id && ! in_array($line->project_id, $projectIds, true))
                || ($line->offer_id && ! in_array($line->offer_id, $offerIds, true))) {
                $complete = false;
                $warnings[] = 'Część źródeł jest niedostępna w Twoim zakresie uprawnień. Sumy są częściowe.';

                continue;
            }
            $line->name = match ($line->kind) {
                'project' => $line->project->name,
                'offer' => $line->offer?->offer_title ?: $line->name,
                'person' => $line->person?->name ?? $line->name,
                default => $line->name,
            };
            if (! $line->enabled) {
                $details[$line->id] = ['sourceRevenue' => null, 'sourceCost' => null, 'lineRevenue' => 0, 'lineCost' => 0, 'factor' => 0, 'lineWarnings' => ['Pozycja wyłączona z wyliczeń.'], 'sourceItems' => []];

                continue;
            }
            $lineWarnings = [];
            $beforeForecast = $forecast;
            $sourceItems = [];
            $revenueBefore = array_sum($forecast['contracted']) + array_sum($forecast['pipeline']) + array_sum($forecast['manual_revenue']);
            $costBefore = array_sum($forecast['direct_cost']) + array_sum($forecast['salary']) + array_sum($forecast['car']) + array_sum($forecast['overhead']);
            $revenue = $line->revenue_override;
            $remainingCost = $line->cost_override;
            $factor = 1.0;
            $sourceRevenue = null;
            $sourceCost = null;
            $lineRevenue = 0;
            $lineCost = 0;
            if ($line->kind === 'offer') {
                $offer = $line->offer;
                $opportunity = $offer?->crm_opportunity_id;
                $excluded = ! $offer || in_array($offer->status, ['przegrana', 'zarchiwizowana'], true)
                    || in_array($offer->crmOpportunity?->stage, ['lost', 'rejected'], true)
                    || in_array($offer->id, $representedOffers, true)
                    || ($opportunity && in_array($opportunity, $representedOpportunities, true));
                if ($opportunity && in_array($opportunity, $seenOpportunities, true)) {
                    $excluded = true;
                    $lineWarnings[] = 'Inna oferta tej samej szansy CRM jest już ujęta. Ta pozycja jest pominięta.';
                }
                if (! $excluded && $line->qualification !== 'excluded' && $opportunity) {
                    $seenOpportunities[] = $opportunity;
                }
                $factor = $excluded ? 0 : $this->factor($line, $scenario);
                if ($excluded) {
                    $lineWarnings[] = 'Oferta pominięta: przegrana, archiwalna albo zastąpiona projektem / inną ofertą.';
                }
                $sourceRevenue = $offer?->kwota_netto;
                $revenue ??= $sourceRevenue;
                if ($remainingCost === null && $factor > 0) {
                    $lineWarnings[] = 'Koszt oferty nieoszacowany — wynik wymaga uzupełnienia.';
                }
                if ($line->updated_at->lt(now()->subDays(30)) && $factor > 0) {
                    $lineWarnings[] = 'Ocena szans nie była aktualizowana od ponad 30 dni.';
                }
            } elseif ($line->kind === 'project') {
                $project = $line->project;
                $entries = $project->financialEntries->filter(fn ($entry) => $entry->source !== 'requirement'
                    || in_array($entry->projectRequirement?->status, ['purchased', 'completed'], true));
                foreach ($entries->where('status', 'paid') as $entry) {
                    $accountEntries[$entry->id] = $entry;
                }
                $invoices = $entries->where('type', 'invoice')->whereIn('status', ['planned', 'issued', 'paid']);
                $costEntries = $entries->where('type', 'cost')->whereIn('status', ['planned', 'issued', 'paid']);
                $requirements = $project->requirements->where('status', '!=', 'cancelled')
                    ->reject(fn ($requirement) => $costEntries->contains('project_requirement_id', $requirement->id));
                $sourceRevenue = (float) $invoices->sum('amount');
                $sourceCost = (float) $costEntries->sum('amount') + (float) $requirements->sum('estimated_cost');
                foreach ($invoices->merge($costEntries) as $entry) {
                    if ($entry->status === 'issued' && $entry->entry_date->betweenIncluded($plan->periodStart(), $plan->periodEnd())) {
                        $unpaid[$entry->type === 'invoice' ? 'revenue' : 'cost'] += $this->cents($entry->amount);
                        $unpaid[$entry->type === 'invoice' ? 'invoiceCount' : 'costCount']++;
                    }
                    $sourceItems[] = ['name' => $entry->name.($entry->document_number ? ' · '.$entry->document_number : ''), 'type' => $entry->type, 'amount' => $this->cents($entry->amount), 'date' => $entry->entry_date->format('d.m.Y'), 'included' => $entry->entry_date->betweenIncluded($plan->periodStart(), $plan->periodEnd())];
                }
                foreach ($invoices as $entry) {
                    $amount = $this->cents($entry->amount);
                    $this->atDate($forecast['contracted'], $amount, $entry->entry_date, $plan);
                    if (in_array($entry->status, ['issued', 'paid'], true)) {
                        $this->atDate($actual['revenue'], $amount, $entry->entry_date, $plan);
                    }
                }
                foreach ($costEntries as $entry) {
                    $amount = $this->cents($entry->amount);
                    $this->atDate($forecast['direct_cost'], $amount, $entry->entry_date, $plan);
                    if (in_array($entry->status, ['issued', 'paid'], true)) {
                        $this->atDate($actual['direct_cost'], $amount, $entry->entry_date, $plan);
                    }
                    if ($entry->projectRequirement && $entry->projectRequirement->estimated_cost === null) {
                        $lineWarnings[] = 'Koszt dostawy nieoszacowany — uzupełnij wycenę w materiałach i usługach projektu.';
                    }
                }
                foreach ($requirements as $requirement) {
                    if ($requirement->estimated_cost === null) {
                        $lineWarnings[] = 'Koszt dostawy nieoszacowany — uzupełnij wycenę w materiałach i usługach projektu.';
                    }
                    $realized = in_array($requirement->status, ['purchased', 'completed'], true);
                    $date = $requirement->needed_by ?? ($realized ? $requirement->updated_at : null);
                    $sourceItems[] = ['name' => $requirement->name, 'type' => 'cost', 'amount' => $this->cents($requirement->estimated_cost), 'date' => $date?->format('d.m.Y') ?? 'brak daty', 'included' => $date?->betweenIncluded($plan->periodStart(), $plan->periodEnd()) ?? false];
                    if ($date) {
                        $this->atDate($forecast['direct_cost'], $this->cents($requirement->estimated_cost), $date, $plan);
                        if ($realized) {
                            $this->atDate($actual['direct_cost'], $this->cents($requirement->estimated_cost), $date, $plan);
                        }
                    } else {
                        $lineWarnings[] = 'Brak terminu dostawy — uzupełnij datę w materiałach i usługach projektu. Koszt nie został rozłożony na miesiące.';
                    }
                }
                if ($invoices->isEmpty() || $sourceRevenue < (float) $project->contract_value) {
                    $lineWarnings[] = 'Harmonogram faktur nie obejmuje całej wartości umowy. Uzupełnij planowane faktury w finansach projektu; brakująca kwota nie jest rozkładana automatycznie.';
                }
                if ($sourceRevenue > (float) $project->contract_value) {
                    $lineWarnings[] = 'Faktury wystawione i planowane przekraczają wartość umowy.';
                }
                if ($costEntries->isEmpty() && $requirements->isEmpty()) {
                    $lineWarnings[] = 'Koszt projektu nieoszacowany — uzupełnij planowane dostawy lub realizacje w projekcie (dopuszczalna wycena 0).';
                }
                if ($project->status === 'cancelled') {
                    $lineWarnings[] = 'Projekt jest anulowany. Sprawdź wartość i pozostałe zobowiązania.';
                }
                foreach ($entries->whereIn('status', ['issued', 'paid', 'planned']) as $entry) {
                    // Budgeting assumption only: do not change the saved source date.
                    $paymentDate = $entry->payment_date ?? $entry->entry_date->copy()->addDays(30);
                    $this->atDate($cash[$entry->type === 'invoice' ? 'in' : 'out'], $this->cents($entry->amount), $paymentDate, $plan);
                }

            }

            if (in_array($line->kind, ['offer', 'revenue'], true)) {
                if ($line->kind === 'revenue' && $remainingCost === null) {
                    $lineWarnings[] = 'Koszt planowanej sprzedaży nieoszacowany — wpisz także jawne 0.';
                }
                if ($revenue === null && $factor > 0) {
                    $lineWarnings[] = 'Brak kwoty przychodu.';
                }
                $key = ['offer' => 'pipeline', 'project' => 'contracted', 'revenue' => 'manual_revenue'][$line->kind];
                if ($line->kind === 'offer' && $line->offer_schedule) {
                    $scheduled = collect($line->offer_schedule);
                    if ($scheduled->where('direction', 'in')->sum(fn ($row) => $this->cents($row['amount'])) !== $this->cents($revenue) && $factor > 0) {
                        $lineWarnings[] = 'Wartość oferty zmieniła się — zaktualizuj harmonogram wpływów.';
                    }
                    foreach ($line->offer_schedule as $payment) {
                        $amount = (int) round($this->cents($payment['amount']) * $factor);
                        $date = CarbonImmutable::parse($payment['date']);
                        $sourceItems[] = ['name' => $payment['direction'] === 'in' ? 'Płatność oferty' : 'Koszt oferty', 'type' => $payment['direction'] === 'in' ? 'invoice' : 'cost', 'amount' => $this->cents($payment['amount']), 'date' => $date->format('d.m.Y'), 'included' => $date->betweenIncluded($plan->periodStart(), $plan->periodEnd())];
                        $this->atDate($forecast[$payment['direction'] === 'in' ? 'pipeline' : 'direct_cost'], $amount, $date, $plan);
                        $this->atDate($cash[$payment['direction']], $amount, $date, $plan);
                        if ($payment['direction'] === 'in' && $factor > 0) {
                            $this->atDate($offerForecast['value'], $this->cents($payment['amount']), $date, $plan);
                        }
                    }
                    $this->spread($forecast['direct_cost'], (int) round($this->cents($line->risk_reserve) * $factor), $line, $plan);
                } else {
                    $lineRevenue = $this->spread($forecast[$key], (int) round($this->cents($revenue) * $factor), $line, $plan);
                    $lineCost = $this->spread($forecast['direct_cost'], (int) round(($this->cents($remainingCost) + $this->cents($line->risk_reserve)) * $factor), $line, $plan);
                    if ($line->kind === 'offer' && $factor > 0) {
                        $this->spread($offerForecast['value'], $this->cents($revenue), $line, $plan);
                        $lineWarnings[] = 'Brak harmonogramu płatności oferty — oferta nie wchodzi do przepływów.';
                    }
                }
                if ($line->kind === 'offer') {
                    foreach (array_keys($months) as $month) {
                        $offerForecast['revenue'][$month] += $forecast['pipeline'][$month] - $beforeForecast['pipeline'][$month];
                        $offerForecast['cost'][$month] += $forecast['direct_cost'][$month] - $beforeForecast['direct_cost'][$month];
                    }
                }
            } elseif ($line->kind === 'person') {
                $lineCost = $this->monthly($forecast['salary'], $this->cents($line->salary_cost), $line, $plan)
                    + $this->monthly($forecast['car'], $this->cents($line->car_cost), $line, $plan);
                if ($line->actual_salary_cost !== null) {
                    $this->monthly($actual['salary'], $this->cents($line->actual_salary_cost), $line, $plan);
                }
                if ($line->actual_car_cost !== null) {
                    $this->monthly($actual['car'], $this->cents($line->actual_car_cost), $line, $plan);
                }
                if ($line->actual_salary_cost === null || ($this->cents($line->car_cost) > 0 && $line->actual_car_cost === null)) {
                    $lineWarnings[] = 'Nie uzupełniono wykonania kosztów wydziałowych — wynik wykonania jest częściowy.';
                }
            } elseif ($line->kind === 'overhead') {
                $lineCost = $this->monthly($forecast['overhead'], $this->cents($line->cost_override), $line, $plan);
                if ($line->actual_cost !== null) {
                    $this->monthly($actual['overhead'], $this->cents($line->actual_cost), $line, $plan);
                } else {
                    $lineWarnings[] = 'Nie uzupełniono wykonania pozostałego kosztu działu — wynik wykonania jest częściowy.';
                }
            }
            foreach ($lineWarnings as $warning) {
                $warnings[] = $line->name.': '.$warning;
            }
            $lineRevenue = array_sum($forecast['contracted']) + array_sum($forecast['pipeline']) + array_sum($forecast['manual_revenue']) - $revenueBefore;
            $lineCost = array_sum($forecast['direct_cost']) + array_sum($forecast['salary']) + array_sum($forecast['car']) + array_sum($forecast['overhead']) - $costBefore;
            if ($line->kind === 'project') {
                $projectCosts += $lineCost;
            }
            foreach (['contracted', 'pipeline', 'manual_revenue', 'direct_cost', 'salary', 'car', 'overhead'] as $row) {
                foreach ($forecast[$row] as $month => $amount) {
                    $contribution = $amount - $beforeForecast[$row][$month];
                    if ($contribution !== 0) {
                        $breakdown[$row][$month][] = ['line_id' => $line->id, 'name' => $line->name, 'amount' => $contribution];
                    }
                }
            }
            $details[$line->id] = compact('sourceRevenue', 'sourceCost', 'lineRevenue', 'lineCost', 'factor', 'lineWarnings', 'sourceItems');
        }
        foreach (array_keys($months) as $month) {
            $forecast['margin'][$month] = $forecast['contracted'][$month] + $forecast['pipeline'][$month] + $forecast['manual_revenue'][$month] - $forecast['direct_cost'][$month];
            $forecast['result'][$month] = $forecast['margin'][$month] - $forecast['salary'][$month] - $forecast['car'][$month] - $forecast['overhead'][$month];
            $forecast['gap'][$month] = max(0, -$forecast['result'][$month]);
            $cash['balance'][$month] = $cash['in'][$month] - $cash['out'][$month];
            $actual['result'][$month] = $actual['revenue'][$month] - $actual['direct_cost'][$month] - $actual['salary'][$month] - $actual['car'][$month] - $actual['overhead'][$month];
        }

        $account = $this->account($plan, $accountEntries, $complete);

        return ['months' => $months, 'forecast' => $forecast, 'actual' => $actual, 'cash' => $cash, 'account' => $account, 'offerForecast' => $offerForecast, 'projectCosts' => $projectCosts, 'unpaid' => $unpaid, 'details' => $details, 'breakdown' => $breakdown, 'warnings' => array_values(array_unique($warnings)), 'complete' => $complete];
    }

    private function account(FinancialPlan $plan, array $entries, bool $sourcesComplete): array
    {
        $today = CarbonImmutable::now('Europe/Warsaw')->endOfDay();
        $cutoff = $today->min($plan->periodEnd());
        $transactions = [];
        $warnings = [];
        foreach ($entries as $entry) {
            if (! $entry->settled_on || $entry->settled_amount === null) {
                if ($entry->entry_date->lte($cutoff)) {
                    $warnings[] = $entry->name.': opłacona pozycja bez faktycznej daty lub kwoty zapłaty. Uzupełnij dane w edycji finansów projektu.';
                }

                continue;
            }
            if (! $entry->settled_on->betweenIncluded($plan->periodStart(), $cutoff)) {
                continue;
            }
            $transactions[] = ['id' => 'project-'.$entry->id, 'manual_id' => null, 'date' => $entry->settled_on->format('Y-m-d'), 'description' => $entry->name, 'direction' => $entry->type === 'invoice' ? 'in' : 'out', 'amount' => $this->cents($entry->settled_amount), 'source' => 'Faktura projektu', 'project_id' => $entry->project_id];
        }
        $payments = FinancialDepartmentPayment::where('financial_department_id', $plan->financial_department_id)
            ->whereDate('paid_on', '>=', $plan->periodStart()->toDateString())->whereDate('paid_on', '<=', $cutoff->toDateString())->get();
        foreach ($payments as $payment) {
            $transactions[] = ['id' => 'manual-'.$payment->id, 'manual_id' => $payment->id, 'date' => $payment->paid_on->format('Y-m-d'), 'description' => $payment->description, 'direction' => $payment->direction, 'amount' => $this->cents($payment->amount), 'source' => 'Płatność działu', 'project_id' => null];
        }
        $transactions = collect($transactions)->sortBy('date')->values()->all();
        $balance = $plan->opening_balance !== null ? $this->cents($plan->opening_balance) : null;
        $initialBalance = $balance;
        $received = $spent = 0;
        $months = [];
        foreach ($plan->months() as $month) {
            if ($month->gt($today)) {
                break;
            }
            $monthlyIn = $monthlyOut = 0;
            foreach ($transactions as $transaction) {
                if (substr($transaction['date'], 0, 7) === $month->format('Y-m')) {
                    if ($transaction['direction'] === 'in') {
                        $monthlyIn += $transaction['amount'];
                    } else {
                        $monthlyOut += $transaction['amount'];
                    }
                }
            }
            $received += $monthlyIn;
            $spent += $monthlyOut;
            if ($balance !== null) {
                $balance += $monthlyIn - $monthlyOut;
            }
            $months[] = ['date' => $month->format('Y-m'), 'label' => $month->format('m.Y'), 'received' => $received, 'spent' => $spent, 'monthlyIn' => $monthlyIn, 'monthlyOut' => $monthlyOut, 'balance' => $balance];
        }

        return ['opening' => $initialBalance, 'balance' => $months ? $balance : null, 'received' => $received, 'spent' => $spent, 'months' => $months, 'transactions' => $transactions, 'warnings' => array_values(array_unique($warnings)), 'complete' => $initialBalance !== null && $sourcesComplete && ! $warnings, 'asOf' => $cutoff->toDateString()];
    }

    private function factor(FinancialPlanLine $line, string $scenario): float
    {
        if ($line->qualification === 'excluded') {
            return 0;
        }
        if ($line->qualification === 'confirmed') {
            return 1;
        }
        if ($scenario === 'cautious') {
            return 0;
        }
        if ($line->qualification === 'reserve' && $scenario !== 'optimistic') {
            return 0;
        }

        return $scenario === 'optimistic' ? 1 : $line->probability / 100;
    }

    private function emptyRows(array $keys, array $months): array
    {
        return array_fill_keys($keys, array_fill_keys(array_keys($months), 0));
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) ($value ?? 0) * 100);
    }

    private function atDate(array &$months, int $amount, mixed $date, FinancialPlan $plan): void
    {
        if ($date && $date->betweenIncluded($plan->periodStart(), $plan->periodEnd())) {
            $index = (int) $plan->periodStart()->startOfMonth()->diffInMonths(CarbonImmutable::parse($date)->startOfMonth()) + 1;
            $months[$index] += $amount;
        }
    }

    private function spread(array &$months, int $amount, FinancialPlanLine $line, FinancialPlan $plan): int
    {
        $start = CarbonImmutable::parse($line->start_date)->startOfMonth();
        $end = CarbonImmutable::parse($line->end_date)->startOfMonth();
        $count = (int) $start->diffInMonths($end) + 1;
        $part = intdiv($amount, $count);
        $remainder = $amount % $count;
        $total = 0;
        for ($index = 0; $index < $count; $index++) {
            $date = $start->addMonths($index);
            $value = $part + ($index < $remainder ? 1 : 0);
            $this->atDate($months, $value, $date, $plan);
            if ($date->betweenIncluded($plan->periodStart(), $plan->periodEnd())) {
                $total += $value;
            }
        }

        return $total;
    }

    private function monthly(array &$months, int $amount, FinancialPlanLine $line, FinancialPlan $plan): int
    {
        $total = 0;
        foreach ($plan->months() as $month => $date) {
            if ($date->betweenIncluded(CarbonImmutable::parse($line->start_date)->startOfMonth(), CarbonImmutable::parse($line->end_date)->startOfMonth())) {
                $months[$month] += $amount;
                $total += $amount;
            }
        }

        return $total;
    }
}
