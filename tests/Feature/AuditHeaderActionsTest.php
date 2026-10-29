<?php

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::findOrCreate('employee'));
    $this->user->givePermissionTo([Permission::findOrCreate('audits.view'), Permission::findOrCreate('audits.manage')]);
    $company = Company::create(['name' => 'Firma']);
    $this->audit = Audit::create(['company_id' => $company->id, 'number' => 'ORIGINAL', 'title' => 'Audyt testowy', 'status' => 'done', 'manager_id' => $this->user->id, 'public_gantt_token' => 'private-token']);
    $this->audit->members()->attach($this->user);
    $this->actingAs($this->user);
});

test('audit copy resets progress and creates empty surveys without duplicating private results', function () {
    $type = AuditType::create(['name' => 'Test', 'slug' => 'copy-test']);
    $this->audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'Ankieta', 'status' => 'completed', 'notes' => 'Poufne odpowiedzi']);
    $first = Task::create(['audit_id' => $this->audit->id, 'title' => 'Etap 1', 'status' => 'done', 'progress' => 100]);
    $first->forceFill(['completion_confirmations' => ['confirmed' => true]])->save();
    Task::create(['audit_id' => $this->audit->id, 'title' => 'Etap 2', 'status' => 'done', 'progress' => 100, 'depends_on_task_id' => $first->id]);
    $this->post(route('audits.copy', $this->audit), ['number' => 'COPY', 'title' => 'Kopia'])->assertRedirect();
    $copy = Audit::where('number', 'COPY')->firstOrFail();
    expect($copy->status)->toBe('draft')->and($copy->public_gantt_token)->toBeNull()
        ->and($copy->members->modelKeys())->toBe([$this->user->id])
        ->and($copy->surveys->first()->status)->toBe('draft')->and($copy->surveys->first()->notes)->toBeNull()
        ->and($copy->tasks->pluck('progress')->all())->toBe([0, 0])
        ->and($copy->tasks->first()->completion_confirmations)->toBeNull()
        ->and($copy->tasks->last()->depends_on_task_id)->toBe($copy->tasks->first()->id)
        ->and($this->audit->fresh()->status)->toBe('done')->and($first->fresh()->progress)->toBe(100);
    $this->post(route('audits.copy', $this->audit), ['number' => 'COPY', 'title' => 'Powtórzona'])->assertSessionHasErrorsIn('auditCopy', 'number');
    expect(Audit::count())->toBe(2);
});

test('header actions respect manage and administrator permissions', function () {
    $this->get(route('audits.show', $this->audit))->assertOk()->assertSee('Kopiuj audyt')->assertSee('Edytuj audyt')->assertDontSee('Usuń audyt');
    $this->user->revokePermissionTo('audits.manage');
    $this->get(route('audits.show', $this->audit))->assertOk()->assertDontSee('Kopiuj audyt')->assertDontSee('Edytuj audyt');
    $this->post(route('audits.copy', $this->audit), ['number' => 'DENIED', 'title' => 'Kopia'])->assertForbidden();
    $this->user->assignRole(Role::findOrCreate('superadmin'));
    $this->get(route('audits.show', $this->audit))->assertOk()->assertSee('Usuń audyt')->assertSee('name="confirm_delete"', false);
});

test('copying an audit cannot bypass delegated company access', function () {
    $this->user->assignRole(Role::findOrCreate('auditor'));
    $this->post(route('audits.copy', $this->audit), ['number' => 'DENIED', 'title' => 'Kopia'])->assertForbidden();
    expect(Audit::count())->toBe(1);
});
