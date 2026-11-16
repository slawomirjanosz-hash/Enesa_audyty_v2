<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('project schedule costs and participants persist and calendars respect selected people and access', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('superadmin'));
    [$owner, $participant, $outsider] = User::factory()->count(3)->create(['is_active' => true])->all();
    $role = Role::findOrCreate('calendar-stage-staff');
    $role->givePermissionTo(Permission::findOrCreate('calendar.view'));
    foreach ([$owner, $participant, $outsider] as $user) {
        $user->assignRole($role);
    }
    $project = Project::create(['name' => 'Projekt kalendarza', 'number' => 'CAL-01', 'manager_id' => $owner->id, 'status' => 'active']);
    $project->members()->attach([$owner->id, $participant->id]);
    $data = ['title' => 'Wspólny etap instalacji', 'start_date' => '2026-11-16', 'due_date' => '2026-11-18',
        'status' => 'todo', 'priority' => 'medium', 'progress' => 0, 'assigned_to' => $owner->id,
        'participant_ids' => [$participant->id], 'schedule_cost' => '1234.56'];
    $store = route('projects.tasks.store', $project);
    $this->actingAs($admin)->postJson($store, array_replace($data, ['participant_ids' => [$outsider->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('participant_ids');
    $response = $this->postJson($store, $data)->assertCreated()->assertJsonPath('schedule_cost', '1234.56')
        ->assertJsonPath('participant_ids.0', $participant->id);
    $stage = Task::findOrFail($response->json('db_id'));
    $this->patchJson(route('projects.tasks.update', [$project, $stage]), ['progress' => 10])
        ->assertOk()->assertJsonPath('schedule_cost', '1234.56')->assertJsonPath('participant_ids.0', $participant->id);
    $this->get(route('projects.show', [$project, 'tab' => 'gantt']))->assertOk()->assertSee('Suma kosztów')->assertSee('Osoby zaangażowane');
    $this->postJson($store, array_replace($data, ['title' => 'Etap tylko osoby odpowiedzialnej', 'participant_ids' => []]))->assertCreated();
    $calendar = fn ($args = []) => route('calendar.index', ['month' => '2026-11'] + $args);
    $this->actingAs($owner)->get($calendar())->assertOk()->assertSee($stage->title);
    $this->actingAs($participant)->get($calendar())->assertOk()->assertSee($stage->title)->assertDontSee('Etap tylko osoby odpowiedzialnej');
    $this->actingAs($outsider)->get($calendar(['scope' => 'team', 'user_ids' => [$owner->id, $participant->id]]))
        ->assertOk()->assertDontSee($stage->title)->assertDontSee('Wybrane osoby');
    $this->actingAs($admin)->get($calendar(['scope' => 'team', 'user_ids' => [$outsider->id]]))->assertOk()->assertDontSee($stage->title);
    $this->get($calendar(['scope' => 'team', 'user_ids' => [$participant->id, $outsider->id]]))
        ->assertOk()->assertSee($stage->title)->assertDontSee('Etap tylko osoby odpowiedzialnej');
    $this->get($calendar(['scope' => 'team']))->assertOk()->assertSee($stage->title)->assertSee('Etap tylko osoby odpowiedzialnej');
});
