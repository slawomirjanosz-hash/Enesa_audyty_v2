<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('dashboard and CRM render the same search header and toolbar', function () {
    Role::findOrCreate('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');

    foreach ([route('dashboard') => 'dashboard-company-search', route('crm.index', ['tab' => 'pipeline']) => 'pipeline-search'] as $url => $input) {
        $response = $this->actingAs($user)->get($url)->assertOk();
        $response->assertSeeInOrder(['class="app-search-header"', 'class="app-search-toolbar"', 'class="app-header-search"', 'id="'.$input.'"'], false)
            ->assertSee('class="app-header-search-result"', false)
            ->assertSee('.app-header-search input{box-sizing:border-box;width:100%;height:38px;', false)
            ->assertSee('aria-live="polite"', false);
    }
});

test('search layout has one shared source and no mobile reordering of dashboard search', function () {
    foreach (['dashboard', 'crm/index'] as $view) {
        $source = file_get_contents(resource_path('views/'.$view.'.blade.php'));
        expect($source)->toContain("@include('partials.header-search-styles')")
            ->not->toContain('.dashboard-search-box', '.pipeline-search-box');
    }
});
