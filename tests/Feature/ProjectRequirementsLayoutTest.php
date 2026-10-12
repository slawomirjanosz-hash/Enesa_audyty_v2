<?php

use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('requirements prices have separate unbroken lines and numeric sorting without leaking hidden prices', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('requirements_layout_viewer');
    $role->givePermissionTo(collect(['system.full_access', 'projects.view', 'projects.requirements.view', 'projects.requirements.material_prices.view'])->map(fn ($name) => Permission::findOrCreate($name)));
    $viewer->assignRole($role);
    $project = Project::create(['number' => 'LAYOUT/1', 'name' => 'Układ materiałów', 'status' => 'active', 'manager_id' => $admin->id]);
    $project->members()->attach($viewer);
    foreach (['material' => 300000, 'service' => 9876.54] as $type => $cost) {
        $project->requirements()->create(['type' => $type, 'name' => $type, 'quantity' => 2, 'unit' => 'szt.', 'estimated_cost' => $cost, 'status' => 'planned']);
    }
    foreach ([$viewer, $admin] as $user) {
        $response = $this->actingAs($user)->get(route('projects.show', [$project, 'tab' => 'requirements']))->assertOk();
        $response->assertSee('150 000,00 zł / szt.')->assertSee('Wartość: 300 000,00 zł')
            ->assertSee('class="requirement-price-cell" data-sort-value="150000"', false)
            ->assertSee('class="requirement-total"', false);
        if ($user->is($viewer)) {
            $response->assertDontSee('Wartość: 9 876,54 zł')->assertDontSee('data-sort-value="4938.27"', false);
        }
    }
});
