<?php

namespace App\Services;

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

    public function calculate(FinancialPlan $plan, User $user, string $scenario = 'base'): array
    {
        $access = app(FinancialPlanAccess::class);
        $projectIds = $access->projects($user)->pluck('id')->all();
        $offerIds = $access->offers($user)->pluck('id')->all();
        $plan->load('lines.project.financialEntries.projectRequirement', 'lines.project.requirements', 'lines.offer.crmOpportunity', 'lines.person');
        $forecast = $this->emptyRows(array_keys(self::ROWS));
        $actual = $this->emptyRows(['revenue', 'direct_cost', 'salary', 'car', 'overhead', 'result']);
        $cash = $this->emptyRows(['in', 'out', 'balance']);
        $warnings = [];
        $details = [];
        $complete = true;
        $linkedProjects = FinancialPlanLine::where('kind', 'project')
            ->whereIn('financial_plan_id', FinancialPlan::where('year', $plan->year)->select('id'))->with('offer:id,crm_opportunity_id')->get();
        $representedOffers = $linkedProjects->pluck('offer_id')->filter()->all();
        $representedOpportunities = $linkedProjects->map(fn ($line) => $line->offer?->crm_opportunity_id)->filter()->all();
        $seenOpportunities = [];
        $costedPeople = $plan->lines->where('kind', 'person')->pluck('user_id');
        foreach ($plan->department->members as $member) {
            if (! $costedPeople->contains($member->id)) {
                $warnings[] = $member->name.': koszt osoby nieoszacowany — dodaj wynagrodzenie i okres przypisania do działu.';
            }
        }

        foreach ($plan->lines->sortBy('id') as $line) {
            if (($line->project_id && ! in_array($line->project_id, $projectIds, true))
                || ($line->offer_id && ! in_array($line->offer_id, $offerIds, true))) {
                $complete = false;
                $warnings[] = 'Część źródeł jest niedostępna w Twoim zakresie uprawnień. Sumy są częściowe; zatwierdzanie jest zablokowane.';

                continue;
            }
            $lineWarnings = [];
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
                $sourceRevenue = $project->contract_value;
                $revenue ??= $sourceRevenue;
                $entries = $project->effectiveFinancialEntries();
                $costEntries = $entries->where('type', 'cost');
                $committed = $costEntries->whereIn('status', ['issued', 'paid']);
                $planned = $costEntries->where('status', 'planned');
                $requirements = $project->requirements->whereNotIn('status', ['cancelled', 'purchased', 'completed'])
                    ->reject(fn ($requirement) => $costEntries->contains('project_requirement_id', $requirement->id));
                $sourceCost = (float) $planned->sum('amount') + (float) $requirements->sum('estimated_cost');
                $remainingCost ??= $sourceCost;
                if ($line->cost_override === null && ($requirements->contains(fn ($requirement) => $requirement->estimated_cost === null)
                    || ($requirements->isEmpty() && $planned->isEmpty() && ($committed->isEmpty() || $line->budget_accuracy === 'initial')))) {
                    $lineWarnings[] = 'Koszt projektu nieoszacowany — uzupełnij pozostały koszt realizacji (także jawne 0).';
                }
                if ($project->status === 'cancelled') {
                    $lineWarnings[] = 'Projekt jest anulowany. Sprawdź wartość i pozostałe zobowiązania.';
                }
                $invoices = $entries->where('type', 'invoice')->whereIn('status', ['issued', 'paid']);
                $plannedInvoices = $entries->where('type', 'invoice')->where('status', 'planned');
                foreach ($invoices as $entry) {
                    $amount = $this->cents($entry->amount);
                    $this->atDate($forecast['contracted'], $amount, $entry->entry_date, $plan->year);
                    $this->atDate($actual['revenue'], $amount, $entry->entry_date, $plan->year);
                }
                foreach ($plannedInvoices as $entry) {
                    $this->atDate($forecast['contracted'], $this->cents($entry->amount), $entry->entry_date, $plan->year);
                }
                // The contract remainder replaces, rather than repeats, already issued invoices.
                $scheduledRevenue = $this->cents($invoices->sum('amount')) + $this->cents($plannedInvoices->sum('amount'));
                $revenue = max(0, $this->cents($revenue) - $scheduledRevenue) / 100;
                if ($scheduledRevenue > $this->cents($line->revenue_override ?? $sourceRevenue)) {
                    $lineWarnings[] = 'Faktury wystawione i planowane przekraczają przyjętą wartość umowy.';
                }
                foreach ($committed as $entry) {
                    $amount = $this->cents($entry->amount);
                    $this->atDate($forecast['direct_cost'], $amount, $entry->entry_date, $plan->year);
                    $this->atDate($actual['direct_cost'], $amount, $entry->entry_date, $plan->year);
                }
                if ($line->cost_override === null) {
                    foreach ($planned as $entry) {
                        $this->atDate($forecast['direct_cost'], $this->cents($entry->amount), $entry->entry_date, $plan->year);
                    }
                    foreach ($requirements as $requirement) {
                        if ($requirement->needed_by) {
                            $this->atDate($forecast['direct_cost'], $this->cents($requirement->estimated_cost), $requirement->needed_by, $plan->year);
                        } else {
                            $this->spread($forecast['direct_cost'], $this->cents($requirement->estimated_cost), $line, $plan->year);
                        }
                    }
                    $remainingCost = 0;
                }
                foreach ($entries->whereIn('status', ['issued', 'paid', 'planned']) as $entry) {
                    if ($entry->payment_date) {
                        $this->atDate($cash[$entry->type === 'invoice' ? 'in' : 'out'], $this->cents($entry->amount), $entry->payment_date, $plan->year);
                    } else {
                        $lineWarnings[] = 'Brak terminu płatności części zapisów — przepływy pieniężne są niepełne.';
                    }
                }
            }

            if (in_array($line->kind, ['offer', 'project', 'revenue'], true)) {
                if ($line->kind === 'revenue' && $remainingCost === null) {
                    $lineWarnings[] = 'Koszt planowanej sprzedaży nieoszacowany — wpisz także jawne 0.';
                }
                if ($revenue === null && $factor > 0) {
                    $lineWarnings[] = 'Brak kwoty przychodu.';
                }
                $key = ['offer' => 'pipeline', 'project' => 'contracted', 'revenue' => 'manual_revenue'][$line->kind];
                $lineRevenue = $this->spread($forecast[$key], (int) round($this->cents($revenue) * $factor), $line, $plan->year);
                $lineCost = $this->spread($forecast['direct_cost'], (int) round(($this->cents($remainingCost) + $this->cents($line->risk_reserve)) * $factor), $line, $plan->year);
            } elseif ($line->kind === 'person') {
                $lineCost = $this->monthly($forecast['salary'], $this->cents($line->salary_cost), $line, $plan->year)
                    + $this->monthly($forecast['car'], $this->cents($line->car_cost), $line, $plan->year);
                if ($line->actual_salary_cost !== null) {
                    $this->monthly($actual['salary'], $this->cents($line->actual_salary_cost), $line, $plan->year);
                }
                if ($line->actual_car_cost !== null) {
                    $this->monthly($actual['car'], $this->cents($line->actual_car_cost), $line, $plan->year);
                }
                if ($line->actual_salary_cost === null || ($this->cents($line->car_cost) > 0 && $line->actual_car_cost === null)) {
                    $lineWarnings[] = 'Nie uzupełniono wykonania kosztów wydziałowych — wynik wykonania jest częściowy.';
                }
            } elseif ($line->kind === 'overhead') {
                $lineCost = $this->monthly($forecast['overhead'], $this->cents($line->cost_override), $line, $plan->year);
                if ($line->actual_cost !== null) {
                    $this->monthly($actual['overhead'], $this->cents($line->actual_cost), $line, $plan->year);
                } else {
                    $lineWarnings[] = 'Nie uzupełniono wykonania pozostałego kosztu działu — wynik wykonania jest częściowy.';
                }
            }
            foreach ($lineWarnings as $warning) {
                $warnings[] = $line->name.': '.$warning;
            }
            $lineRevenue = array_sum($forecast['contracted']) + array_sum($forecast['pipeline']) + array_sum($forecast['manual_revenue']) - $revenueBefore;
            $lineCost = array_sum($forecast['direct_cost']) + array_sum($forecast['salary']) + array_sum($forecast['car']) + array_sum($forecast['overhead']) - $costBefore;
            $details[$line->id] = compact('sourceRevenue', 'sourceCost', 'lineRevenue', 'lineCost', 'factor', 'lineWarnings');
        }
        foreach (range(1, 12) as $month) {
            $forecast['margin'][$month] = $forecast['contracted'][$month] + $forecast['pipeline'][$month] + $forecast['manual_revenue'][$month] - $forecast['direct_cost'][$month];
            $forecast['result'][$month] = $forecast['margin'][$month] - $forecast['salary'][$month] - $forecast['car'][$month] - $forecast['overhead'][$month];
            $forecast['gap'][$month] = max(0, -$forecast['result'][$month]);
            $cash['balance'][$month] = $cash['in'][$month] - $cash['out'][$month];
            $actual['result'][$month] = $actual['revenue'][$month] - $actual['direct_cost'][$month] - $actual['salary'][$month] - $actual['car'][$month] - $actual['overhead'][$month];
        }

        return ['forecast' => $forecast, 'actual' => $actual, 'cash' => $cash, 'details' => $details, 'warnings' => array_values(array_unique($warnings)), 'complete' => $complete];
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

    private function emptyRows(array $keys): array
    {
        return array_fill_keys($keys, array_fill(1, 12, 0));
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) ($value ?? 0) * 100);
    }

    private function atDate(array &$months, int $amount, mixed $date, int $year): void
    {
        if ($date && (int) $date->format('Y') === $year) {
            $months[(int) $date->format('n')] += $amount;
        }
    }

    private function spread(array &$months, int $amount, FinancialPlanLine $line, int $year): int
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
            $this->atDate($months, $value, $date, $year);
            if ($date->year === $year) {
                $total += $value;
            }
        }

        return $total;
    }

    private function monthly(array &$months, int $amount, FinancialPlanLine $line, int $year): int
    {
        $total = 0;
        foreach (range(1, 12) as $month) {
            $date = CarbonImmutable::create($year, $month, 1);
            if ($date->betweenIncluded(CarbonImmutable::parse($line->start_date)->startOfMonth(), CarbonImmutable::parse($line->end_date)->startOfMonth())) {
                $months[$month] += $amount;
                $total += $amount;
            }
        }

        return $total;
    }
}
