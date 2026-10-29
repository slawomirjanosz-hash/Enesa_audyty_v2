<?php

use App\Models\BoardTask;
use App\Models\CompanySettings;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function calendarEntryUser(): User
{
    $role = Role::findOrCreate('calendar_entry_employee');
    $role->givePermissionTo(Permission::findOrCreate('calendar.view'));
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function calendarEntryData(string $kind = 'meeting'): array
{
    return ['title' => 'Spotkanie harmonogramowe', 'description' => 'Ustalenie terminów', 'due_date' => '2026-11-12', 'scheduled_time' => '14:30', 'calendar_kind' => $kind];
}

test('calendar creates own tasks and meetings visible on board without crm projects or audits', function (string $kind) {
    CompanySettings::create(['name' => 'Kalendarz', 'enabled_modules' => ['calendar']]);
    $user = calendarEntryUser();
    $other = calendarEntryUser();
    $this->actingAs($user)->post(route('calendar.store'), calendarEntryData($kind) + ['assigned_to' => $other->id, 'created_by' => $other->id, 'status' => 'done'])
        ->assertRedirect(route('calendar.index', ['month' => '2026-11']));
    $card = BoardTask::sole();
    expect($card->assigned_to)->toBe($user->id);
    expect((int) $card->created_by)->toBe($user->id);
    expect($card->status)->toBe('todo');
    expect($card->calendar_kind)->toBe($kind);
    expect($card->isCalendarEntry())->toBeTrue();
    $this->get(route('calendar.index', ['month' => '2026-11']))->assertOk()->assertSee('Spotkanie harmonogramowe')->assertSee('14:30')->assertSee('data-calendar-date="2026-11-12"', false);
    $this->get(route('board.mine'))->assertOk()->assertSee('Spotkanie harmonogramowe')->assertSee('14:30')->assertSee('12.11.2026');
    if (getenv('CALENDAR_ENTRY_QA') && $kind === 'meeting') {
        file_put_contents(base_path('tmp/calendar-entry-render.html'), $this->get(route('calendar.index', ['month' => '2026-11']))->getContent());
        file_put_contents(base_path('tmp/calendar-board-render.html'), $this->get(route('board.mine'))->getContent());
    }
    $this->get(route('board.mine', ['personal' => ['']]))->assertOk()->assertDontSee('Spotkanie harmonogramowe');
    $this->actingAs($other)->get(route('calendar.index', ['month' => '2026-11', 'scope' => 'team']))->assertOk()->assertDontSee('Spotkanie harmonogramowe');
    $this->get(route('board.mine'))->assertOk()->assertDontSee('Spotkanie harmonogramowe');
    $this->patchJson(route('board.status', $card), ['status' => 'done', 'revision' => 1])->assertForbidden();
    $this->actingAs($user)->patchJson(route('board.status', $card), ['status' => 'done', 'revision' => 1])->assertOk();
    $this->patchJson(route('board.archive', ['board', $card->id]), ['restore' => false, 'revision' => 2])->assertOk();
    $this->get(route('calendar.index', ['month' => '2026-11']))->assertOk()->assertDontSee('Spotkanie harmonogramowe');
    $this->get(route('board.mine', ['archive' => 1]))->assertOk()->assertSee('Spotkanie harmonogramowe');
    $this->patchJson(route('board.archive', ['board', $card->id]), ['restore' => true, 'revision' => 3])->assertOk();
    $this->get(route('calendar.index', ['month' => '2026-11']))->assertOk()->assertSee('Spotkanie harmonogramowe');
})->with(['task', 'meeting']);

test('calendar entry validation and permission boundaries', function () {
    $user = calendarEntryUser();
    $this->actingAs($user)->postJson(route('calendar.store'), array_replace(calendarEntryData(), ['due_date' => '2026-02-31', 'scheduled_time' => '25:90', 'calendar_kind' => 'project', 'title' => '']))
        ->assertUnprocessable()->assertJsonValidationErrors(['due_date', 'scheduled_time', 'calendar_kind', 'title']);
    expect(BoardTask::count())->toBe(0);
    $other = User::factory()->create();
    $other->assignRole(Role::findOrCreate('no_calendar'));
    $this->actingAs($other)->postJson(route('calendar.store'), calendarEntryData())->assertForbidden();
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_admin'));
    $client->givePermissionTo(Permission::findOrCreate('calendar.view'));
    $this->actingAs($client)->postJson(route('calendar.store'), calendarEntryData())->assertForbidden();
    CompanySettings::create(['name' => 'Bez kalendarza', 'enabled_modules' => ['dashboard']]);
    $this->actingAs($user)->postJson(route('calendar.store'), calendarEntryData())->assertForbidden();
    expect(BoardTask::count())->toBe(0);
});

test('team read permissions do not grant modification of another calendar entry', function () {
    $owner = calendarEntryUser();
    $reader = calendarEntryUser();
    $reader->givePermissionTo([Permission::findOrCreate('calendar.team.view'), Permission::findOrCreate('board.team.view')]);
    $this->actingAs($owner)->post(route('calendar.store'), calendarEntryData())->assertRedirect();
    $card = BoardTask::sole();
    $this->actingAs($reader)->get(route('calendar.index', ['month' => '2026-11', 'scope' => 'team', 'user_id' => $owner->id]))->assertOk()->assertSee($card->title);
    $this->get(route('board.mine', ['users' => [$owner->id]]))->assertOk()->assertSee($card->title);
    $this->patchJson(route('board.status', $card), ['status' => 'done', 'revision' => 1])->assertForbidden();
    $this->patchJson(route('board.archive', ['board', $card->id]), ['restore' => false, 'revision' => 1])->assertForbidden();
    $this->putJson(route('board.participants', ['board', $card->id]), ['participants' => [$reader->id], 'revision' => 1])->assertForbidden();
});
