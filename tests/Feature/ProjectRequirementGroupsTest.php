<?php

use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::findOrCreate('admin'));
    $this->project = Project::create(['number' => 'GROUP/1', 'name' => 'Grupowanie', 'status' => 'active', 'manager_id' => $this->manager->id]);
    $this->payload = ['type' => 'material', 'name' => 'Rura', 'quantity' => 2, 'status' => 'requested', 'group_name' => 'Instalacja wodna'];
    $this->actingAs($this->manager);
});

test('materials and services persist an optional group and expose sortable filtering and copy data', function () {
    $this->post(route('projects.requirements.store', $this->project), $this->payload)->assertSessionHasNoErrors();
    $item = $this->project->requirements()->sole();
    expect($item->group_name)->toBe('Instalacja wodna');
    $this->get(route('projects.show', [$this->project, 'tab' => 'requirements']))->assertOk()
        ->assertSee('<th>Grupa</th>', false)->assertSee('data-sort-value="Instalacja wodna"', false)
        ->assertSee('requirements-group-filter')->assertSee('requirement-groups')->assertSee('group_name');
    $this->patch(route('projects.requirements.update', [$this->project, $item]), array_replace($this->payload, ['type' => 'service', 'group_name' => '  Montaż  ']))->assertSessionHasNoErrors();
    expect($item->fresh()->group_name)->toBe('Montaż');
    $this->patch(route('projects.requirements.update', [$this->project, $item]), array_replace($this->payload, ['group_name' => '']))->assertSessionHasNoErrors();
    expect($item->fresh()->group_name)->toBeNull();
    $this->post(route('projects.requirements.store', $this->project), array_replace($this->payload, ['group_name' => str_repeat('a', 121)]))->assertSessionHasErrors('group_name');
});

test('bulk groups are scoped to the project and require edit permission', function () {
    $this->post(route('projects.requirements.store', $this->project), $this->payload)->assertSessionHasNoErrors();
    $item = $this->project->requirements()->sole();
    $other = Project::create(['number' => 'OTHER/1', 'name' => 'Other', 'status' => 'active', 'manager_id' => $this->manager->id]);
    $foreign = $other->requirements()->create($this->payload);
    $this->post(route('projects.requirements.bulk', $this->project), ['action' => 'set_group', 'requirement_ids' => [$item->id], 'group_name' => 'Osprzęt'])->assertSessionHasNoErrors();
    expect($item->fresh()->group_name)->toBe('Osprzęt');
    $this->post(route('projects.requirements.bulk', $this->project), ['action' => 'set_group', 'requirement_ids' => [$item->id, $foreign->id], 'group_name' => 'Błędna'])->assertSessionHasErrors('requirement_ids');
    expect($item->fresh()->group_name)->toBe('Osprzęt')->and($foreign->fresh()->group_name)->toBe('Instalacja wodna');
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('auditor'));
    $viewer->givePermissionTo(Permission::findOrCreate('projects.view'));
    $this->project->members()->attach($viewer);
    $this->actingAs($viewer)->post(route('projects.requirements.bulk', $this->project), ['action' => 'set_group', 'requirement_ids' => [$item->id], 'group_name' => 'Nieuprawniona'])->assertForbidden();
    $this->actingAs($this->manager)->post(route('projects.requirements.bulk', $this->project), ['action' => 'set_group', 'requirement_ids' => [$item->id], 'group_name' => ''])->assertSessionHasNoErrors();
    expect($item->fresh()->group_name)->toBeNull();
});
