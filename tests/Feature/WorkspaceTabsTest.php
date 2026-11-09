<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('workspace tabs have a stable per login namespace and identify companies', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin'));
    $company = Company::create(['name' => 'Firma <test> & partner', 'company_type' => 'client', 'status' => 'active']);
    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-workspace-tabs', false)->assertSee('workspace-tabs.js');
    if (getenv('WORKSPACE_TABS_QA')) {
        file_put_contents(base_path('tmp/workspace-tabs-render.html'), $response->getContent());
    }
    $scope = session('workspace_tabs_scope');
    expect($scope)->toBeString()->toHaveLength(24);
    expect($scope)->not->toBe(session()->getId());
    $this->get(route('companies.show', $company))->assertOk()->assertSee('data-title="Firma &lt;test&gt; &amp; partner"', false);
    expect(session('workspace_tabs_scope'))->toBe($scope);
    $this->post(route('logout'))->assertRedirect();
    expect(session()->has('workspace_tabs_scope'))->toBeFalse();
    $this->get(route('login'))->assertOk()->assertDontSee('data-workspace-tabs', false);
});

test('successful login resets workspace tabs even if a previous namespace exists', function () {
    User::factory()->create(['email' => 'tabs@example.test']);
    $this->withSession(['workspace_tabs_scope' => 'previous-login'])
        ->post(route('login'), ['email' => 'tabs@example.test', 'password' => 'password'])->assertRedirect();
    expect(session()->has('workspace_tabs_scope'))->toBeFalse();
});
