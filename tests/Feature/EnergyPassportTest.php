<?php

use App\Models\Company;
use App\Models\EnergyPassport;
use App\Models\EnergyPassportTemplate;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('built in Excel passport templates are imported with stages and questions', function () {
    expect(EnergyPassportTemplate::query()->count())->toBe(9);

    $template = EnergyPassportTemplate::query()->where('source_filename', 'Paszport_AHU_Wentylacja_v3.xlsx')->firstOrFail();
    $questions = collect($template->sections)
        ->flatMap(fn (array $stage) => collect($stage['sections'])->flatMap(fn (array $section) => $section['questions']));

    expect($template->is_builtin)->toBeTrue()
        ->and($template->category)->toBe('Wentylacja')
        ->and(count($template->sections))->toBe(3)
        ->and($questions->count())->toBeGreaterThan(30)
        ->and($questions->pluck('code'))->toContain('P-00');
});

test('authorized user creates edits and deletes an energy passport', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('passport_manager');
    $role->givePermissionTo([
        Permission::findOrCreate('audits.view'),
        Permission::findOrCreate('audits.passports.view'),
        Permission::findOrCreate('audits.passports.manage'),
    ]);
    $user->assignRole($role);
    $company = Company::create(['name' => 'Zakład ISO 50001', 'company_type' => 'client']);
    $template = EnergyPassportTemplate::query()->firstOrFail();

    $this->actingAs($user)->get(route('energy-passports.index'))
        ->assertOk()->assertSee('Paszporty energetyczne')->assertSee($template->name);

    $this->actingAs($user)->post(route('energy-passports.store'), [
        'template_id' => $template->id,
        'company_id' => $company->id,
        'name' => 'Centrala AHU-01',
        'asset_identifier' => 'AHU-01',
        'location' => 'Hala A',
        'status' => 'draft',
    ])->assertRedirect();

    $passport = EnergyPassport::firstOrFail();
    $question = collect($template->sections)->flatMap(fn (array $stage) => collect($stage['sections'])->flatMap(fn (array $section) => $section['questions']))->first();

    $this->actingAs($user)->put(route('energy-passports.update', $passport), [
        'company_id' => $company->id,
        'name' => 'Centrala AHU-01 po inwentaryzacji',
        'asset_identifier' => 'AHU-01',
        'location' => 'Hala A',
        'status' => 'in_progress',
        'responses' => [$question['key'] => 'Odpowiedź techniczna'],
    ])->assertSessionHas('success');

    expect($passport->fresh()->responses[$question['key']])->toBe('Odpowiedź techniczna');
    $this->actingAs($user)->delete(route('energy-passports.destroy', $passport))->assertRedirect(route('energy-passports.index'));
    $this->assertDatabaseMissing('energy_passports', ['id' => $passport->id]);
});

test('passport viewer cannot modify records', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('passport_viewer');
    $role->givePermissionTo([Permission::findOrCreate('audits.view'), Permission::findOrCreate('audits.passports.view')]);
    $user->assignRole($role);
    $passport = EnergyPassport::create(['name' => 'Tylko podgląd', 'status' => 'draft']);

    $this->actingAs($user)->get(route('energy-passports.edit', $passport))->assertOk()->assertSee('Tylko podgląd');
    $this->actingAs($user)->put(route('energy-passports.update', $passport), [])->assertForbidden();
    $this->actingAs($user)->delete(route('energy-passports.destroy', $passport))->assertForbidden();
});
