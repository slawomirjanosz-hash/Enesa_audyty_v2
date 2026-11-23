<?php

use App\Mail\HrLeaveReminder;
use App\Models\CompanySettings;
use App\Models\HrLeave;
use App\Models\User;
use App\Services\HrLeaveReminderSelection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    CompanySettings::create(['name' => 'HR', 'enabled_modules' => ['hr']]);
    config(['mail.default' => 'smtp', 'mail.from.address' => 'reminders@test.invalid', 'mail.mailers.smtp.host' => 'smtp.test.invalid']);
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-11-23 08:01', 'Europe/Warsaw'));
});

function leaveReminderManager(): User
{
    $u = User::factory()->create();
    $role = Role::findOrCreate('leave_reminder_manager');
    $role->givePermissionTo(Permission::findOrCreate('hr.leaves.view'));
    $u->assignRole($role);

    return $u;
}

function pendingReminderLeave(User $manager, string $date, array $extra = []): HrLeave
{
    $employee = User::factory()->create();

    return HrLeave::create($extra + ['user_id' => $employee->id, 'created_by' => $employee->id, 'type' => 'annual',
        'start_date' => $date, 'end_date' => $date, 'days' => 1, 'document_date' => '2026-11-01', 'approval_status' => 'pending', 'approver_id' => $manager->id]);
}

test('one digest covers tomorrow today and overdue pending requests and repeats only next day', function () {
    $manager = leaveReminderManager();
    $other = leaveReminderManager();
    $old = pendingReminderLeave($manager, '2026-11-01');
    pendingReminderLeave($manager, '2026-11-23');
    pendingReminderLeave($manager, '2026-11-24');
    pendingReminderLeave($manager, '2026-11-25');
    pendingReminderLeave($manager, '2026-11-22', ['approval_status' => 'approved']);
    pendingReminderLeave($manager, '2026-11-22', ['approval_status' => 'rejected']);
    pendingReminderLeave($other, '2026-11-24');
    $this->artisan('hr:send-leave-reminders --scheduled')->assertSuccessful();
    $this->artisan('hr:send-leave-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(HrLeaveReminder::class, fn ($mail) => $mail->hasTo($manager->email) && $mail->leaves->count() === 3);
    Mail::assertSent(HrLeaveReminder::class, fn ($mail) => $mail->hasTo($other->email) && $mail->leaves->count() === 1);
    Mail::assertSentCount(2);
    $old->update(['approval_status' => 'approved']);
    $this->travelTo(Carbon::parse('2026-11-24 08:01', 'Europe/Warsaw'));
    $this->artisan('hr:send-leave-reminders --scheduled')->assertSuccessful();
    Mail::assertSentCount(4);
    expect(app(HrLeaveReminderSelection::class)->groups()->get($manager->id)->contains('id', $old->id))->toBeFalse();
});

test('dry run and wrong hour never claim deliveries and disabled HR suppresses digests', function () {
    pendingReminderLeave(leaveReminderManager(), '2026-11-24');
    $this->artisan('hr:send-leave-reminders --dry-run')->assertSuccessful();
    $this->travelTo(Carbon::parse('2026-11-23 07:59', 'Europe/Warsaw'));
    $this->artisan('hr:send-leave-reminders --scheduled')->assertSuccessful();
    Mail::assertNothingSent();
    $this->assertDatabaseCount('hr_leave_reminder_deliveries', 0);
    CompanySettings::first()->update(['enabled_modules' => []]);
    expect(app(HrLeaveReminderSelection::class)->groups())->toBeEmpty();
});

test('inactive and unauthorized approvers are excluded and a saved decision stops reminders', function () {
    $manager = leaveReminderManager();
    $leave = pendingReminderLeave($manager, '2026-11-24');
    $manager->update(['is_active' => false]);
    expect(app(HrLeaveReminderSelection::class)->groups())->toBeEmpty();
    $manager->update(['is_active' => true]);
    $leave->update(['approval_status' => 'rejected']);
    $this->artisan('hr:send-leave-reminders')->assertSuccessful();
    Mail::assertNothingSent();
    $leave->update(['approval_status' => 'pending']);
    $manager->syncRoles([]);
    expect(app(HrLeaveReminderSelection::class)->groups())->toBeEmpty();
});

test('calendar daily reminders also cover the day before a Sunday leave', function () {
    $this->travelTo(Carbon::parse('2026-11-28 08:01', 'Europe/Warsaw'));
    $leave = pendingReminderLeave(leaveReminderManager(), '2026-11-29');
    $this->artisan('hr:send-leave-reminders --scheduled')->assertSuccessful();
    Mail::assertSent(HrLeaveReminder::class, fn ($mail) => $mail->leaves->contains('id', $leave->id));
    $mail = new HrLeaveReminder(collect([$leave]));
    expect($mail->render())->toContain('Rozpatrz wniosek','29.11.2026')->not->toContain('decision_note');
});
