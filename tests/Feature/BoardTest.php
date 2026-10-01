<?php

use App\Models\Audit;
use App\Models\BoardTask;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\User;
use App\Services\BoardTaskService;
use Spatie\Permission\Models\Role;

function boardFixture(): array
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin'));
    $company = Company::create(['name' => 'Tablica', 'company_type' => 'client', 'status' => 'active']);
    $project = Project::create(['company_id' => $company->id, 'number' => 'P/1', 'name' => 'Projekt tablicy', 'manager_id' => $user->id, 'status' => 'active']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'A/1', 'title' => 'Audyt tablicy', 'manager_id' => $user->id, 'status' => 'draft']);

    return [$user, $project, $audit];
}

test('boards create edit move and delete cards for both owner types with isolated stages', function () {
    [$user,$project,$audit] = boardFixture();
    $this->actingAs($user);
    foreach (['project' => $project, 'audit' => $audit] as $type => $owner) {
        $stage = $owner->tasks()->create(['title' => 'Etap', 'due_date' => '2026-10-20', 'status' => 'todo']);
        $foreign = ($type === 'project' ? $audit : $project)->tasks()->create(['title' => 'Obcy etap', 'status' => 'todo']);
        $data = ['title' => 'Karta '.$type, 'status' => 'todo', 'stage_task_id' => $stage->id, 'assigned_to' => $user->id];
        $this->postJson(route('board.store', [$type, $owner->id]), array_replace($data, ['stage_task_id' => $foreign->id]))->assertNotFound();
        $this->postJson(route('board.store', [$type, $owner->id]), $data)->assertCreated();
        $card = BoardTask::where('title', $data['title'])->sole();
        expect($card->due_date->format('Y-m-d'))->toBe('2026-10-20');
        $this->get(route($type === 'project' ? 'projects.show' : 'audits.show', [$owner, 'tab' => 'tasks']))->assertOk()->assertSee('data-board-new', false)->assertSee($card->title);
        $this->patchJson(route('board.status', $card), ['status' => 'in_progress', 'revision' => 1])->assertOk()->assertJsonPath('revision', 2);
        $this->patchJson(route('board.status', $card), ['status' => 'done', 'revision' => 1])->assertStatus(409);
        $this->putJson(route('board.update', $card), array_replace($data, ['revision' => 2, 'title' => 'Edytowana', 'due_date' => '2026-10-22']))->assertOk();
        expect($card->fresh()->stage_offset_days)->toBe(2);
        $this->deleteJson(route('board.destroy', $card), ['revision' => 3])->assertOk();
        $this->assertSoftDeleted($card);
    }
});

test('schedule changes preserve card offsets and deleting stages preserves cards', function () {
    [$user,$project,$audit] = boardFixture();
    foreach ([$project, $audit] as $owner) {
        $stage = $owner->tasks()->create(['title' => 'Etap', 'due_date' => '2026-10-20']);
        $card = app(BoardTaskService::class)->save($owner, ['title' => 'Offset', 'status' => 'todo', 'stage_task_id' => $stage->id, 'due_date' => '2026-10-18'], $user);
        $stage->update(['due_date' => '2026-10-25']);
        expect($card->fresh()->due_date->format('Y-m-d'))->toBe('2026-10-23');
        $stage->update(['due_date' => null]);
        expect($card->fresh()->due_date->format('Y-m-d'))->toBe('2026-10-23');
        $stage->update(['due_date' => '2026-10-30']);
        expect($card->fresh()->due_date->format('Y-m-d'))->toBe('2026-10-28');
        $stage->delete();
        expect($card->fresh()->stage_task_id)->toBeNull()->and($card->fresh()->due_date->format('Y-m-d'))->toBe('2026-10-28');
    }
});

test('assignees can move their cards but not edit delete or view inaccessible owners', function () {
    [$admin,$project,$audit] = boardFixture();
    $worker = User::factory()->create();
    $worker->assignRole(Role::findOrCreate('employee'));
    $worker->givePermissionTo(['projects.view', 'projects.schedule.view', 'audits.view']);
    $project->members()->attach($worker);
    $card = BoardTask::create(['project_id' => $project->id, 'assigned_to' => $worker->id, 'title' => 'Moje zadanie', 'status' => 'todo']);
    $foreign = BoardTask::create(['project_id' => $project->id, 'assigned_to' => $admin->id, 'title' => 'Cudze zadanie', 'status' => 'todo']);
    $this->actingAs($worker)->get(route('board.mine'))->assertOk()->assertSee('Moje zadanie')->assertDontSee('Cudze zadanie');
    $this->patchJson(route('board.status', $card), ['status' => 'done', 'revision' => 1])->assertOk();
    $this->patchJson(route('board.status', $foreign), ['status' => 'done', 'revision' => 1])->assertForbidden();
    $this->putJson(route('board.update', $card), ['title' => 'Zmiana', 'status' => 'done', 'revision' => 2])->assertForbidden();
    $this->deleteJson(route('board.destroy', $card), ['revision' => 2])->assertForbidden();
    $this->postJson(route('board.store', ['project', $project->id]), ['title' => 'Nowe', 'status' => 'todo'])->assertForbidden();
    $project->members()->detach($worker);
    $this->get(route('board.mine'))->assertOk()->assertDontSee('Moje zadanie');
    $this->patchJson(route('board.status', $card), ['status' => 'todo', 'revision' => 2])->assertForbidden();
});

test('personal board groups audits and projects while disabled modules and deleted owners disappear', function () {
    [$user,$project,$audit] = boardFixture();
    BoardTask::create(['project_id' => $project->id, 'assigned_to' => $user->id, 'title' => 'Projekt karta', 'status' => 'todo']);
    BoardTask::create(['audit_id' => $audit->id, 'assigned_to' => $user->id, 'title' => 'Audyt karta', 'status' => 'done']);
    $this->actingAs($user)->get(route('board.mine'))->assertOk()->assertSee('Projekt karta')->assertSee('Audyt karta');
    $this->get(route('board.mine', ['group' => 'audit-'.$audit->id]))->assertOk()->assertSee('Audyt karta')->assertDontSee('Projekt karta');
    $this->get(route('board.mine', ['group' => 'project-999999']))->assertNotFound();
    $project->delete();
    $this->get(route('board.mine'))->assertOk()->assertDontSee('Projekt karta');
    CompanySettings::query()->delete();
    CompanySettings::create(['name' => 'Test', 'enabled_modules' => ['projects']]);
    $this->get(route('board.mine'))->assertOk()->assertDontSee('Audyt karta');
    $this->postJson(route('board.store', ['audit', $audit->id]), ['title' => 'Nie', 'status' => 'todo'])->assertForbidden();
});

test('cards validate assignees and dates and clients cannot mutate them', function () {
    [$user,$project,$audit] = boardFixture();
    $outsider = User::factory()->create();
    $this->actingAs($user)->postJson(route('board.store', ['project', $project->id]), ['title' => 'Test', 'status' => 'todo', 'assigned_to' => $outsider->id])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
    $this->postJson(route('board.store', ['project', $project->id]), ['title' => 'Test', 'status' => 'todo', 'due_date' => 'bad'])->assertUnprocessable();
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->companies()->attach($audit->company_id);
    $card = BoardTask::create(['audit_id' => $audit->id, 'title' => 'Widoczna karta', 'status' => 'todo']);
    $this->actingAs($client)->get(route('client.audits.show', [$audit, 'tab' => 'tasks']))->assertOk()->assertSee('Widoczna karta')->assertDontSee('data-board-new', false)->assertDontSee('data-card-status', false);
    $this->postJson(route('board.store', ['audit', $audit->id]), ['title' => 'Nie', 'status' => 'todo'])->assertForbidden();
    $this->patchJson(route('board.status', $card), ['status' => 'done', 'revision' => 1])->assertForbidden();
});
