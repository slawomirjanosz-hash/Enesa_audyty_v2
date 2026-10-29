<?php

use App\Models\Audit;
use App\Models\AuditorCompanyAccess;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('workspace lists sort every data column in both directions and preserve section boundaries', function (string $kind) {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin'));
    $model = $kind === 'projects' ? Project::class : Audit::class;
    $name = $kind === 'projects' ? 'name' : 'title';
    $finished = $kind === 'projects' ? 'completed' : 'done';
    $active = $kind === 'projects' ? 'active' : 'draft';
    $otherActive = $kind === 'projects' ? 'on_hold' : 'in_progress';
    $records = [];
    foreach (['A', 'Z'] as $index => $letter) {
        $company = Company::create(['name' => $letter.' firma']);
        $manager = User::factory()->create(['name' => $letter.' kierownik']);
        foreach (['active', 'completed'] as $section) {
            $record = $model::create(['company_id' => $company->id, 'manager_id' => $manager->id,
                'number' => $letter.$section, $name => $letter.' nazwa',
                'status' => $section === 'completed' ? $finished : ($index === 0 ? $active : $otherActive),
                'contract_value' => $index + 1, 'start_date' => '2026-01-0'.($index + 1), 'end_date' => '2026-02-0'.($index + 1)]);
            if ($index === 1) {
                $record->members()->attach($manager);
            }
            $records[$section][] = $record->id;
        }
    }
    $this->actingAs($user);
    $keys = ['number', 'name', 'company', 'manager', 'team', 'date', 'end', 'status'];
    if ($kind === 'projects') {
        $keys[] = 'amount';
    }
    foreach (['active', 'completed'] as $section) {
        $variable = $section === 'active' ? $kind : ($kind === 'projects' ? 'completedProjects' : 'completedAudits');
        foreach ($keys as $key) {
            foreach (['asc', 'desc'] as $direction) {
                $response = $this->get(route($kind.'.index', ['view' => 'list', $section.'_sort' => $key, $section.'_direction' => $direction]));
                $response->assertOk()->assertSee('data-server-sort=', false)->assertSee('data-sortable="false"', false);
                $expected = $direction === 'asc' || ($section === 'completed' && $key === 'status') ? $records[$section] : array_reverse($records[$section]);
                expect($response->viewData($variable)->modelKeys())->toBe($expected);
            }
        }
    }
    $this->get(route($kind.'.index', ['view' => 'tiles']))->assertOk()->assertSee('class="workspace-tile"', false)->assertDontSee('data-server-sort=', false);
    $this->get(route($kind.'.index', ['view' => 'list', 'active_sort' => 'invalid sql']))->assertOk();
})->with(['projects', 'audits']);

test('both workspace modes keep company and membership scopes including completed records', function (string $kind, string $view) {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($kind === 'audits' ? 'auditor' : 'employee'));
    $user->givePermissionTo(Permission::findOrCreate($kind.'.view'));
    $model = $kind === 'projects' ? Project::class : Audit::class;
    $name = $kind === 'projects' ? 'name' : 'title';
    $company = Company::create(['name' => 'Obca firma']);
    $record = $model::create(['company_id' => $company->id, 'number' => 'PRIVATE', $name => 'Prywatna pozycja', 'status' => $kind === 'projects' ? 'completed' : 'done']);
    $this->actingAs($user)->get(route($kind.'.index', ['view' => $view, 'completed_sort' => 'name']))->assertOk()->assertDontSee('Prywatna pozycja');
    if ($kind === 'projects') {
        $record->members()->attach($user);
    } else {
        AuditorCompanyAccess::create(['auditor_id' => $user->id, 'company_id' => $company->id, 'can_view_audits' => true]);
    }
    $this->get(route($kind.'.index', ['view' => $view]))->assertOk()->assertSee('Prywatna pozycja')->assertDontSee('Wartość netto');
})->with(['projects', 'audits'])->with(['tiles', 'list']);
