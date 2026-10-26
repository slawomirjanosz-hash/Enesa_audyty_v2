<?php

use App\Models\Company;
use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('lead actions preserve history and attribution without creating tasks and separate tasks remain linked to CRM', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $worker = User::factory()->create(['is_active' => true]);
    $worker->assignRole(Role::findOrCreate('auditor'));
    $company = Company::create(['name' => 'Historia klienta', 'company_type' => 'client', 'status' => 'active']);
    $data = ['title' => 'Lead z działaniem', 'company_id' => $company->id, 'stage' => 'new_lead', 'action_description' => 'Rozmowa z klientem została zakończona', 'action_due_date' => '2026-10-20', 'action_assigned_to' => $worker->id];
    $this->actingAs($admin)->post(route('crm.opportunities.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $lead = CrmOpportunity::where('title', $data['title'])->sole();
    $activity = $lead->activities()->where('type', 'lead_action')->sole();
    expect($activity->description)->toBe($data['action_description']);
    expect((int) $activity->performed_by)->toBe($worker->id);
    expect((int) $activity->user_id)->toBe($admin->id);
    expect($activity->occurred_on->format('Y-m-d'))->toBe('2026-10-20');
    expect(Task::count())->toBe(0);
    $longDescription = trim(str_repeat('Długi zapis rozmowy. ', 60));
    $this->patch(route('crm.opportunities.update', $lead), array_replace($data, ['action_description' => $longDescription]))->assertSessionHasNoErrors();
    expect($lead->activities()->where('type', 'lead_action')->count())->toBe(2);
    expect(CrmActivity::where('description', $longDescription)->exists())->toBeTrue();
    expect(Task::count())->toBe(0);
    $this->get(route('companies.show', $company))->assertOk()->assertSee($data['action_description'])->assertSee('20.10.2026');
    $this->get(route('crm.index', ['tab' => 'pipeline']))->assertOk()->assertSee('Historia działań')->assertSee('Dodaj zadanie')->assertSee('Rozmowa z klientem');
    $this->post(route('crm.tasks.store'), ['title' => 'Osobne zadanie', 'company_id' => $company->id, 'crm_opportunity_id' => $lead->id, 'assigned_to' => $worker->id, 'status' => 'todo', 'priority' => 'medium'])->assertSessionHasNoErrors();
    expect($lead->tasks()->sole()->title)->toBe('Osobne zadanie');
    expect($lead->activities()->where('type', 'lead_action')->count())->toBe(2);
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

test('lead editor can record history without task permissions including leads without a company but viewer cannot', function () {
    $editor = User::factory()->create();
    $role = Role::findOrCreate('Lead action editor');
    $role->givePermissionTo([Permission::findOrCreate('crm.view'), Permission::findOrCreate('crm.leads.manage')]);
    $editor->assignRole($role);
    $lead = CrmOpportunity::create(['title' => 'Chroniony lead', 'stage' => 'new_lead']);
    $data = ['title' => 'Zmieniony', 'stage' => 'contact', 'action_description' => 'Kontakt', 'action_due_date' => '2026-10-20', 'action_assigned_to' => $editor->id];
    $this->actingAs($editor)->patch(route('crm.opportunities.update', $lead), $data)->assertSessionHasNoErrors();
    expect($lead->activities()->where('type', 'lead_action')->sole()->company_id)->toBeNull();
    $this->get(route('crm.index', ['tab' => 'pipeline']))->assertOk()->assertSee('Dodaj działanie');
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('employee'));
    $viewer->givePermissionTo('crm.view');
    $this->actingAs($viewer)->patch(route('crm.opportunities.update', $lead), $data)->assertForbidden();
    expect($lead->activities()->where('type', 'lead_action')->count())->toBe(1);
    expect(Task::count())->toBe(0);
});

test('CRM tasks default to todo then in progress then done within the accessible scope after each page load', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('superadmin'));
    $other = User::factory()->create();
    $other->assignRole(Role::findOrCreate('employee'));
    $expected = [];
    foreach ([$admin, $other] as $person) {
        $done = Task::create(['title' => 'A gotowe '.$person->id, 'assigned_to' => $person->id, 'status' => 'done', 'due_date' => '2026-01-01']);
        $progress = Task::create(['title' => 'B w toku '.$person->id, 'assigned_to' => $person->id, 'status' => 'in_progress', 'due_date' => '2026-02-01']);
        $todo = Task::create(['title' => 'Z do zrobienia '.$person->id, 'assigned_to' => $person->id, 'status' => 'todo', 'due_date' => '2026-12-01']);
        $expected[$person->id] = [$todo->id, $progress->id, $done->id];
    }
    $url = route('crm.index', ['tab' => 'tasks']);
    $this->actingAs($admin)->get($url)->assertOk()->assertViewHas('myTasks', fn ($tasks) => $tasks->pluck('id')->all() === $expected[$admin->id]);
    $this->get($url)->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('status')->all() === ['todo', 'todo', 'in_progress', 'in_progress', 'done', 'done']);
    $other->givePermissionTo('crm.tasks.own.manage');
    $this->actingAs($other)->get($url)->assertOk()->assertViewHas('myTasks', fn ($tasks) => $tasks->pluck('id')->all() === $expected[$other->id])->assertViewHas('tasks', fn ($tasks) => $tasks->isEmpty());
    $this->get($url)->assertDontSee('Z do zrobienia '.$admin->id);
});

test('delegated auditor cannot read or add history to an unrelated lead', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole(Role::findOrCreate('auditor'));
    $auditor->givePermissionTo(['crm.view', 'crm.leads.manage']);
    $other = User::factory()->create();
    $lead = CrmOpportunity::create(['title' => 'Ukryty lead', 'stage' => 'new_lead', 'assigned_to' => $other->id]);
    CrmActivity::create(['crm_opportunity_id' => $lead->id, 'type' => 'lead_action', 'description' => 'Secret history fixture', 'occurred_on' => '2026-10-20', 'performed_by' => $other->id, 'user_id' => $other->id]);
    $this->actingAs($auditor)->get(route('crm.index', ['tab' => 'pipeline']))->assertOk()->assertDontSee('Secret history fixture')->assertDontSee('Ukryty lead');
    $this->patch(route('crm.opportunities.update', $lead), ['title' => $lead->title, 'stage' => $lead->stage, 'action_description' => 'Próba dopisania', 'action_due_date' => '2026-10-20', 'action_assigned_to' => $auditor->id])->assertForbidden();
    expect($lead->activities()->count())->toBe(1);
});
