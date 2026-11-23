<?php

use App\Models\CompanySettings;
use App\Models\HrAttendance;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('attendance hours use the latest earlier working entry and protect other employees', function () {
    CompanySettings::create(['name' => 'HR', 'enabled_modules' => ['hr']]);
    $role = Role::findOrCreate('attendance_employee');
    $role->givePermissionTo(Permission::findOrCreate('hr.attendance.view'));
    $user = User::factory()->create();
    $user->assignRole($role);
    $other = User::factory()->create();
    $other->assignRole($role);
    foreach ([
        ['work_date' => '2026-11-20', 'started_at' => '07:30', 'finished_at' => '15:30', 'status' => 'present'],
        ['work_date' => '2026-11-21', 'started_at' => null, 'finished_at' => null, 'status' => 'leave'],
        ['work_date' => '2026-11-24', 'started_at' => '10:00', 'finished_at' => '18:00', 'status' => 'remote'],
    ] as $data) {
        HrAttendance::create($data + ['user_id' => $user->id]);
    }
    $url = route('hr.attendance.hours');
    $this->actingAs($user)->getJson($url.'?work_date=2026-11-23')->assertOk()->assertJson(['started_at' => '07:30', 'finished_at' => '15:30']);
    $this->getJson($url.'?work_date=2026-11-20')->assertOk()->assertJson(['started_at' => '', 'finished_at' => '']);
    $this->getJson($url.'?work_date=2026-11-23&user_id='.$other->id)->assertForbidden();
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $admin->givePermissionTo('hr.attendance.view');
    $this->actingAs($admin)->getJson($url.'?work_date=2026-11-23&user_id='.$user->id)->assertOk()->assertJsonPath('started_at', '07:30');
    $html = $this->get(route('hr.index', ['tab' => 'attendance']))->assertOk()->getContent();
    preg_match('/<div id="attendance-modal".*?(?=<div id="leave-modal"|$)/s', $html, $match);
    expect($match[0])->not->toContain('leave-document-date');
});

test('leave document date precedes the employee selection within the leave form', function () {
    CompanySettings::create(['name' => 'HR', 'enabled_modules' => ['hr']]);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin'));
    $html = $this->actingAs($user)->get(route('hr.index', ['tab' => 'leaves']))->assertOk()->getContent();
    preg_match('/<form id="leave-form".*?<\/form>/s', $html, $match);
    expect(strpos($match[0], 'leave-document-date'))->toBeLessThan(strpos($match[0], 'leave-user-id'));
});
