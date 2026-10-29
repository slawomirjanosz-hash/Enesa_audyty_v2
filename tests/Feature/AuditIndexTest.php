<?php

use App\Models\Audit;
use App\Models\AuditorCompanyAccess;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->viewer = User::factory()->create();
    $this->viewer->assignRole(Role::findOrCreate('employee'));
    $this->viewer->givePermissionTo(Permission::findOrCreate('audits.view'));
    $this->company = Company::create(['name' => 'Firma audytowana']);
});

test('audit index defaults to current audits and searches related companies', function () {
    foreach (['draft', 'in_progress', 'done', 'cancelled'] as $status) {
        Audit::create(['company_id' => $this->company->id, 'number' => $status, 'title' => 'Audyt '.$status, 'status' => $status]);
    }
    $deleted = Audit::create(['company_id' => $this->company->id, 'number' => 'deleted', 'title' => 'Usunięty', 'status' => 'draft']);
    $deleted->delete();
    $response = $this->actingAs($this->viewer)->get(route('audits.index'));
    $response->assertOk()->assertSee('Audyt draft')->assertSee('Audyt in_progress')->assertSee('Audyt done')->assertSee('Audyty zakończone')->assertDontSee('Usunięty');
    expect($response->viewData('audits')->total())->toBe(3);
    $this->get(route('audits.index', ['status' => 'done', 'q' => 'Firma audytowana']))->assertOk()->assertSee('Audyt done')->assertDontSee('Audyt draft');
    expect($this->get(route('audits.index', ['status' => 'all']))->viewData('audits')->total())->toBe(3);
    expect($this->get(route('audits.index', ['q' => 'Brak dopasowania']))->viewData('audits')->total())->toBe(0);
});

test('audit index restricts delegated auditors even when searching all statuses', function () {
    $this->viewer->assignRole(Role::findOrCreate('auditor'));
    $other = Company::create(['name' => 'Obca firma']);
    foreach ([$this->company, $other] as $company) {
        Audit::create(['company_id' => $company->id, 'number' => 'A'.$company->id, 'title' => $company->name, 'status' => 'draft']);
    }
    $this->actingAs($this->viewer)->get(route('audits.index'))->assertOk()->assertDontSee('Firma audytowana')->assertDontSee('Obca firma');
    AuditorCompanyAccess::create(['auditor_id' => $this->viewer->id, 'company_id' => $this->company->id, 'can_view_audits' => true]);
    $this->get(route('audits.index', ['status' => 'all', 'q' => 'firma']))->assertOk()->assertSee('Firma audytowana')->assertDontSee('Obca firma');
});

test('audit index requires permission and enabled module', function () {
    $this->viewer->revokePermissionTo('audits.view');
    $this->actingAs($this->viewer)->get(route('audits.index'))->assertForbidden();
    $this->viewer->givePermissionTo('audits.view');
    CompanySettings::create(['name' => 'ENESA', 'enabled_modules' => ['dashboard', 'projects']]);
    $this->get(route('audits.index'))->assertForbidden();
});

test('audit index paginates and retains search and status', function () {
    for ($i = 0; $i < 21; $i++) {
        Audit::create(['company_id' => $this->company->id, 'number' => 'LIST-'.$i, 'title' => 'Audyt '.$i, 'status' => 'done']);
    }
    $response = $this->actingAs($this->viewer)->get(route('audits.index', ['status' => 'done', 'q' => 'LIST']));
    $response->assertOk();
    $audits = $response->viewData('completedAudits');
    expect($audits->count())->toBe(20)->and($audits->total())->toBe(21)
        ->and($audits->nextPageUrl())->toContain('status=done', 'q=LIST');
});

test('clients cannot use the staff audit index', function () {
    $this->viewer->syncRoles([Role::findOrCreate('client_user')]);
    $this->actingAs($this->viewer)->get(route('audits.index'))->assertForbidden();
});
