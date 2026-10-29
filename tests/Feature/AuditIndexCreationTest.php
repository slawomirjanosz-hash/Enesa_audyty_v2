<?php

use App\Models\Audit;
use App\Models\AuditorCompanyAccess;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('audit index offers scoped creation and stores initial survey and contract value', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('auditor'));
    $user->givePermissionTo([Permission::findOrCreate('audits.view'), Permission::findOrCreate('audits.manage')]);
    $company = Company::create(['name' => 'Dostępny klient', 'company_type' => 'client', 'status' => 'active']);
    $other = Company::create(['name' => 'Niedostępny klient', 'company_type' => 'client', 'status' => 'active']);
    AuditorCompanyAccess::create(['auditor_id' => $user->id, 'company_id' => $company->id, 'can_view_audits' => true]);
    $type = AuditType::create(['name' => 'Audyt testowy', 'slug' => 'creation-test']);
    $this->actingAs($user)->get(route('audits.index'))->assertOk()->assertSee('Nowy audyt')->assertSee('Dostępny klient')->assertDontSee('Niedostępny klient');
    $data = ['company_id' => $company->id, 'audit_type_id' => $type->id, 'number' => 'NEW/1', 'title' => 'Nowy audyt testowy', 'manager_id' => $user->id, 'status' => 'draft', 'contract_value' => '1234.56'];
    $this->post(route('audits.store'), $data)->assertRedirect();
    $audit = Audit::where('number', 'NEW/1')->firstOrFail();
    expect($audit->surveys()->first()->audit_type_id)->toBe($type->id)
        ->and($audit->members->modelKeys())->toBe([$user->id]);
    foreach (['tiles', 'list'] as $view) {
        $this->get(route('audits.index', ['view' => $view]))->assertOk()->assertSee('1 234,56 zł');
    }
    $this->from(route('audits.index'))->post(route('audits.store'), $data)->assertSessionHasErrorsIn('auditCreate', 'number');
    $this->get(route('audits.index'))->assertOk()->assertSee('showModal()', false)->assertSee('NEW/1');
    $this->post(route('audits.store'), array_replace($data, ['company_id' => $other->id, 'number' => 'NEW/2']))->assertForbidden();
    $user->revokePermissionTo('audits.manage');
    $this->get(route('audits.index'))->assertOk()->assertDontSee('Nowy audyt</button>', false)->assertDontSee('id="audit-create-dialog"', false);
    $this->post(route('audits.store'), array_replace($data, ['number' => 'NEW/3']))->assertForbidden();
});
