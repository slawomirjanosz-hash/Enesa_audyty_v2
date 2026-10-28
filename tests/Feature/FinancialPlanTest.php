<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\CrmOpportunity;
use App\Models\FinancialDepartment;
use App\Models\FinancialDepartmentPayment;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanLine;
use App\Models\Offer;
use App\Models\Project;
use App\Models\ProjectFinancialEntry;
use App\Models\ProjectRequirement;
use App\Models\User;
use App\Services\FinancialPlanCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::findOrCreate('superadmin'));
    $this->department = FinancialDepartment::create(['name' => 'Dział realizacji', 'owner_id' => $this->manager->id]);
    $this->plan = $this->department->plans()->create(['year' => 2027]);
});

function financialLineData(array $overrides = []): array
{
    return array_replace([
        'kind' => 'revenue', 'name' => 'Założenie sprzedażowe', 'qualification' => 'real', 'probability' => 50,
        'budget_accuracy' => 'initial', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
        'revenue_override' => 120000, 'cost_override' => 20000, 'notes' => 'Założenie potwierdzone z kierownikiem.', 'revision' => 0,
    ], $overrides);
}

function addFinancialLine(FinancialPlan $plan, User $manager, array $overrides = []): FinancialPlanLine
{
    $data = financialLineData($overrides);
    unset($data['revision']);

    return $plan->lines()->create($data + ['updated_by' => $manager->id]);
}

function financialProject(User $manager, array $overrides = []): Project
{
    return Project::create(array_replace([
        'number' => 'FP-'.uniqid(), 'name' => 'Projekt do planu', 'status' => 'active',
        'manager_id' => $manager->id, 'created_by' => $manager->id, 'contract_value' => 120000,
    ], $overrides));
}

test('plan lines can be excluded and restored without losing their values', function () {
    $line = addFinancialLine($this->plan, $this->manager);
    $calculator = app(FinancialPlanCalculator::class);
    expect(array_sum($calculator->calculate($this->plan, $this->manager)['forecast']['manual_revenue']))->toBe(12000000);
    $this->actingAs($this->manager)->put(route('financial-plans.lines.enabled', [$this->plan, $line]), ['revision' => 0, 'enabled' => false])->assertSessionHasNoErrors();
    $result = $calculator->calculate($this->plan->fresh(), $this->manager);
    expect(array_sum($result['forecast']['manual_revenue']))->toBe(0)
        ->and(array_sum($result['forecast']['direct_cost']))->toBe(0)
        ->and($result['details'][$line->id]['lineRevenue'])->toBe(0)
        ->and((float) $line->fresh()->revenue_override)->toBe(120000.0);
    $this->put(route('financial-plans.lines.enabled', [$this->plan, $line]), ['revision' => 0, 'enabled' => true])->assertStatus(409);
    $this->put(route('financial-plans.lines.enabled', [$this->plan, $line]), ['revision' => 1, 'enabled' => true])->assertSessionHasNoErrors();
    expect(array_sum($calculator->calculate($this->plan->fresh(), $this->manager)['forecast']['manual_revenue']))->toBe(12000000);
});

test('department plan list shows names periods creation dates and compact actions', function () {
    $this->department->update(['name' => 'Energetyka']);
    $this->plan->update(['name' => 'Budżet rozwoju']);
    $response = $this->actingAs($this->manager)->get(route('financial-plans.index'))->assertOk()
        ->assertSee('Energetyka')->assertSee('Budżet rozwoju')->assertSee('Utworzono')->assertSee('Edytuj plan')->assertSee('Usuń plan')->assertDontSee('bieżąca prognoza')->assertDontSee('Otwórz / utwórz plan');
    $xpath = financialLiveXPath($response->getContent());
    expect($xpath->query('//input[@type="month" and not(ancestor::details)]')->length)->toBe(0);
    expect($xpath->query('//details[contains(@class,"fp-plan-edit")]')->length)->toBe(0);
    expect($xpath->query('//a[@aria-label="Edytuj plan"]/@href')->item(0)->nodeValue)->toBe(route('financial-plans.show', $this->plan));
    $this->get(route('financial-plans.show', $this->plan))->assertOk()->assertSee('Budżet rozwoju');
    $this->put(route('financial-plans.period.update', $this->plan), ['revision' => 0, 'name' => 'Plan bazowy', 'start_month' => '2027-01', 'end_month' => '2027-12'])->assertSessionHasNoErrors();
    expect($this->plan->fresh()->name)->toBe('Plan bazowy');
});

test('Excel exports contain numeric chart values and preserve literal source text', function (string $section) {
    addFinancialLine($this->plan, $this->manager, ['kind' => 'person', 'user_id' => $this->manager->id, 'salary_cost' => 100, 'car_cost' => 25]);
    addFinancialLine($this->plan, $this->manager, ['name' => '=HYPERLINK("https://example.invalid")']);
    $response = $this->actingAs($this->manager)->get(route('financial-plans.export', [$this->plan, 'section' => $section, 'scenario' => 'base']))->assertOk()->assertDownload('plan-'.$this->plan->id.'-'.$section.'.xlsx')->assertHeader('Cache-Control', 'no-store, private');
    \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder);
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx;
    $reader->setIncludeCharts(true);
    $book = $reader->load($response->baseResponse->getFile()->getPathname());
    $sheet = $book->getActiveSheet();
    expect($sheet->getCell('B4')->getValue())->toBe('Bazowy');
    if ($section === 'sources') {
        expect($sheet->getCell('A9')->getValue())->toStartWith('=HYPERLINK')
            ->and($sheet->getCell('A9')->getDataType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
            ->and($sheet->getCell('J8')->getCalculatedValue())->toEqual(1500)
            ->and($sheet->getCell('J8')->getDataType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    } else {
        expect($sheet->getChartCount())->toBe(1);
        if ($section !== 'result') {
            expect($sheet->getCell('B8')->getCalculatedValue())->toEqual(100)
                ->and($sheet->getCell('D8')->getCalculatedValue())->toEqual(0)
                ->and($sheet->getCell('E9')->getCalculatedValue())->toEqual($section === 'cost-cumulative' ? 250 : 125)
                ->and($sheet->getCell('E9')->getDataType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
        }
    }
    $book->disconnectWorksheets();
})->with(['cost-monthly', 'cost-cumulative', 'result', 'sources']);

test('Excel exports enforce access and omit inaccessible sources', function () {
    $project = financialProject($this->manager, ['name' => 'Tajny projekt eksportu']);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('employee'));
    $viewer->givePermissionTo('financial_plans.view');
    $url = route('financial-plans.export', [$this->plan, 'section' => 'sources']);
    $this->actingAs($viewer)->get($url)->assertForbidden();
    $this->department->planners()->attach($viewer);
    $response = $this->get($url)->assertOk();
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($response->baseResponse->getFile()->getPathname());
    expect(json_encode($book->getActiveSheet()->toArray()))->not->toContain('Tajny projekt eksportu');
    expect($book->getActiveSheet()->getCell('B5')->getValue())->toContain('Sumy częściowe');
    $book->disconnectWorksheets();
    $this->get(route('financial-plans.export', [$this->plan, 'section' => 'unknown']))->assertSessionHasErrors('section');
});

test('forecast cash and people tables export their current numeric values', function (string $section) {
    addFinancialLine($this->plan, $this->manager, ['kind' => 'person', 'user_id' => $this->manager->id, 'salary_cost' => 100, 'car_cost' => 25]);
    addFinancialLine($this->plan, $this->manager);
    $response = $this->actingAs($this->manager)->get(route('financial-plans.export', [$this->plan, 'section' => $section]))->assertOk()->assertDownload('plan-'.$this->plan->id.'-'.$section.'.xlsx');
    \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder);
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($response->baseResponse->getFile()->getPathname());
    $sheet = $book->getActiveSheet();
    if ($section === 'forecast') {
        expect($sheet->getCell('A10')->getValue())->toBe('Pozostała planowana sprzedaż')
            ->and($sheet->getCell('N10')->getCalculatedValue())->toEqual(120000)
            ->and($sheet->getCell('B13')->getValue())->toBe(100);
    } elseif ($section === 'cash') {
        expect($sheet->getCell('A8')->getValue())->toBe('Wpływy · projekty i oferty')
            ->and($sheet->getCell('B8')->getValue())->toBe(0)
            ->and($sheet->getCell('N10')->getCalculatedValue())->toEqual(0);
    } elseif ($section === 'cost-values') {
        expect($sheet->getCell('F9')->getCalculatedValue())->toEqual(250);
    } else {
        expect($sheet->getCell('A8')->getValue())->toBe($this->manager->name)
            ->and($sheet->getCell('C8')->getValue())->toBe(100.0)
            ->and($sheet->getCell('F8')->getCalculatedValue())->toEqual(1500);
    }
    $book->disconnectWorksheets();
})->with(['forecast', 'cash', 'people', 'cost-values']);

test('exported formulas recalculate after changing their input cells', function (string $section, string $input, int $value, string $output, float $expected) {
    addFinancialLine($this->plan, $this->manager, ['kind' => 'person', 'user_id' => $this->manager->id, 'salary_cost' => 100, 'car_cost' => 25]);
    addFinancialLine($this->plan, $this->manager);
    $response = $this->actingAs($this->manager)->get(route('financial-plans.export', [$this->plan, 'section' => $section]))->assertOk();
    \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder);
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($response->baseResponse->getFile()->getPathname());
    $sheet = $book->getActiveSheet();
    expect($sheet->getCell($output)->getDataType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $sheet->setCellValue($input, $value);
    \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($book)->clearCalculationCache();
    expect((float) $sheet->getCell($output)->getCalculatedValue())->toEqualWithDelta($expected, 0.00001);
    $book->disconnectWorksheets();
})->with([
    ['forecast', 'B10', 2000, 'N10', 112000.0],
    ['cash', 'B8', 2000, 'N10', 2000.0],
    ['people', 'C8', 200, 'F8', 2700.0],
    ['sources', 'L9', 2000, 'I9', 112000.0],
    ['result', 'H8', 2000, 'E8', 208.33],
    ['cost-monthly', 'B8', 200, 'E8', 225.0],
    ['cost-cumulative', 'F8', 200, 'E9', 350.0],
    ['cost-values', 'B8', 200, 'F9', 350.0],
]);

test('four forecast tiles reconcile revenue realization costs department costs and result', function () {
    addFinancialLine($this->plan, $this->manager);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'person', 'salary_cost' => 100, 'car_cost' => 25]);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'overhead', 'cost_override' => 50]);
    $html = $this->actingAs($this->manager)->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->json('html');
    $xpath = financialLiveXPath($html);
    $values = [];
    foreach ($xpath->query('//div[contains(@class,"fp-kpi-grid")]/div/div[contains(@class,"fp-kpi")]') as $cell) {
        $values[] = (float) str_replace(',', '.', preg_replace('/[^\d,\-]/u', '', $cell->textContent));
    }
    expect($values)->toBe([120000.0, 20000.0, 2100.0, 97900.0]);
    expect($values[0] - $values[1] - $values[2])->toBe($values[3]);
    foreach (['forecast', 'cash', 'sources', 'people', 'cost-monthly', 'cost-cumulative', 'result'] as $section) {
        expect($html)->toContain('section='.$section);
    }
});

test('creating named plans and deleting a plan preserves project sources and shared payments', function () {
    $this->actingAs($this->manager)->post(route('financial-plans.store', $this->department), ['name' => 'Nowy plan', 'start_month' => '2028-01', 'end_month' => '2028-06'])->assertSessionHasNoErrors();
    expect($this->department->plans()->where('name', 'Nowy plan')->exists())->toBeTrue();
    $project = financialProject($this->manager);
    $line = addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $this->plan->changes()->create(['user_id' => $this->manager->id, 'action' => 'created', 'payload' => []]);
    $payment = FinancialDepartmentPayment::create(['financial_department_id' => $this->department->id, 'direction' => 'in', 'paid_on' => '2027-01-01', 'amount' => 50, 'description' => 'Wpłata', 'created_by' => $this->manager->id]);
    $this->delete(route('financial-plans.destroy', $this->plan), ['revision' => 3])->assertConflict();
    $this->delete(route('financial-plans.destroy', $this->plan), ['revision' => 0])->assertRedirect(route('financial-plans.index'));
    expect($this->plan->fresh())->toBeNull()->and($line->fresh())->toBeNull()->and($project->fresh())->not->toBeNull()->and($payment->fresh())->not->toBeNull();
});

test('plan deletion requires management permission and department access', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('employee'));
    $user->givePermissionTo('financial_plans.manage');
    $this->actingAs($user)->delete(route('financial-plans.destroy', $this->plan), ['revision' => 0])->assertForbidden();
    $this->department->planners()->attach($user);
    $user->revokePermissionTo('financial_plans.manage');
    $user->givePermissionTo('financial_plans.view');
    $this->delete(route('financial-plans.destroy', $this->plan), ['revision' => 0])->assertForbidden();
    expect($this->plan->fresh())->not->toBeNull();
});

test('offer payment schedule drives monthly forecast cash and separate full offer values', function () {
    $company = Company::create(['name' => 'Klient harmonogramu', 'type' => 'client']);
    $offer = Offer::create(['offer_number' => 'SCHEDULE', 'status' => 'w_toku', 'company_id' => $company->id, 'created_by_id' => $this->manager->id, 'kwota_netto' => 1000, 'is_template' => false]);
    $schedule = [
        ['direction' => 'in', 'date' => '2027-01-10', 'amount' => '300'],
        ['direction' => 'in', 'date' => '2027-02-10', 'amount' => '700'],
        ['direction' => 'out', 'date' => '2027-01-05', 'amount' => '100'],
        ['direction' => 'out', 'date' => '2027-03-05', 'amount' => '300'],
    ];
    $data = financialLineData(['kind' => 'offer', 'offer_id' => $offer->id, 'revenue_override' => null, 'cost_override' => null, 'offer_schedule' => $schedule]);
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $this->plan), $data)->assertSessionHasNoErrors();
    $line = $this->plan->lines()->first();
    expect($line->offer_schedule)->toBe($schedule)->and((float) $line->cost_override)->toBe(400.0);
    expect(DB::table('financial_plan_lines')->value('offer_schedule'))->not->toContain('2027-01-10');
    $calc = app(FinancialPlanCalculator::class);
    $result = $calc->calculate($this->plan->fresh(), $this->manager);
    expect(array_slice(array_values($result['forecast']['pipeline']), 0, 3))->toBe([15000, 35000, 0])
        ->and(array_slice(array_values($result['cash']['out']), 0, 3))->toBe([5000, 0, 15000])
        ->and(array_sum($result['cash']['in']))->toBe(50000)
        ->and(array_sum($result['offerForecast']['value']))->toBe(100000);
    $html = $this->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->json('html');
    $xpath = financialLiveXPath($html);
    $first = $xpath->query('//table[@data-fp-table="fp-result-chart-values"]/tbody/tr')->item(0);
    $values = collect(iterator_to_array($first->getElementsByTagName('td')))->slice(1)->map(fn ($cell) => $cell->getAttribute('data-sort-value'))->values()->all();
    expect($values)->toBe(['0', '0', '0', '0', '100', '300']);
    expect(array_sum($calc->calculate($this->plan->fresh(), $this->manager, 'cautious')['cash']['in']))->toBe(0);
    $line->update(['enabled' => false]);
    expect(array_sum($calc->calculate($this->plan->fresh(), $this->manager)['cash']['in']))->toBe(0);
});

test('offer schedule rejects incomplete totals and retains dates beyond selected months', function () {
    $company = Company::create(['name' => 'Klient walidacji', 'type' => 'client']);
    $offer = Offer::create(['offer_number' => 'VALIDATE', 'status' => 'w_toku', 'company_id' => $company->id, 'created_by_id' => $this->manager->id, 'kwota_netto' => 1000, 'is_template' => false]);
    $data = financialLineData(['kind' => 'offer', 'offer_id' => $offer->id, 'revenue_override' => null, 'cost_override' => null, 'offer_schedule' => [['direction' => 'in', 'date' => '2027-02-01', 'amount' => 900], ['direction' => 'out', 'date' => '2027-01-01', 'amount' => 0]]]);
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $this->plan), $data)->assertSessionHasErrors('offer_schedule');
    $data['offer_schedule'][0]['amount'] = 1000;
    $data['offer_schedule'][0]['date'] = '2028-02-01';
    $this->post(route('financial-plans.lines.store', $this->plan), $data)->assertSessionHasNoErrors();
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager);
    expect(array_sum($result['cash']['in']))->toBe(0);
    $this->plan->update(['end_date' => '2028-03-31']);
    expect(array_sum(app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager)['cash']['in']))->toBe(50000);
});

test('line controls enforce department ownership and deletion needs no reason', function () {
    $line = addFinancialLine($this->plan, $this->manager);
    $other = FinancialDepartment::create(['name' => 'Inny dział', 'owner_id' => $this->manager->id])->plans()->create(['year' => 2027]);
    $this->actingAs($this->manager)->put(route('financial-plans.lines.enabled', [$other, $line]), ['revision' => 0, 'enabled' => false])->assertNotFound();
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('employee'));
    $this->actingAs($viewer)->put(route('financial-plans.lines.enabled', [$this->plan, $line]), ['revision' => 0, 'enabled' => false])->assertForbidden();
    $this->actingAs($this->manager)->delete(route('financial-plans.lines.destroy', [$this->plan, $line]), ['revision' => 0])->assertSessionHasNoErrors();
    expect($line->fresh())->toBeNull()
        ->and($this->plan->changes()->where('action', 'deleted')->first()->payload)->not->toHaveKey('reason');
});

test('compact totals separate issued invoices and project costs from offers and planned sales', function () {
    $project = financialProject($this->manager);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    foreach ([['invoice', 'issued', 100], ['invoice', 'paid', 200], ['invoice', 'planned', 300], ['cost', 'planned', 40], ['cost', 'paid', 50]] as [$type, $status, $amount]) {
        $project->financialEntries()->create(['type' => $type, 'status' => $status, 'name' => 'Pozycja', 'entry_date' => '2027-02-01', 'amount' => $amount, 'created_by' => $this->manager->id]);
    }
    addFinancialLine($this->plan, $this->manager);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan, $this->manager);
    expect(array_sum($result['actual']['revenue']))->toBe(30000)->and($result['projectCosts'])->toBe(9000);
    $this->actingAs($this->manager)->get(route('financial-plans.show', $this->plan))->assertOk()->assertSee('Koszty realizacji')->assertDontSee('Przychody nieopłacone')->assertDontSee('Wydatki nieopłacone')->assertSee('Wyłącz z wyliczeń')->assertDontSee('Powód usunięcia');
    $line = $this->plan->lines()->where('kind', 'project')->first();
    $line->update(['enabled' => false]);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager);
    expect(array_sum($result['actual']['revenue']))->toBe(0)
        ->and($result['projectCosts'])->toBe(0)
        ->and(array_sum($result['cash']['in']))->toBe(0)
        ->and($result['account']['transactions'])->toBe([]);
});

test('unpaid tiles count only issued project invoices and costs in the selected period', function () {
    $this->plan->update(['start_date' => '2027-02-01', 'end_date' => '2027-02-28']);
    $project = financialProject($this->manager);
    $line = addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    foreach ([['invoice', 'issued', '2027-02-01', 100], ['invoice', 'issued', '2027-02-28', 250], ['invoice', 'paid', '2027-02-10', 700], ['invoice', 'planned', '2027-02-10', 900], ['invoice', 'issued', '2027-03-01', 500], ['cost', 'issued', '2027-02-10', 80], ['cost', 'paid', '2027-02-10', 40], ['cost', 'planned', '2027-02-10', 60], ['cost', 'issued', '2027-01-31', 30]] as [$type, $status, $date, $amount]) {
        $project->financialEntries()->create(['type' => $type, 'status' => $status, 'name' => 'Pozycja', 'entry_date' => $date, 'amount' => $amount, 'created_by' => $this->manager->id]);
    }
    addFinancialLine($this->plan, $this->manager);
    $calculator = app(FinancialPlanCalculator::class);
    expect($calculator->calculate($this->plan->fresh(), $this->manager)['unpaid'])->toBe(['revenue' => 35000, 'cost' => 8000, 'invoiceCount' => 2, 'costCount' => 1]);
    $first = $this->actingAs($this->manager)->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->json();
    expect($first['html'])->not->toContain('Liczba faktur:')->not->toContain('Liczba zapisów:');
    $project->financialEntries()->where('type', 'invoice')->where('amount', 100)->first()->update(['status' => 'paid']);
    $second = $this->withHeader('X-Plan-Fingerprint', $first['fingerprint'])->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->json();
    expect($second['fingerprint'])->toBe($first['fingerprint']);
    expect($calculator->calculate($this->plan->fresh(), $this->manager)['unpaid']['revenue'])->toBe(25000);
    $line->update(['enabled' => false]);
    expect($calculator->calculate($this->plan->fresh(), $this->manager)['unpaid'])->toBe(['revenue' => 0, 'cost' => 0, 'invoiceCount' => 0, 'costCount' => 0]);
});

test('linked financial lines derive their names and accept no description', function (string $kind) {
    $expected = 'Wybrane źródło';
    if ($kind === 'person') {
        $person = User::factory()->create(['name' => $expected]);
        $this->department->members()->attach($person);
        $source = ['user_id' => $person->id, 'salary_cost' => 12000];
    } elseif ($kind === 'project') {
        $project = financialProject($this->manager, ['name' => $expected]);
        $source = ['project_id' => $project->id];
    } else {
        $company = Company::create(['name' => 'Klient nazwy', 'type' => 'client']);
        $offer = Offer::create(['offer_number' => 'FP-NAME', 'offer_title' => $expected, 'status' => 'w_toku', 'company_id' => $company->id, 'created_by_id' => $this->manager->id, 'kwota_netto' => 100000, 'is_template' => false]);
        $source = ['offer_id' => $offer->id];
    }
    $data = financialLineData(['kind' => $kind] + $source);
    unset($data['name'], $data['notes']);
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $this->plan), $data)->assertSessionHasNoErrors();
    $line = $this->plan->lines()->firstOrFail();
    expect($line->name)->toBe($expected)->and($line->notes)->toBeNull();
    $data['revision'] = 1;
    $data['name'] = 'Nazwa przesłana z ukrytego pola';
    $this->put(route('financial-plans.lines.update', [$this->plan, $line]), $data)->assertSessionHasNoErrors();
    expect($line->fresh()->name)->toBe($expected)->and($line->fresh()->notes)->toBeNull();
})->with(['person', 'project', 'offer']);

test('manual lines need a name while descriptions can be omitted or cleared', function () {
    $data = financialLineData();
    unset($data['notes']);
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $this->plan), $data)->assertSessionHasNoErrors();
    $line = $this->plan->lines()->firstOrFail();
    expect($line->notes)->toBeNull();
    $this->put(route('financial-plans.lines.update', [$this->plan, $line]), $data + ['notes' => 'Dodatkowy opis'])->assertConflict();
    $data['revision'] = 1;
    $this->put(route('financial-plans.lines.update', [$this->plan, $line]), $data + ['notes' => 'Dodatkowy opis'])->assertSessionHasNoErrors();
    $data['revision'] = 2;
    $this->put(route('financial-plans.lines.update', [$this->plan, $line]), $data)->assertSessionHasNoErrors();
    expect($line->fresh()->notes)->toBeNull();
    unset($data['name']);
    $data['revision'] = 3;
    $this->post(route('financial-plans.lines.store', $this->plan), $data)->assertSessionHasErrors('name');
    $form = $this->get(route('financial-plans.show', $this->plan))->assertOk()->getContent();
    expect($form)->toContain('Opis (opcjonalnie)')->not->toContain('Założenie / uzasadnienie zmiany');
    expect($form)->toMatch('/data-fp-kinds="revenue overhead"><span class="fp-field-title"><span>Nazwa pozycji/');
    expect($form)->not->toMatch('/<textarea[^>]*name="notes"[^>]*required/');
});

test('department roster does not grant salary access and operational full access cannot bypass it', function () {
    $employee = User::factory()->create();
    $employee->assignRole('admin');
    $employee->givePermissionTo('system.full_access');
    $this->department->members()->attach($employee);
    $this->actingAs($employee)->get(route('financial-plans.index'))->assertForbidden();
    $this->actingAs($employee)->get(route('financial-plans.show', $this->plan))->assertForbidden();
    $employee->givePermissionTo('financial_plans.view');
    $this->actingAs($employee)->get(route('financial-plans.show', $this->plan))->assertForbidden();
    $this->department->planners()->attach($employee);
    $this->actingAs($employee)->get(route('financial-plans.show', $this->plan))->assertOk();
    $this->actingAs($employee)->post(route('financial-plans.lines.store', $this->plan), financialLineData())->assertForbidden();
});

test('clients cannot access financial plans even with an accidental explicit grant', function () {
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->givePermissionTo(['financial_plans.view', 'financial_plans.manage']);
    $this->department->planners()->attach($client);
    $this->actingAs($client)->get(route('financial-plans.index'))->assertForbidden();
});

test('manager creates a department and opens a unique annual plan', function () {
    $this->actingAs($this->manager)->post(route('financial-plans.departments.store'), ['name' => 'Energetyka', 'member_ids' => [$this->manager->id]])->assertRedirect();
    $department = FinancialDepartment::where('name', 'Energetyka')->firstOrFail();
    expect($department->members()->count())->toBe(1);
    foreach (range(1, 2) as $attempt) {
        $this->post(route('financial-plans.store', $department), ['year' => 2028])->assertRedirect();
    }
    expect($department->plans()->count())->toBe(1);
    $this->get(route('financial-plans.index'))->assertOk()->assertSee('Energetyka');
});

test('salary and car costs are monthly encrypted and not exposed in general logs', function () {
    $person = User::factory()->create();
    $this->department->members()->attach($person);
    $data = financialLineData([
        'kind' => 'person', 'name' => 'Koszt zespołu', 'user_id' => $person->id,
        'salary_cost' => '12345.67', 'car_cost' => '1500.00', 'start_date' => '2027-03-01', 'end_date' => '2027-05-31',
        'actual_salary_cost' => '12300.00', 'actual_car_cost' => '1400.00',
    ]);
    unset($data['qualification'], $data['probability'], $data['budget_accuracy']);
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $this->plan), $data)->assertRedirect()->assertSessionHasNoErrors();
    $line = $this->plan->lines()->firstOrFail();
    expect($line->salary_cost)->toBe('12345.67');
    expect(DB::table('financial_plan_lines')->value('salary_cost'))->not->toContain('12345.67');
    expect(DB::table('financial_plan_changes')->value('payload'))->not->toContain('12345.67');
    expect(json_encode(ActivityLog::where('auditable_type', FinancialPlanLine::class)->pluck('changes')))->not->toContain('12345');
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan, $this->manager);
    expect(array_sum($result['forecast']['salary']))->toBe(3703701);
    expect(array_sum($result['forecast']['car']))->toBe(450000);
    expect(array_sum($result['forecast']['direct_cost']))->toBe(0);
    expect(array_sum($result['actual']['result']))->toBe(-4110000);
    $this->get(route('financial-plans.show', $this->plan))->assertOk()->assertSee('data-sort-value="12345.67"', false)->assertHeader('Cache-Control', 'no-store, private');
});

test('person periods cannot overlap even on different days of a month or across departments', function () {
    $person = User::factory()->create();
    $this->department->members()->attach($person);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'person', 'user_id' => $person->id, 'salary_cost' => 10000, 'start_date' => '2027-01-01', 'end_date' => '2027-06-30']);
    $other = FinancialDepartment::create(['name' => 'Drugi dział', 'owner_id' => $this->manager->id]);
    $other->members()->attach($person);
    $plan = $other->plans()->create(['year' => 2027]);
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $plan), financialLineData([
        'kind' => 'person', 'user_id' => $person->id, 'salary_cost' => 11000, 'start_date' => '2027-06-20',
    ]))->assertSessionHasErrors('user_id');
    $this->post(route('financial-plans.lines.store', $plan), financialLineData([
        'kind' => 'person', 'user_id' => $person->id, 'salary_cost' => 11000, 'start_date' => '2027-07-01',
    ]))->assertSessionHasNoErrors();
});

test('project contract invoices and purchased requirements are counted only once', function () {
    $project = financialProject($this->manager);
    ProjectFinancialEntry::create(['project_id' => $project->id, 'type' => 'invoice', 'name' => 'Faktura', 'amount' => 30000, 'status' => 'issued', 'entry_date' => '2027-02-01', 'payment_date' => '2027-03-01', 'created_by' => $this->manager->id]);
    ProjectRequirement::create(['project_id' => $project->id, 'type' => 'material', 'name' => 'Zakup', 'quantity' => 1, 'unit' => 'szt.', 'estimated_cost' => 10000, 'status' => 'purchased', 'created_by' => $this->manager->id]);
    ProjectFinancialEntry::where('project_id', $project->id)->where('type', 'cost')->update(['entry_date' => '2027-02-01', 'payment_date' => '2027-04-01']);
    ProjectRequirement::create(['project_id' => $project->id, 'type' => 'material', 'name' => 'Planowany zakup', 'quantity' => 1, 'unit' => 'szt.', 'estimated_cost' => 20000, 'status' => 'planned', 'needed_by' => '2027-05-01', 'created_by' => $this->manager->id]);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id, 'revenue_override' => null, 'cost_override' => null, 'risk_reserve' => 5000]);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan, $this->manager);
    expect(array_sum($result['forecast']['contracted']))->toBe(3000000);
    expect(array_sum($result['forecast']['direct_cost']))->toBe(3000000);
    expect(array_sum($result['actual']['direct_cost']))->toBe(1000000);
    expect($result['cash']['in'][3])->toBe(3000000);
    expect($result['cash']['out'][4])->toBe(1000000);
    $line = $this->plan->lines()->first();
    $line->update(['cost_override' => 12000]);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager);
    expect(array_sum($result['forecast']['direct_cost']))->toBe(3000000);
});

test('offers use scenarios exclude lost deals and are replaced by linked projects', function () {
    $company = Company::create(['name' => 'Klient', 'type' => 'client']);
    $offer = Offer::create(['offer_number' => 'FP-OF-1', 'offer_title' => 'Oferta', 'status' => 'w_toku', 'company_id' => $company->id, 'created_by_id' => $this->manager->id, 'kwota_netto' => 100000, 'is_template' => false]);
    $line = addFinancialLine($this->plan, $this->manager, ['kind' => 'offer', 'offer_id' => $offer->id, 'probability' => 40, 'revenue_override' => null, 'cost_override' => 30000]);
    $calc = app(FinancialPlanCalculator::class);
    expect(array_sum($calc->calculate($this->plan, $this->manager, 'base')['forecast']['pipeline']))->toBe(4000000);
    expect(array_sum($calc->calculate($this->plan, $this->manager, 'cautious')['forecast']['pipeline']))->toBe(0);
    expect(array_sum($calc->calculate($this->plan, $this->manager, 'optimistic')['forecast']['pipeline']))->toBe(10000000);
    $line->update(['qualification' => 'reserve']);
    expect(array_sum($calc->calculate($this->plan->fresh(), $this->manager)['forecast']['pipeline']))->toBe(0);
    $line->update(['qualification' => 'confirmed']);
    $offer->update(['status' => 'przegrana']);
    expect(array_sum($calc->calculate($this->plan->fresh(), $this->manager, 'optimistic')['forecast']['pipeline']))->toBe(0);
    $offer->update(['status' => 'wygrana']);
    $project = financialProject($this->manager);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'offer_id' => $offer->id, 'project_id' => $project->id]);
    expect(array_sum($calc->calculate($this->plan->fresh(), $this->manager)['forecast']['pipeline']))->toBe(0);
});

test('one CRM opportunity cannot be counted as multiple offer versions', function () {
    $company = Company::create(['name' => 'Klient CRM', 'type' => 'client']);
    $opportunity = CrmOpportunity::create(['title' => 'Jedna szansa', 'company_id' => $company->id, 'created_by' => $this->manager->id, 'stage' => 'offer']);
    $offers = collect([1, 2])->map(fn ($index) => Offer::create(['offer_number' => 'FP-V'.$index, 'status' => 'w_toku', 'company_id' => $company->id, 'created_by_id' => $this->manager->id, 'crm_opportunity_id' => $opportunity->id, 'kwota_netto' => 100000, 'is_template' => false]));
    addFinancialLine($this->plan, $this->manager, ['kind' => 'offer', 'offer_id' => $offers[0]->id]);
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $this->plan), financialLineData(['kind' => 'offer', 'offer_id' => $offers[1]->id]))->assertSessionHasErrors('offer_id');
});

test('annual totals allocate across years without losing rounding cents', function () {
    addFinancialLine($this->plan, $this->manager, ['start_date' => '2026-12-01', 'end_date' => '2027-02-28', 'revenue_override' => '100.00', 'cost_override' => 0]);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan, $this->manager);
    expect($result['forecast']['manual_revenue'][1])->toBe(3333);
    expect($result['forecast']['manual_revenue'][2])->toBe(3333);
    expect(array_sum($result['forecast']['manual_revenue']))->toBe(6666);
});

test('live planning has no approval step and still rejects stale writes', function () {
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $this->plan), financialLineData())->assertSessionHasNoErrors();
    $line = $this->plan->lines()->first();
    $this->put(route('financial-plans.lines.update', [$this->plan, $line]), financialLineData(['revision' => 1, 'revenue_override' => 240000]))->assertSessionHasNoErrors();
    expect(array_sum(app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager)['forecast']['manual_revenue']))->toBe(24000000);
    $this->put(route('financial-plans.lines.update', [$this->plan, $line]), financialLineData(['revision' => 1]))->assertConflict();
    $this->post('/financial-plans/'.$this->plan->id.'/approve', ['revision' => 2])->assertNotFound();
    $this->get(route('financial-plans.show', $this->plan))->assertOk()->assertDontSee('Zatwierdź')->assertDontSee('odchylenie prognozy');
});

test('missing source costs remain visible as warnings without blocking live planning', function () {
    $project = financialProject($this->manager);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id, 'revenue_override' => null, 'cost_override' => null]);
    $this->actingAs($this->manager)->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->assertSee('Koszt projektu nieoszacowany');
    $project->requirements()->create(['type' => 'service', 'name' => 'Brak kosztu dostawy', 'quantity' => 1, 'estimated_cost' => 0, 'status' => 'planned', 'needed_by' => '2027-01-15', 'created_by' => $this->manager->id]);
    $this->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->assertDontSee('Koszt projektu nieoszacowany');
});

test('source permission boundaries apply to selection totals snapshots and writes', function () {
    $project = financialProject($this->manager, ['name' => 'Poufny projekt']);
    $line = addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $employee = User::factory()->create();
    $employee->assignRole('admin');
    $employee->forceFill(['individual_permissions' => true])->save();
    $employee->givePermissionTo(['financial_plans.view', 'financial_plans.manage']);
    $this->department->planners()->attach($employee);
    $response = $this->actingAs($employee)->get(route('financial-plans.show', $this->plan));
    $response->assertOk()->assertDontSee('Poufny projekt')->assertSee('Sumy są częściowe');
    $this->post(route('financial-plans.lines.store', $this->plan), financialLineData(['kind' => 'project', 'project_id' => $project->id]))->assertForbidden();
    $this->put(route('financial-plans.lines.update', [$this->plan, $line]), financialLineData())->assertForbidden();
});

test('nested line identifiers cannot modify another department plan', function () {
    $other = $this->department->plans()->create(['year' => 2028]);
    $line = addFinancialLine($other, $this->manager);
    $this->actingAs($this->manager)->put(route('financial-plans.lines.update', [$this->plan, $line]), financialLineData())->assertNotFound();
    $this->delete(route('financial-plans.lines.destroy', [$this->plan, $line]), ['revision' => 0, 'reason' => 'Test'])->assertNotFound();
});

test('disabled financial planning module denies access and hides navigation', function () {
    CompanySettings::create(['name' => 'Firma', 'enabled_modules' => ['projects']]);
    $this->actingAs($this->manager)->get(route('financial-plans.index'))->assertForbidden();
});

test('project planned invoice and purchase dates drive the automatic monthly forecast', function () {
    $project = financialProject($this->manager);
    ProjectFinancialEntry::create(['project_id' => $project->id, 'type' => 'invoice', 'name' => 'Etap 1', 'amount' => 120000, 'status' => 'planned', 'entry_date' => '2027-09-10', 'created_by' => $this->manager->id]);
    ProjectFinancialEntry::create(['project_id' => $project->id, 'type' => 'cost', 'name' => 'Usługa', 'amount' => 10000, 'status' => 'planned', 'entry_date' => '2027-03-10', 'created_by' => $this->manager->id]);
    ProjectRequirement::create(['project_id' => $project->id, 'type' => 'material', 'name' => 'Dostawa', 'quantity' => 1, 'estimated_cost' => 20000, 'status' => 'planned', 'needed_by' => '2027-04-15', 'created_by' => $this->manager->id]);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id, 'revenue_override' => null, 'cost_override' => null]);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan, $this->manager);
    expect($result['forecast']['contracted'][9])->toBe(12000000);
    expect($result['forecast']['contracted'][1])->toBe(0);
    expect($result['forecast']['direct_cost'][3])->toBe(1000000);
    expect($result['forecast']['direct_cost'][4])->toBe(2000000);
    expect(array_sum($result['actual']['revenue']))->toBe(0);
});

test('approved sources remain protected after their forecast line is removed', function () {
    $project = financialProject($this->manager);
    $line = addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id, 'name' => 'Zastrzeżona historia']);
    $project->requirements()->create(['type' => 'service', 'name' => 'Wycena', 'quantity' => 1, 'estimated_cost' => 0, 'status' => 'planned', 'needed_by' => '2027-01-15', 'created_by' => $this->manager->id]);
    $this->plan->update(['approved_snapshot' => ['rows' => [], 'project_ids' => [$project->id], 'offer_ids' => []], 'approved_at' => now()]);
    $this->plan->changes()->create(['action' => 'approved', 'user_id' => $this->manager->id, 'payload' => ['snapshot' => ['Poufna historyczna kwota' => 123456]]]);
    $this->actingAs($this->manager);
    $this->delete(route('financial-plans.lines.destroy', [$this->plan, $line]), ['revision' => 0, 'reason' => 'Usunięcie poufnego źródła'])->assertSessionHasNoErrors();
    $viewer = User::factory()->create(['individual_permissions' => true]);
    $viewer->assignRole('admin');
    $viewer->givePermissionTo('financial_plans.view');
    $this->department->planners()->attach($viewer);
    $this->actingAs($viewer)->get(route('financial-plans.show', $this->plan))->assertOk()
        ->assertDontSee('Zastrzeżona historia')->assertDontSee('Usunięcie poufnego źródła')
        ->assertDontSee('Poufna historyczna kwota');
});

test('selected department members have missing salary estimates indicated in the live plan', function () {
    $this->department->members()->attach($this->manager);
    addFinancialLine($this->plan, $this->manager);
    $this->actingAs($this->manager)->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->assertSee('koszt osoby nieoszacowany');
});

test('manual overrides remain stable when an offer source value changes', function () {
    $company = Company::create(['name' => 'Klient aktualizacji', 'type' => 'client']);
    $offer = Offer::create(['offer_number' => 'FP-AUTO', 'status' => 'w_toku', 'company_id' => $company->id, 'created_by_id' => $this->manager->id, 'kwota_netto' => 100000, 'is_template' => false]);
    $line = addFinancialLine($this->plan, $this->manager, ['kind' => 'offer', 'offer_id' => $offer->id, 'qualification' => 'confirmed', 'revenue_override' => null]);
    $offer->update(['kwota_netto' => 200000]);
    $calc = app(FinancialPlanCalculator::class);
    expect(array_sum($calc->calculate($this->plan->fresh(), $this->manager)['forecast']['pipeline']))->toBe(20000000);
    $line->update(['revenue_override' => 150000]);
    $offer->update(['kwota_netto' => 300000]);
    expect(array_sum($calc->calculate($this->plan->fresh(), $this->manager)['forecast']['pipeline']))->toBe(15000000);
});

test('financial installation resumes partial MySQL style DDL without dropping existing rows', function () {
    $migration = require database_path('migrations/2026_10_27_120000_create_department_financial_plans.php');
    $migration->down();
    Schema::create('financial_departments', function (Blueprint $table) {
        $table->id();
        $table->string('name', 150);
        $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
        $table->timestamps();
    });
    Schema::create('financial_department_members', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('financial_department_id');
        $table->unsignedBigInteger('user_id');
    });
    DB::table('financial_departments')->insert(['id' => 123, 'name' => 'Zachowany dział', 'owner_id' => $this->manager->id]);
    DB::table('financial_department_members')->insert(['financial_department_id' => 123, 'user_id' => $this->manager->id]);
    $migration->up();
    $migration->up();
    expect(DB::table('financial_departments')->where('id', 123)->value('name'))->toBe('Zachowany dział');
    expect(DB::table('financial_department_members')->count())->toBe(1);
    foreach (['financial_department_members', 'financial_department_access'] as $table) {
        $index = $table === 'financial_department_members' ? 'finance_department_member_unique' : 'finance_department_access_unique';
        expect(Schema::hasIndex($table, $index))->toBeTrue();
        expect(Schema::getForeignKeys($table))->toHaveCount(2);
    }
    foreach (['financial_departments', 'financial_department_members', 'financial_department_access', 'financial_plans', 'financial_plan_lines', 'financial_plan_changes'] as $table) {
        foreach (Schema::getIndexes($table) as $index) {
            expect(strlen($index['name']))->toBeLessThanOrEqual(64);
        }
    }
});

test('plans accept reusable month ranges spanning years and display only selected months', function () {
    $this->actingAs($this->manager);
    foreach (range(1, 2) as $attempt) {
        $this->post(route('financial-plans.store', $this->department), ['start_month' => '2027-11', 'end_month' => '2028-02'])->assertSessionHasNoErrors();
    }
    $plan = $this->department->plans()->where('year', 2027)->whereNotNull('start_date')->firstOrFail();
    expect($this->department->plans()->count())->toBe(2);
    expect($plan->periodLabel())->toBe('11.2027 – 02.2028');
    addFinancialLine($plan, $this->manager, ['start_date' => '2027-11-01', 'end_date' => '2028-02-29', 'revenue_override' => '100.01', 'cost_override' => 0]);
    $result = app(FinancialPlanCalculator::class)->calculate($plan, $this->manager);
    expect($result['forecast']['manual_revenue'])->toBe([1 => 2501, 2 => 2500, 3 => 2500, 4 => 2500]);
    $this->get(route('financial-plans.show', $plan))->assertOk()->assertSee('11.2027 – 02.2028')->assertSee('<th>01.2028</th>', false)
        ->assertDontSee('<th>03.2028</th>', false)->assertSee('Razem w okresie')->assertDontSee('Plan roczny');
    $this->post(route('financial-plans.store', $this->department), ['start_month' => '2028-03', 'end_month' => '2028-01'])->assertSessionHasErrors('end_month');
    $this->post(route('financial-plans.store', $this->department), ['start_month' => '2028-03'])->assertSessionHasErrors('end_month');
});

test('period changes keep existing lines and enforce revision and department access', function () {
    addFinancialLine($this->plan, $this->manager);
    $url = route('financial-plans.period.update', $this->plan);
    $this->actingAs($this->manager)->put($url, ['start_month' => '2027-03', 'end_month' => '2027-05', 'revision' => 0])->assertSessionHasNoErrors();
    expect($this->plan->fresh()->periodLabel())->toBe('03.2027 – 05.2027');
    expect($this->plan->lines()->count())->toBe(1);
    $this->put($url, ['start_month' => '2027-04', 'end_month' => '2027-06', 'revision' => 0])->assertConflict();
    $this->plan->update(['approved_at' => now()]);
    $this->put($url, ['start_month' => '2027-04', 'end_month' => '2027-06', 'revision' => 1])->assertSessionHasNoErrors();
    $viewer = User::factory()->create(['individual_permissions' => true]);
    $viewer->assignRole('admin');
    $viewer->givePermissionTo('financial_plans.view');
    $this->department->planners()->attach($viewer);
    $this->actingAs($viewer)->put($url, [])->assertForbidden();
});

test('project needs no line dates and uses source schedules without spreading contract or overrides', function () {
    $this->plan->update(['start_date' => '2027-11-01', 'end_date' => '2028-02-29']);
    $project = financialProject($this->manager);
    $project->financialEntries()->create(['type' => 'invoice', 'name' => 'Etap', 'amount' => 12000, 'status' => 'planned', 'entry_date' => '2028-01-10', 'created_by' => $this->manager->id]);
    $delivery = $project->requirements()->create(['type' => 'material', 'name' => 'Dostawa', 'quantity' => 1, 'estimated_cost' => 2000, 'status' => 'ordered', 'needed_by' => '2027-12-15', 'created_by' => $this->manager->id]);
    $data = financialLineData(['kind' => 'project', 'project_id' => $project->id, 'cost_override' => 999999]);
    unset($data['start_date'], $data['end_date']);
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $this->plan), $data)->assertSessionHasNoErrors();
    $line = $this->plan->lines()->firstOrFail();
    expect($line->cost_override)->toBeNull()->and($line->revenue_override)->toBeNull();
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager);
    expect($result['forecast']['contracted'])->toBe([1 => 0, 2 => 0, 3 => 1200000, 4 => 0]);
    expect($result['forecast']['direct_cost'])->toBe([1 => 0, 2 => 200000, 3 => 0, 4 => 0]);
    $delivery->update(['estimated_cost' => 2500]);
    expect(array_sum(app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager)['forecast']['direct_cost']))->toBe(250000);
    $delivery->update(['status' => 'purchased']);
    $delivery->financialEntry->update(['entry_date' => '2028-02-01']);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager);
    expect($result['forecast']['direct_cost'])->toBe([1 => 0, 2 => 0, 3 => 0, 4 => 250000]);
    expect(array_sum($result['actual']['direct_cost']))->toBe(250000);
});

test('missing invoice due dates assume thirty days in budget only across year and leap day boundaries', function () {
    $this->plan->update(['start_date' => '2027-12-01', 'end_date' => '2028-03-31']);
    $project = financialProject($this->manager);
    foreach ([['invoice', 'planned', '2027-12-15', null, 100], ['cost', 'issued', '2028-01-31', null, 200], ['invoice', 'issued', '2028-02-28', '2028-02-29', 300], ['invoice', 'paid', '2028-03-20', null, 400]] as [$type, $status, $date, $due, $amount]) {
        $project->financialEntries()->create(['type' => $type, 'status' => $status, 'entry_date' => $date, 'payment_date' => $due, 'amount' => $amount, 'name' => 'Faktura', 'created_by' => $this->manager->id]);
    }
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager);
    expect($result['cash']['in'])->toBe([1 => 0, 2 => 10000, 3 => 30000, 4 => 0]);
    expect($result['cash']['out'])->toBe([1 => 0, 2 => 0, 3 => 0, 4 => 20000]);
    expect($project->financialEntries()->whereNull('payment_date')->count())->toBe(3);
    expect(implode(' ', $result['warnings']))->not->toContain('Brak terminu płatności');
});

test('overlapping periods protect source allocation across departments but allow alternative plans within one department', function () {
    $project = financialProject($this->manager);
    $this->plan->update(['start_date' => '2027-11-01', 'end_date' => '2028-02-29']);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $other = FinancialDepartment::create(['name' => 'Inny dział', 'owner_id' => $this->manager->id]);
    $overlapping = $other->plans()->create(['year' => 2028, 'start_date' => '2028-01-01', 'end_date' => '2028-04-30']);
    $data = financialLineData(['kind' => 'project', 'project_id' => $project->id]);
    unset($data['start_date'], $data['end_date']);
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $overlapping), $data)->assertSessionHasErrors('project_id');
    $alternative = $this->department->plans()->create(['year' => 2028, 'start_date' => '2028-01-01', 'end_date' => '2028-04-30']);
    $this->post(route('financial-plans.lines.store', $alternative), $data)->assertSessionHasNoErrors();
    $later = $other->plans()->create(['year' => 2028, 'start_date' => '2028-05-01', 'end_date' => '2028-06-30']);
    $this->post(route('financial-plans.lines.store', $later), $data)->assertSessionHasNoErrors();
});

test('period migration preserves old annual snapshots and can resume safely', function () {
    $snapshot = ['rows' => ['result' => array_fill(1, 12, 12345)], 'project_ids' => [], 'offer_ids' => []];
    $this->plan->update(['approved_snapshot' => $snapshot, 'approved_at' => now()]);
    $encrypted = DB::table('financial_plans')->where('id', $this->plan->id)->value('approved_snapshot');
    $migration = require database_path('migrations/2026_10_28_000000_add_month_period_to_financial_plans.php');
    $migration->up();
    $migration->up();
    expect($this->plan->fresh()->periodLabel())->toBe('01.2027 – 12.2027');
    expect($this->plan->fresh()->approved_snapshot)->toBe($snapshot);
    expect(DB::table('financial_plans')->where('id', $this->plan->id)->value('approved_snapshot'))->toBe($encrypted);
    expect(Schema::hasIndex('financial_plans', 'finance_plan_period_unique'))->toBeTrue();
    expect(Schema::hasIndex('financial_plans', 'financial_plans_financial_department_id_year_unique'))->toBeFalse();
});

test('undated deliveries are flagged instead of spread and cancelled deliveries are excluded', function () {
    $project = financialProject($this->manager);
    foreach ([['planned', null, 1000], ['cancelled', '2027-04-01', 5000]] as [$status, $date, $amount]) {
        $project->requirements()->create(['type' => 'material', 'name' => 'Dostawa', 'quantity' => 1, 'estimated_cost' => $amount, 'status' => $status, 'needed_by' => $date, 'created_by' => $this->manager->id]);
    }
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan, $this->manager);
    expect(array_sum($result['forecast']['direct_cost']))->toBe(0);
    expect(implode(' ', $result['warnings']))->toContain('Brak terminu dostawy');
});

function financialLiveXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($document);
}

test('live calculation reflects changed project amounts dates statuses and source names without changing plan revision', function () {
    $this->plan->update(['start_date' => '2027-01-01', 'end_date' => '2027-03-31']);
    $project = financialProject($this->manager, ['name' => 'Źródło dynamiczne']);
    $invoice = $project->financialEntries()->create(['type' => 'invoice', 'name' => 'Etap pierwszy', 'amount' => 500, 'status' => 'planned', 'entry_date' => '2027-01-10', 'created_by' => $this->manager->id]);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $url = route('financial-plans.calculation', $this->plan);
    $first = $this->actingAs($this->manager)->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
    $xpath = financialLiveXPath($first['html']);
    $query = '//table[@data-fp-table="forecast"]/tbody/tr[td[@data-sort-value="Przychody z projektów"]]';
    expect($xpath->query($query.'/td[2]')->item(0)->getAttribute('data-sort-value'))->toBe('500');
    $this->withHeader('X-Plan-Fingerprint', $first['fingerprint'])->getJson($url)->assertOk()->assertJsonPath('html', null);
    $invoice->update(['amount' => 700, 'entry_date' => '2027-02-10', 'status' => 'issued']);
    $project->update(['name' => 'Zmieniona nazwa projektu']);
    $second = $this->getJson($url)->assertOk()->json();
    expect($second['fingerprint'])->not->toBe($first['fingerprint']);
    expect($second['revision'])->toBe(0);
    $xpath = financialLiveXPath($second['html']);
    expect($xpath->query($query.'/td[2]')->item(0)->getAttribute('data-sort-value'))->toBe('0');
    expect($xpath->query($query.'/td[3]')->item(0)->getAttribute('data-sort-value'))->toBe('700');
    expect($second['html'])->toContain('Zmieniona nazwa projektu: 700,00 zł')->toContain('Etap pierwszy: 700,00 zł');
    expect($xpath->query('//table[@data-fp-table="actual"]')->length)->toBe(0);
    $cash = '//table[@data-fp-table="cash"]/tbody/tr[td[@data-sort-value="Wpływy · projekty i oferty"]]/td[4]';
    expect($xpath->query($cash)->item(0)->getAttribute('data-sort-value'))->toBe('700');
});

test('cost charts sum monthly department components and cumulative values excluding direct project costs', function () {
    $this->plan->update(['start_date' => '2027-01-01', 'end_date' => '2027-03-31']);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'person', 'salary_cost' => 1000, 'car_cost' => 100, 'start_date' => '2027-01-01', 'end_date' => '2027-01-31']);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'person', 'salary_cost' => 2000, 'car_cost' => 0, 'start_date' => '2027-02-01', 'end_date' => '2027-03-31']);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'overhead', 'cost_override' => 50]);
    $project = financialProject($this->manager);
    $project->financialEntries()->create(['type' => 'cost', 'name' => 'Koszt projektu', 'amount' => 9000, 'status' => 'planned', 'entry_date' => '2027-01-10', 'created_by' => $this->manager->id]);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $html = $this->actingAs($this->manager)->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->json('html');
    $xpath = financialLiveXPath($html);
    expect($xpath->query('//section[contains(@class,"fp-cost-chart")]//svg[@role="img"]')->length)->toBe(2);
    $monthly = [];
    $cumulative = [];
    foreach ($xpath->query('//table[@data-fp-table="chart-values"]/tbody/tr') as $row) {
        $cells = $row->getElementsByTagName('td');
        $monthly[] = $cells->item(4)->getAttribute('data-sort-value');
        $cumulative[] = $cells->item(5)->getAttribute('data-sort-value');
    }
    expect($monthly)->toBe(['1150', '2050', '2050']);
    expect($cumulative)->toBe(['1150', '3200', '5250']);
    expect($xpath->query('//table[@data-fp-table="forecast"]/ancestor::section/following-sibling::*[1][contains(@class,"fp-cost-chart")]')->length)->toBe(1);
});

test('live source warnings use compact escaped popovers with unique accessible identifiers', function () {
    $project = financialProject($this->manager);
    $line = addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id, 'notes' => '<script>danger()</script> '.str_repeat('Długi opis ', 100)]);
    $html = $this->actingAs($this->manager)->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->json('html');
    $xpath = financialLiveXPath($html);
    $badge = $xpath->query('//button[@data-fp-tip="fp-line-notes-'.$line->id.'"]')->item(0);
    expect($badge->textContent)->toStartWith('Uw. (');
    expect($xpath->query('//*[@id="fp-line-notes-'.$line->id.'"][@popover="manual"][@role="tooltip"]')->length)->toBe(1);
    expect($xpath->query('//script')->length)->toBe(0);
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $element) {
        $ids[] = $element->getAttribute('id');
    }
    expect($ids)->toHaveCount(count(array_unique($ids)));
    foreach ($xpath->query('//*[@data-fp-tip]') as $trigger) {
        expect($xpath->query('//*[@id="'.$trigger->getAttribute('aria-describedby').'"]')->length)->toBe(1);
    }
});

test('live endpoint rechecks project and department access on every request', function () {
    $project = financialProject($this->manager, ['name' => 'Poufna nazwa źródła']);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $viewer = User::factory()->create(['individual_permissions' => true]);
    $viewer->assignRole('admin');
    $viewer->givePermissionTo(['financial_plans.view', 'projects.view', 'projects.finances.view', 'projects.requirements.material_prices.view', 'projects.requirements.service_prices.view']);
    $this->department->planners()->attach($viewer);
    $project->members()->attach($viewer);
    $url = route('financial-plans.calculation', $this->plan);
    $visible = $this->actingAs($viewer)->getJson($url)->assertOk()->assertJsonPath('complete', true)->json('html');
    expect($visible)->toContain('Poufna nazwa źródła');
    $project->members()->detach($viewer);
    $hidden = $this->getJson($url)->assertOk()->assertJsonPath('complete', false)->json('html');
    expect($hidden)->not->toContain('Poufna nazwa źródła');
    $this->department->planners()->detach($viewer);
    $this->getJson($url)->assertForbidden();
    $this->actingAs($this->manager)->getJson($url.'?scenario=invalid')->assertUnprocessable();
});

test('profit and loss chart accumulates gains and losses with a signed net result', function () {
    $this->plan->update(['start_date' => '2027-01-01', 'end_date' => '2027-03-31']);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'person', 'salary_cost' => 100, 'car_cost' => 0]);
    $project = financialProject($this->manager);
    foreach ([['2027-01-10', 50], ['2027-02-10', 400]] as [$date, $amount]) {
        $project->financialEntries()->create(['type' => 'invoice', 'name' => 'Etap', 'amount' => $amount, 'status' => 'planned', 'entry_date' => $date, 'created_by' => $this->manager->id]);
    }
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $html = $this->actingAs($this->manager)->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->json('html');
    $xpath = financialLiveXPath($html);
    $values = [];
    foreach ($xpath->query('//table[@data-fp-table="fp-result-chart-values"]/tbody/tr') as $row) {
        $values[] = collect(iterator_to_array($row->getElementsByTagName('td')))->slice(1)->map(fn ($cell) => $cell->getAttribute('data-sort-value'))->values()->all();
    }
    expect($values)->toBe([['-50', '0', '-50', '-50', '-50', '0'], ['300', '300', '-50', '250', '250', '0'], ['-100', '300', '-150', '150', '150', '0']]);
    foreach (['chart-cost-monthly', 'chart-cost-cumulative', 'fp-result-chart'] as $key) {
        expect($xpath->query('//details[@data-fp-details="'.$key.'"]/summary')->length)->toBe(1);
    }
    foreach ($xpath->query('//svg[@aria-labelledby="fp-result-chart-title fp-result-chart-desc"]/polyline') as $line) {
        foreach (explode(' ', $line->getAttribute('points')) as $point) {
            [$x, $y] = array_map('floatval', explode(',', $point));
            expect(is_finite($x) && is_finite($y) && $y >= 40 && $y <= 220)->toBeTrue();
        }
    }
});

test('cash account uses opening balance gross settled amounts actual dates and department payments only once', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-15 12:00:00', 'Europe/Warsaw'));
    $this->manager->forceFill(['security_activity_at' => now()])->saveQuietly();
    $this->plan->update(['start_date' => '2027-01-01', 'end_date' => '2027-03-31', 'opening_balance' => '1000.00']);
    $project = financialProject($this->manager);
    foreach ([['invoice', 'paid', 100, '2027-02-20', 123], ['cost', 'paid', 50, '2027-03-02', 61.5], ['invoice', 'issued', 500, '2027-02-22', 615], ['invoice', 'paid', 100, '2027-03-30', 123]] as [$type, $status, $net, $date, $gross]) {
        $project->financialEntries()->create(['type' => $type, 'name' => 'Faktura', 'amount' => $net, 'entry_date' => '2027-01-10', 'payment_date' => '2027-01-31', 'status' => $status, 'settled_on' => $date, 'settled_amount' => $gross, 'created_by' => $this->manager->id]);
    }
    foreach ([['in', '2027-01-15', 200], ['out', '2027-01-31', 70]] as [$direction, $date, $amount]) {
        FinancialDepartmentPayment::create(['financial_department_id' => $this->department->id, 'direction' => $direction, 'paid_on' => $date, 'amount' => $amount, 'description' => 'Przelew działu', 'created_by' => $this->manager->id]);
    }
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $account = app(FinancialPlanCalculator::class)->calculate($this->plan, $this->manager)['account'];
    expect($account['complete'])->toBeTrue()->and($account['balance'])->toBe(119150);
    expect(array_column($account['months'], 'balance'))->toBe([113000, 125300, 119150]);
    expect($account['received'])->toBe(32300)->and($account['spent'])->toBe(13150);
    expect($account['transactions'])->toHaveCount(4);
    $html = $this->actingAs($this->manager)->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->json('html');
    $xpath = financialLiveXPath($html);
    expect($xpath->query('//svg[@aria-labelledby="fp-account-chart-title fp-account-chart-desc"]')->length)->toBe(0);
    $this->travelBack();
});

test('cash account never invents opening balance or missing payment facts', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-15 12:00:00', 'Europe/Warsaw'));
    $this->manager->forceFill(['security_activity_at' => now()])->saveQuietly();
    $this->plan->update(['start_date' => '2027-01-01', 'end_date' => '2027-03-31']);
    $project = financialProject($this->manager);
    $entry = $project->financialEntries()->create(['type' => 'invoice', 'name' => 'Brak danych przelewu', 'amount' => 100, 'status' => 'paid', 'entry_date' => '2027-01-01', 'created_by' => $this->manager->id]);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $calc = app(FinancialPlanCalculator::class);
    $account = $calc->calculate($this->plan, $this->manager)['account'];
    expect($account['balance'])->toBeNull()->and($account['transactions'])->toBeEmpty()->and($account['complete'])->toBeFalse();
    expect($account['warnings'])->toHaveCount(1);
    $this->plan->update(['opening_balance' => '0.00']);
    $entry->update(['settled_on' => '2027-02-10', 'settled_amount' => '123.00']);
    $account = $calc->calculate($this->plan->fresh(), $this->manager)['account'];
    expect($account['balance'])->toBe(12300)->and($account['complete'])->toBeTrue();
    $this->travelBack();
});

test('opening balance and manual payments are encrypted revision protected and department scoped', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-15 12:00:00', 'Europe/Warsaw'));
    $this->manager->forceFill(['security_activity_at' => now()])->saveQuietly();
    $this->plan->update(['start_date' => '2027-01-01', 'end_date' => '2027-03-31']);
    $this->actingAs($this->manager)->put(route('financial-plans.opening-balance.update', $this->plan), ['revision' => 0, 'opening_balance' => '-12345.67'])->assertSessionHasNoErrors();
    expect(DB::table('financial_plans')->where('id', $this->plan->id)->value('opening_balance'))->not->toContain('12345.67');
    $this->put(route('financial-plans.opening-balance.update', $this->plan), ['revision' => 0, 'opening_balance' => 0])->assertConflict();
    $payload = ['revision' => 1, 'direction' => 'out', 'paid_on' => '2027-02-28', 'amount' => '9876.54', 'description' => 'Wypłaty', 'created_by' => 999999];
    $this->post(route('financial-plans.payments.store', $this->plan), $payload)->assertSessionHasNoErrors();
    $payment = FinancialDepartmentPayment::firstOrFail();
    expect($payment->created_by)->toBe($this->manager->id);
    expect(DB::table('financial_department_payments')->value('amount'))->not->toContain('9876.54');
    $otherDepartment = FinancialDepartment::create(['name' => 'Inny dział konta', 'owner_id' => $this->manager->id]);
    $otherPlan = $otherDepartment->plans()->create(['year' => 2027]);
    $this->delete(route('financial-plans.payments.destroy', [$otherPlan, $payment]), ['revision' => 0])->assertNotFound();
    $this->post(route('financial-plans.payments.store', $this->plan), array_replace($payload, ['revision' => 2, 'paid_on' => '2027-03-30']))->assertSessionHasErrors('paid_on');
    $this->put(route('financial-plans.period.update', $this->plan), ['revision' => 2, 'start_month' => '2027-02', 'end_month' => '2027-03'])->assertSessionHasNoErrors();
    expect($this->plan->fresh()->opening_balance)->toBeNull();
    $this->delete(route('financial-plans.payments.destroy', [$this->plan, $payment]), ['revision' => 3])->assertSessionHasNoErrors();
    expect(FinancialDepartmentPayment::count())->toBe(0);
    $this->travelBack();
});

test('cash inputs are unavailable to read only planners and invisible project payments never leak', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-15 12:00:00', 'Europe/Warsaw'));
    $this->manager->forceFill(['security_activity_at' => now()])->saveQuietly();
    $this->plan->update(['opening_balance' => '0.00']);
    $project = financialProject($this->manager, ['name' => 'Poufne przepływy']);
    $project->financialEntries()->create(['type' => 'invoice', 'name' => 'Poufny przelew', 'amount' => 500, 'status' => 'paid', 'entry_date' => '2027-01-01', 'settled_on' => '2027-01-02', 'settled_amount' => '615.00', 'created_by' => $this->manager->id]);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id]);
    $viewer = User::factory()->create(['individual_permissions' => true]);
    $viewer->assignRole('admin');
    $viewer->givePermissionTo('financial_plans.view');
    $this->department->planners()->attach($viewer);
    $this->actingAs($viewer)->put(route('financial-plans.opening-balance.update', $this->plan), [])->assertForbidden();
    $this->post(route('financial-plans.payments.store', $this->plan), [])->assertForbidden();
    $html = $this->getJson(route('financial-plans.calculation', $this->plan))->assertOk()->assertJsonPath('complete', false)->json('html');
    expect($html)->not->toContain('Poufny przelew')->not->toContain('Poufne przepływy');
    $this->get(route('financial-plans.show', $this->plan))->assertOk()->assertDontSee('id="fp-account-form"', false);
    $this->travelBack();
});

test('department payments are shared across alternative periods while opening balance prevents recounting past cash', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-15 12:00:00', 'Europe/Warsaw'));
    $this->plan->update(['start_date' => '2027-02-01', 'end_date' => '2027-03-31', 'opening_balance' => '100.00']);
    FinancialDepartmentPayment::create(['financial_department_id' => $this->department->id, 'direction' => 'in', 'paid_on' => '2027-01-15', 'amount' => 500, 'description' => 'Przed okresem', 'created_by' => $this->manager->id]);
    FinancialDepartmentPayment::create(['financial_department_id' => $this->department->id, 'direction' => 'out', 'paid_on' => '2027-02-10', 'amount' => 30, 'description' => 'Koszt wspólny działu', 'created_by' => $this->manager->id]);
    $otherDepartment = FinancialDepartment::create(['name' => 'Obce płatności', 'owner_id' => $this->manager->id]);
    FinancialDepartmentPayment::create(['financial_department_id' => $otherDepartment->id, 'direction' => 'out', 'paid_on' => '2027-02-10', 'amount' => 999, 'description' => 'Inny dział', 'created_by' => $this->manager->id]);
    $alternative = $this->department->plans()->create(['year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-04-30', 'opening_balance' => '100.00']);
    $calc = app(FinancialPlanCalculator::class);
    foreach ([$this->plan, $alternative] as $plan) {
        $account = $calc->calculate($plan, $this->manager)['account'];
        expect($account['balance'])->toBe(7000)->and($account['transactions'])->toHaveCount(1);
    }
    $this->travelBack();
});
