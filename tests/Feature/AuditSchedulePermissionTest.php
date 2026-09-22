<?php

use App\Models\Audit;
use App\Models\AuditorCompanyAccess;
use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('delegated auditor can manage schedule without managing the audit and stays company scoped', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('auditor');
    $role->syncPermissions(['audits.view']);
    $user->assignRole($role);
    $company = Company::create(['name' => 'Firma harmonogramu']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'SCHED/1', 'title' => 'Audyt', 'status' => 'draft']);
    AuditorCompanyAccess::create(['auditor_id' => $user->id, 'company_id' => $company->id, 'can_view_audits' => true]);
    $payload = ['title' => 'Nowe zadanie', 'start_date' => '2026-10-01', 'due_date' => '2026-10-02', 'status' => 'todo', 'priority' => 'medium', 'progress' => 0];
    $this->actingAs($user)->get(route('audits.show', $audit))->assertOk()->assertDontSee('id="gantt-add-task"', false);
    $this->postJson(route('audits.tasks.store', $audit), $payload)->assertForbidden();

    $role->givePermissionTo('audits.schedule.manage');
    $user->unsetRelation('roles');
    $this->get(route('audits.show', $audit))->assertOk()
        ->assertSee('id="gantt-add-task"', false)->assertSee('id="gantt-task-modal"', false)
        ->assertSee('id="gantt-import-modal"', false)->assertDontSee('id="audit-edit-modal"', false);
    $this->postJson(route('audits.tasks.store', $audit), $payload)->assertCreated();
    $task = $audit->tasks()->firstOrFail();
    $this->patchJson(route('audits.tasks.update', [$audit, $task]), ['progress' => 50])->assertOk();
    $this->postJson(route('audits.tasks.reorder', $audit), ['order' => [$task->id]])->assertOk();
    $this->post(route('audits.gantt.import', $audit), [])->assertSessionHasErrors('file', null, 'ganttImport');
    $this->putJson(route('audits.update', $audit), [])->assertForbidden();
    $this->postJson(route('audits.finances.store', $audit), [])->assertForbidden();

    $foreignCompany = Company::create(['name' => 'Obca firma harmonogramu']);
    $foreignAudit = Audit::create(['company_id' => $foreignCompany->id, 'number' => 'SCHED/2', 'title' => 'Obcy audyt', 'status' => 'draft']);
    $this->postJson(route('audits.tasks.store', $foreignAudit), $payload)->assertForbidden();
    $this->patchJson(route('audits.tasks.update', [$foreignAudit, $task]), ['progress' => 0])->assertForbidden();
    $this->deleteJson(route('audits.tasks.destroy', [$audit, $task]))->assertOk();
    $this->postJson(route('audits.tasks.store', $audit), $payload)->assertCreated();
    $this->deleteJson(route('audits.tasks.bulk-destroy', $audit), ['task_ids' => $audit->tasks()->pluck('id')->all()])->assertOk();
    $role->revokePermissionTo('audits.schedule.manage');
    $user->unsetRelation('roles');
    $this->postJson(route('audits.tasks.store', $audit), $payload)->assertForbidden();
});

test('audit schedule permission is available in role settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('superadmin'));
    $this->actingAs($admin)->get(route('settings.roles.index'))->assertOk()
        ->assertSee('audits.schedule.manage')->assertSee('Zarządzanie harmonogramem i zadaniami audytu');
});
