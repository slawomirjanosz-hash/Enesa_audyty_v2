<?php

use App\Mail\HrLeaveApproved;
use App\Models\CompanySettings;
use App\Models\HrLeave;
use App\Models\HrLeaveApprovalTask;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function approvalEmployee(array $attributes = []): User
{
    $role = Role::findOrCreate('leave_employee');
    $role->givePermissionTo(Permission::findOrCreate('hr.leaves.view'));
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function approvalRequest(array $changes = []): array
{
    return $changes + ['type' => 'annual', 'document_date' => '2026-10-25', 'start_date' => '2026-11-02', 'days' => 3, 'notes' => 'Wniosek prywatny test'];
}

beforeEach(function () {
    CompanySettings::create(['name' => 'HR test', 'enabled_modules' => ['hr', 'dashboard']]);
    Mail::fake();
});

test('employee sees recipient and submits a pending request with an in progress task only for that supervisor', function () {
    $supervisor = approvalEmployee(['name' => 'Przełożony Jan']);
    $employee = approvalEmployee(['supervisor_id' => $supervisor->id]);
    $stranger = approvalEmployee();
    $this->actingAs($employee)->get(route('hr.index', ['tab' => 'leaves']))->assertOk()->assertSee('Przełożony Jan')->assertSee('leave-recipient');
    $this->post(route('hr.leaves.store'), approvalRequest(['user_id' => $stranger->id, 'approver_id' => $stranger->id, 'approval_status' => 'approved']))->assertSessionHasNoErrors();
    $leave = HrLeave::firstOrFail();
    $this->assertDatabaseMissing('activity_logs', ['auditable_type' => HrLeave::class]);
    expect($leave->user_id)->toBe($employee->id)->and($leave->approval_status)->toBe('pending')->and($leave->approver_id)->toBe($supervisor->id);
    $task = HrLeaveApprovalTask::firstOrFail();
    expect($task->status)->toBe('in_progress')->and($task->assigned_to)->toBe($supervisor->id)->and($task->archived_at)->toBeNull();
    $this->actingAs($supervisor)->get(route('board.mine'))->assertOk()->assertSee('data-leave-task="'.$task->id.'"', false)->assertSee('W trakcie wykonania');
    $this->get(route('hr.index', ['tab' => 'leaves', 'leave_scope' => 'team']))->assertOk()->assertSee('Akceptuj')->assertSee('Odrzuć');
    $this->actingAs($stranger)->get(route('board.mine'))->assertOk()->assertDontSee('data-leave-task=', false);
    $this->get(route('hr.leaves.pdf', $leave))->assertForbidden();
    Mail::assertNothingSent();
});

test('only the captured supervisor can decide and acceptance archives the task sends one mail and locks the request', function () {
    $supervisor = approvalEmployee();
    $employee = approvalEmployee(['supervisor_id' => $supervisor->id, 'has_employment_contract' => true]);
    $this->actingAs($employee)->post(route('hr.leaves.store'), approvalRequest());
    $leave = HrLeave::firstOrFail();
    expect($employee->annualLeaveUsedDays(2026))->toBe(0);
    $decision = ['decision' => 'approved', 'approval_revision' => 1];
    $this->post(route('hr.leaves.decide', $leave), $decision)->assertForbidden();
    $this->actingAs(approvalEmployee())->post(route('hr.leaves.decide', $leave), $decision)->assertForbidden();
    $this->actingAs($supervisor)->post(route('hr.leaves.decide', $leave), $decision)->assertSessionHas('success');
    expect($leave->fresh()->approval_status)->toBe('approved')->and(HrLeaveApprovalTask::first()->status)->toBe('done')->and(HrLeaveApprovalTask::first()->archived_at)->not->toBeNull();
    expect($employee->annualLeaveUsedDays(2026))->toBe(3);
    Mail::assertSent(HrLeaveApproved::class, fn ($mail) => $mail->hasTo($employee->email));
    $this->post(route('hr.leaves.decide', $leave), $decision)->assertStatus(409);
    Mail::assertSentCount(1);
    $this->get(route('board.mine'))->assertDontSee('data-leave-task=', false);
    $this->get(route('board.mine', ['archive' => 1]))->assertOk()->assertSee('data-leave-task=', false)->assertSee('Zaakceptowany');
    $this->actingAs($employee)->get(route('hr.index', ['tab' => 'leaves']))->assertOk()->assertSee('Zaakceptowany');
    $this->put(route('hr.leaves.update', $leave), approvalRequest(['approval_revision' => 2]))->assertForbidden();
    $this->delete(route('hr.leaves.destroy', $leave), ['approval_revision' => 2])->assertForbidden();
});

test('rejection is visible to the employee and resubmission invalidates stale decisions and reopens the task', function () {
    $supervisor = approvalEmployee();
    $employee = approvalEmployee(['supervisor_id' => $supervisor->id]);
    $this->actingAs($employee)->post(route('hr.leaves.store'), approvalRequest());
    $leave = HrLeave::firstOrFail();
    $this->actingAs($supervisor)->post(route('hr.leaves.decide', $leave), ['decision' => 'rejected', 'approval_revision' => 1, 'decision_note' => 'Inny termin'])->assertSessionHas('success');
    Mail::assertNothingSent();
    $this->actingAs($employee)->get(route('hr.index', ['tab' => 'leaves']))->assertOk()->assertSee('Odrzucony')->assertSee('Inny termin');
    $this->put(route('hr.leaves.update', $leave), approvalRequest(['approval_revision' => 2, 'start_date' => '2026-11-09']))->assertSessionHasNoErrors();
    expect($leave->fresh()->approval_status)->toBe('pending')->and($leave->fresh()->decision_note)->toBeNull()->and(HrLeaveApprovalTask::count())->toBe(1)->and(HrLeaveApprovalTask::first()->archived_at)->toBeNull();
    $this->actingAs($supervisor)->post(route('hr.leaves.decide', $leave), ['decision' => 'approved', 'approval_revision' => 1])->assertStatus(409);
});

test('accounting has a separate read only all requests permission and cannot decide or edit someone elses request', function () {
    $manager = approvalEmployee();
    $employee = approvalEmployee(['supervisor_id' => $manager->id]);
    $this->actingAs($employee)->post(route('hr.leaves.store'), approvalRequest());
    $leave = HrLeave::firstOrFail();
    $accountant = approvalEmployee();
    $accountant->givePermissionTo('hr.leaves.accounting');
    $this->actingAs($accountant)->get(route('hr.index', ['tab' => 'leaves', 'leave_scope' => 'all']))->assertOk()->assertSee('Wniosek prywatny test');
    $this->post(route('hr.leaves.decide', $leave), ['decision' => 'approved', 'approval_revision' => 1])->assertForbidden();
    $this->put(route('hr.leaves.update', $leave), approvalRequest(['approval_revision' => 1]))->assertForbidden();
    $this->actingAs(approvalEmployee())->get(route('hr.index', ['tab' => 'leaves', 'leave_scope' => 'all']))->assertForbidden();
});

test('a missing inactive or self supervisor prevents submitting a holiday request but L4 remains a separate record', function () {
    $employee = approvalEmployee();
    $this->actingAs($employee)->post(route('hr.leaves.store'), approvalRequest())->assertSessionHasErrorsIn('leave', 'supervisor');
    $employee->update(['supervisor_id' => $employee->id]);
    $this->post(route('hr.leaves.store'), approvalRequest())->assertSessionHasErrorsIn('leave', 'supervisor');
    $inactive = approvalEmployee(['is_active' => false]);
    $employee->update(['supervisor_id' => $inactive->id]);
    $this->post(route('hr.leaves.store'), approvalRequest())->assertSessionHasErrorsIn('leave', 'supervisor');
    $this->assertDatabaseCount('hr_leaves', 0);
    $this->post(route('hr.leaves.store'), approvalRequest(['type' => 'sick_leave']))->assertSessionHas('success');
    expect(HrLeave::first()->approval_status)->toBe('recorded');
    $this->assertDatabaseCount('hr_leave_approval_tasks', 0);
});

test('authorized user administration assigns a supervisor and rejects clients self references and cycles', function () {
    $super = User::factory()->create();
    $super->assignRole(Role::findOrCreate('superadmin'));
    $manager = approvalEmployee();
    $role = Role::findOrCreate('auditor');
    $data = ['name' => 'Nowy Pracownik', 'email' => 'leave-worker@example.test', 'role' => $role->name, 'supervisor_id' => $manager->id];
    $this->actingAs($super)->post(route('settings.users.store'), $data)->assertSessionHasNoErrors();
    $employee = User::where('email', $data['email'])->firstOrFail();
    expect((int) $employee->supervisor_id)->toBe($manager->id);
    $this->put(route('settings.users.update', $employee), array_merge($data, ['supervisor_id' => $employee->id]))->assertSessionHasErrors('supervisor_id');
    $manager->update(['supervisor_id' => $employee->id]);
    $this->put(route('settings.users.update', $employee), $data)->assertSessionHasErrors('supervisor_id');
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $this->put(route('settings.users.update', $employee), array_merge($data, ['supervisor_id' => $client->id]))->assertSessionHasErrors('supervisor_id');
    $this->actingAs($manager)->put(route('settings.users.update', $employee), $data)->assertForbidden();
});

test('an accounting only account can read every request without gaining submission or approval powers', function () {
    $manager = approvalEmployee();
    $employee = approvalEmployee(['supervisor_id' => $manager->id]);
    $this->actingAs($employee)->post(route('hr.leaves.store'), approvalRequest());
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::findOrCreate('leave_accounting_only'));
    $accountant->givePermissionTo('hr.leaves.accounting');
    $this->actingAs($accountant)->get(route('hr.index', ['tab' => 'leaves', 'leave_scope' => 'all']))->assertOk()->assertSee('Wniosek prywatny test');
    $this->get(route('hr.leaves.pdf', HrLeave::first()))->assertOk();
    $this->post(route('hr.leaves.store'), approvalRequest())->assertForbidden();
    $this->post(route('hr.leaves.decide', HrLeave::first()), ['decision' => 'approved', 'approval_revision' => 1])->assertForbidden();
});

test('the approval email uses the company mail layout and links to the employee leave view', function () {
    $manager = approvalEmployee(['name' => 'Przełożony testowy']);
    $employee = approvalEmployee(['supervisor_id' => $manager->id]);
    $this->actingAs($employee)->post(route('hr.leaves.store'), approvalRequest());
    $leave = HrLeave::firstOrFail();
    $this->actingAs($manager)->post(route('hr.leaves.decide', $leave), ['decision' => 'approved', 'approval_revision' => 1]);
    $html = (new HrLeaveApproved($leave->fresh()))->render();
    expect($html)->toContain('HR test', 'Przełożony testowy', 'zaakceptowany', 'Życzymy miłego użytkowania!', 'leave_scope=own');
});
