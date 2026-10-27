<?php

use App\Models\ActivityLog;
use App\Models\CompanySettings;
use App\Models\HrVehicle;
use App\Models\HrVehicleColumnResponsibility;
use App\Models\User;
use App\Services\HrVehicleReminderSelection;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function columnPerson(bool $responsible = true, bool $admin = false): User
{
    $person = User::factory()->create();
    $role = Role::findOrCreate($admin ? 'admin' : 'column_employee');
    $role->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $person->assignRole($role);
    if ($responsible) {
        $person->givePermissionTo(Permission::findOrCreate('hr.vehicles.responsible'));
    }

    return $person;
}

function columnAssign(string $column, array $ids): void
{
    HrVehicleColumnResponsibility::where('column_key', $column)->firstOrFail()->update(['user_ids' => $ids]);
}

beforeEach(function () {
    CompanySettings::create(['name' => 'Kolumny test', 'enabled_modules' => ['hr']]);
    $this->travelTo(Carbon::parse('2026-10-27 08:00', 'Europe/Warsaw'));
});

test('only HR managers can assign eligible fleet people and changes are revision checked and audited', function () {
    $admin = columnPerson(false, true);
    $person = columnPerson();
    $outsider = columnPerson(false);
    $url = route('hr.vehicles.column-responsibility', 'insurance_expires_at');
    $this->actingAs($person)->put($url, ['revision' => 1, 'user_ids' => [$person->id]])->assertForbidden();
    $this->actingAs($admin)->put($url, ['revision' => 1, 'user_ids' => [$outsider->id]])->assertSessionHasErrors('user_ids.0');
    $person->update(['is_active' => false]);
    $this->put($url, ['revision' => 1, 'user_ids' => [$person->id]])->assertSessionHasErrors('user_ids.0');
    $person->update(['is_active' => true]);
    $this->put($url, ['revision' => 1, 'user_ids' => [$person->id]])->assertSessionHasNoErrors();
    $row = HrVehicleColumnResponsibility::where('column_key', 'insurance_expires_at')->firstOrFail();
    expect($row->user_ids)->toBe([$person->id])->and($row->revision)->toBe(2);
    $log = ActivityLog::where('auditable_type', HrVehicleColumnResponsibility::class)->latest('id')->firstOrFail();
    expect($log->user_id)->toBe($admin->id)->and($log->changes['user_ids']['new_display'])->toContain($person->name)->and($log->changes)->not->toHaveKey('revision');
    $this->put($url, ['revision' => 1, 'user_ids' => []])->assertStatus(409);
    expect($row->fresh()->user_ids)->toBe([$person->id]);
    $this->put($url, ['revision' => 2])->assertSessionHasNoErrors();
    expect($row->fresh()->user_ids)->toBe([]);
    $this->put(route('hr.vehicles.column-responsibility', 'password'), ['revision' => 1])->assertNotFound();
});

test('column-specific dates reach assigned managers and the linked owner without leaking other columns or private cars', function () {
    $insurance = columnPerson();
    $inspection = columnPerson();
    $owner = columnPerson(false);
    columnAssign('insurance_expires_at', [$insurance->id]);
    columnAssign('inspection_due_at', [$inspection->id]);
    $car = HrVehicle::create(['type' => 'company', 'name' => 'Flota', 'registration_number' => 'FLEET-COL', 'insurance_expires_at' => '2026-10-26', 'inspection_due_at' => '2026-10-26']);
    $car->forceFill(['responsible_user_id' => $owner->id])->save();
    HrVehicle::create(['type' => 'private', 'name' => 'Sekret prywatny', 'registration_number' => 'PRIVATE-COL', 'created_by' => $owner->id, 'user_id' => $owner->id, 'insurance_expires_at' => '2026-10-26']);
    $groups = app(HrVehicleReminderSelection::class)->groups();
    expect($groups[$insurance->id]->count())->toBe(1)->and($groups[$insurance->id]->first()['message'])->toContain(HrVehicle::COLUMNS['insurance_expires_at']);
    expect($groups[$inspection->id]->count())->toBe(1)->and($groups[$inspection->id]->first()['message'])->toContain(HrVehicle::COLUMNS['inspection_due_at']);
    expect($groups[$owner->id]->count())->toBe(2);
    expect($groups->flatten(1)->pluck('vehicle.registration_number')->unique()->all())->toBe(['FLEET-COL']);
    $insurance->revokePermissionTo('hr.vehicles.responsible');
    $groups = app(HrVehicleReminderSelection::class)->groups();
    expect($groups->has($insurance->id))->toBeFalse()->and($groups[$inspection->id]->count())->toBe(1);
});

test('Monday missing data is filtered per column including service book and registration document aliases', function () {
    $this->travelTo(Carbon::parse('2026-10-26 08:00', 'Europe/Warsaw'));
    $first = columnPerson();
    $second = columnPerson();
    foreach (array_keys(HrVehicle::COLUMNS) as $column) {
        columnAssign($column, [$first->id]);
    }
    columnAssign('fuel_card_number', [$second->id]);
    columnAssign('service_book_entry_at', [$second->id]);
    columnAssign('registration_document', [$second->id]);
    HrVehicle::create(['type' => 'company', 'name' => 'Braki', 'registration_number' => 'MISSING-COL']);
    $groups = app(HrVehicleReminderSelection::class)->groups();
    $firstText = $groups[$first->id]->first()['message'];
    $secondText = $groups[$second->id]->first()['message'];
    expect($firstText)->not->toContain(HrVehicle::COLUMNS['fuel_card_number'], 'Książka serwisowa — status', 'Dowód rejestracyjny — dostępny');
    expect($secondText)->toContain(HrVehicle::COLUMNS['fuel_card_number'], 'Książka serwisowa — status', 'Dowód rejestracyjny — dostępny')->not->toContain(HrVehicle::COLUMNS['insurer']);
    expect($groups[$first->id]->count())->toBe(1)->and($groups[$second->id]->count())->toBe(1);
    $this->travelTo(Carbon::parse('2026-10-27 08:00', 'Europe/Warsaw'));
    expect(app(HrVehicleReminderSelection::class)->groups())->toBeEmpty();
});

test('empty column selections preserve default recipients and manager controls keep sorting labels', function () {
    $admin = columnPerson(true, true);
    $second = columnPerson();
    HrVehicle::create(['type' => 'company', 'name' => 'Domyślne', 'registration_number' => 'DEFAULT-COL', 'inspection_due_at' => '2026-10-26']);
    expect(app(HrVehicleReminderSelection::class)->groups()->keys()->sort()->values()->all())->toBe([$admin->id, $second->id]);
    $this->actingAs($admin)->get(route('hr.index', ['tab' => 'vehicles']))->assertOk()->assertSee('data-table-header-controls', false)->assertSee('Odpowiedzialni za:')->assertSee('vehicle-column-responsibility-modal')->assertSee($second->name);
    $this->actingAs($second)->get(route('hr.index', ['tab' => 'vehicles']))->assertOk()->assertDontSee('id="vehicle-column-responsibility-modal"', false);
});

test('several people can share a column without duplicate notifications for a linked owner', function () {
    $first = columnPerson();
    $second = columnPerson();
    $other = columnPerson();
    columnAssign('insurance_expires_at', [$first->id, $second->id]);
    $car = HrVehicle::create(['type' => 'company', 'name' => 'Wspólna kolumna', 'registration_number' => 'MULTI-COL', 'insurance_expires_at' => '2026-11-02']);
    $car->forceFill(['responsible_user_id' => $first->id])->save();
    $groups = app(HrVehicleReminderSelection::class)->groups();
    expect($groups->has($other->id))->toBeFalse()->and($groups->count())->toBe(2);
    expect($groups[$first->id]->count())->toBe(1)->and($groups[$second->id]->count())->toBe(1);
});
