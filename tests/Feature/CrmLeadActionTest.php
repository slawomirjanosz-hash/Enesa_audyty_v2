<?php

use App\Models\CrmOpportunity;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('lead actions are CRM tasks with an assignee and date and remain archived after lead edits', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $worker = User::factory()->create(['is_active' => true]);
    $worker->assignRole(Role::findOrCreate('auditor'));
    $data = ['title' => 'Lead z działaniem', 'stage' => 'new_lead', 'action_description' => 'Zadzwoń do klienta', 'action_due_date' => '2026-11-05', 'action_assigned_to' => $worker->id];
    $this->actingAs($admin)->post(route('crm.opportunities.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $lead = CrmOpportunity::where('title', $data['title'])->sole();
    $task = $lead->tasks()->sole();
    expect($task->description)->toBe($data['action_description']);
    expect((int) $task->assigned_to)->toBe($worker->id);
    expect($task->due_date->format('Y-m-d'))->toBe('2026-11-05');
    expect($task->status)->toBe('todo');
    expect($task->project_id)->toBeNull();
    $task->delete();
    $this->patch(route('crm.opportunities.update', $lead), ['title' => $lead->title, 'stage' => 'contact'])->assertSessionHasNoErrors();
    expect($lead->tasks()->count())->toBe(0);
    $this->patch(route('crm.opportunities.update', $lead), $data)->assertSessionHasNoErrors();
    expect($lead->tasks()->count())->toBe(1);
    expect(Task::onlyTrashed()->whereKey($task->id)->exists())->toBeTrue();
    $this->get(route('crm.index', ['tab' => 'pipeline']))->assertOk()->assertSee('Dodaj działanie')->assertSee('action_description');
});

test('lead actions reject incomplete data and inactive or client assignees without saving a lead', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $this->actingAs($admin);
    $base = ['title' => 'Nieprawidłowy', 'stage' => 'new_lead', 'action_description' => 'Kontakt'];
    $this->post(route('crm.opportunities.store'), $base)->assertSessionHasErrors(['action_due_date', 'action_assigned_to']);
    foreach ([false, true] as $active) {
        $worker = User::factory()->create(['is_active' => $active]);
        $worker->assignRole(Role::findOrCreate($active ? 'client_user' : 'auditor'));
        $this->post(route('crm.opportunities.store'), $base + ['action_due_date' => '2026-11-05', 'action_assigned_to' => $worker->id])->assertStatus(422);
    }
    expect(CrmOpportunity::count())->toBe(0);
});

test('lead editor without task management cannot assign an action', function () {
    $editor = User::factory()->create();
    $role = Role::findOrCreate('Lead action editor');
    $role->givePermissionTo([Permission::findOrCreate('crm.view'), Permission::findOrCreate('crm.leads.manage')]);
    $editor->assignRole($role);
    $lead = CrmOpportunity::create(['title' => 'Chroniony lead', 'stage' => 'new_lead']);
    $this->actingAs($editor)->patch(route('crm.opportunities.update', $lead), ['title' => 'Zmieniony', 'stage' => 'contact', 'action_description' => 'Kontakt', 'action_due_date' => '2026-11-05', 'action_assigned_to' => $editor->id])->assertForbidden();
    expect($lead->fresh()->title)->toBe('Chroniony lead');
    expect(Task::count())->toBe(0);
});
