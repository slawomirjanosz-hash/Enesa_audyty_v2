<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\CrmOpportunity;
use App\Models\FinancialDepartment;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanLine;
use App\Models\Offer;
use App\Models\Project;
use App\Models\ProjectFinancialEntry;
use App\Models\ProjectRequirement;
use App\Models\User;
use App\Services\FinancialPlanCalculator;
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
    ProjectRequirement::create(['project_id' => $project->id, 'type' => 'material', 'name' => 'Planowany zakup', 'quantity' => 1, 'unit' => 'szt.', 'estimated_cost' => 20000, 'status' => 'planned', 'created_by' => $this->manager->id]);
    addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id, 'revenue_override' => null, 'cost_override' => null, 'risk_reserve' => 5000]);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan, $this->manager);
    expect(array_sum($result['forecast']['contracted']))->toBe(12000000);
    expect(array_sum($result['forecast']['direct_cost']))->toBe(3500000);
    expect(array_sum($result['actual']['direct_cost']))->toBe(1000000);
    expect($result['cash']['in'][3])->toBe(3000000);
    expect($result['cash']['out'][4])->toBe(1000000);
    $line = $this->plan->lines()->first();
    $line->update(['cost_override' => 12000]);
    $result = app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager);
    expect(array_sum($result['forecast']['direct_cost']))->toBe(2700000);
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

test('approved baseline stays fixed while forecast changes and stale writes are rejected', function () {
    $this->actingAs($this->manager)->post(route('financial-plans.lines.store', $this->plan), financialLineData())->assertSessionHasNoErrors();
    $this->post(route('financial-plans.approve', $this->plan), ['revision' => 1])->assertSessionHasNoErrors();
    $snapshot = $this->plan->fresh()->approved_snapshot;
    $line = $this->plan->lines()->first();
    $this->put(route('financial-plans.lines.update', [$this->plan, $line]), financialLineData(['revision' => 2, 'revenue_override' => 240000]))->assertSessionHasNoErrors();
    expect($this->plan->fresh()->approved_snapshot)->toBe($snapshot);
    expect(array_sum(app(FinancialPlanCalculator::class)->calculate($this->plan->fresh(), $this->manager)['forecast']['manual_revenue']))->toBe(24000000);
    $this->put(route('financial-plans.lines.update', [$this->plan, $line]), financialLineData(['revision' => 2]))->assertConflict();
    $this->post(route('financial-plans.approve', $this->plan), ['revision' => 3])->assertConflict();
});

test('missing project cost is flagged and blocks approval until explicitly estimated', function () {
    $project = financialProject($this->manager);
    $line = addFinancialLine($this->plan, $this->manager, ['kind' => 'project', 'project_id' => $project->id, 'revenue_override' => null, 'cost_override' => null]);
    $this->actingAs($this->manager)->post(route('financial-plans.approve', $this->plan), ['revision' => 0])->assertSessionHasErrors('plan');
    $line->update(['cost_override' => 0]);
    $this->post(route('financial-plans.approve', $this->plan), ['revision' => 0])->assertSessionHasNoErrors();
    expect($this->plan->fresh()->approved_at)->not->toBeNull();
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
    $this->post(route('financial-plans.approve', $this->plan), ['revision' => 0])->assertSessionHasErrors('plan');
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
    $this->actingAs($this->manager)->post(route('financial-plans.approve', $this->plan), ['revision' => 0])->assertSessionHasNoErrors();
    $this->delete(route('financial-plans.lines.destroy', [$this->plan, $line]), ['revision' => 1, 'reason' => 'Usunięcie poufnego źródła'])->assertSessionHasNoErrors();
    $viewer = User::factory()->create(['individual_permissions' => true]);
    $viewer->assignRole('admin');
    $viewer->givePermissionTo('financial_plans.view');
    $this->department->planners()->attach($viewer);
    $this->actingAs($viewer)->get(route('financial-plans.show', $this->plan))->assertOk()
        ->assertDontSee('Zastrzeżona historia')->assertDontSee('Usunięcie poufnego źródła')
        ->assertSee('Podgląd zatwierdzonego planu wymaga dostępu do wszystkich jego źródeł.');
});

test('selected department members need a salary estimate before annual approval', function () {
    $this->department->members()->attach($this->manager);
    addFinancialLine($this->plan, $this->manager);
    $this->actingAs($this->manager)->post(route('financial-plans.approve', $this->plan), ['revision' => 0])->assertSessionHasErrors('plan');
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
