<?php

use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findOrCreate('admin'));
    CompanySettings::create(['name' => 'Firma platformy', 'short_name' => 'PRINZ']);
    $this->actingAs($this->admin);
});

test('new project uses platform prefix with editable date and sequence while old numbers stay unchanged', function () {
    $old = Project::create(['number' => 'PR_20260409_004', 'name' => 'Stary projekt', 'manager_id' => $this->admin->id, 'status' => 'active']);
    expect(Project::nextNumberSequence())->toBe(5);
    $this->get(route('projects.index'))->assertOk()->assertSee('PRINZ_PR_'.now()->format('Ymd').'_005')->assertSee('Data w numerze');
    $data = ['name' => 'Nowy projekt', 'manager_id' => $this->admin->id, 'status' => 'planned', 'contract_value' => 0,
        'number_date' => '2027-02-03', 'number_sequence' => '009', 'number' => 'FORGED'];
    $this->post(route('projects.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $new = Project::latest('id')->firstOrFail();
    expect($new->number)->toBe('PRINZ_PR_20270203_009')
        ->and($old->fresh()->number)->toBe('PR_20260409_004')
        ->and($new->start_date)->toBeNull()
        ->and(Project::nextNumberSequence())->toBe(10);
    $this->post(route('projects.store'), $data)->assertSessionHasErrors('number');
    expect(Project::count())->toBe(2);
    $new->delete();
    expect(Project::nextNumberSequence())->toBe(10);
    $this->post(route('projects.store'), $data)->assertSessionHasErrors('number');
});

test('copy gets the new numbering format without renumbering its source', function () {
    $source = Project::create(['number' => 'PRJ/2026/014', 'name' => 'Wzór', 'manager_id' => $this->admin->id, 'status' => 'active']);
    $this->post(route('projects.copy', $source), ['name' => 'Kopia', 'number_date' => '2026-09-22', 'number_sequence' => 15])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(Project::latest('id')->first()->number)->toBe('PRINZ_PR_20260922_015')
        ->and($source->fresh()->number)->toBe('PRJ/2026/014');
});

test('invalid number components are rejected for creation and copy', function () {
    $source = Project::create(['number' => 'OLD/001', 'name' => 'Wzór', 'manager_id' => $this->admin->id, 'status' => 'active']);
    $data = ['name' => 'Błędny', 'manager_id' => $this->admin->id, 'status' => 'planned', 'contract_value' => 0,
        'number_date' => '2026-02-30', 'number_sequence' => 0];
    $this->post(route('projects.store'), $data)->assertSessionHasErrors(['number_date', 'number_sequence']);
    $this->post(route('projects.copy', $source), $data)->assertSessionHasErrorsIn('projectCopy', ['number_date', 'number_sequence']);
    expect(Project::count())->toBe(1);
});

test('client cannot create numbered projects or copy existing projects', function () {
    $source = Project::create(['number' => 'OLD/001', 'name' => 'Wzór', 'manager_id' => $this->admin->id, 'status' => 'active']);
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $data = ['name' => 'Nieuprawniony', 'number_date' => '2026-09-22', 'number_sequence' => 2];
    $this->actingAs($client)->post(route('projects.store'), $data)->assertForbidden();
    $this->post(route('projects.copy', $source), $data)->assertForbidden();
    expect(Project::count())->toBe(1);
});
