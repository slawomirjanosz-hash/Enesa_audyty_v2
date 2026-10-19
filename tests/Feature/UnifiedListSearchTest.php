<?php

use App\Models\Company;
use App\Models\Offer;
use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

test('primary lists render the shared header search', function () {
    foreach ([route('dashboard'), route('suppliers.index'), route('offers.index'), route('projects.index'), route('settings.users.index'), route('board.mine'), route('documents.index'), route('audit-types.index'), route('settings.archive.index'), route('offer-forms.index'), route('pricing-catalog.index')] as $url) {
        $this->get($url)->assertOk()->assertSee('class="app-search-header"', false)->assertSee('class="app-header-search"', false);
    }
    foreach (['companies', 'suppliers', 'tasks', 'contacts', 'pipeline', 'archive'] as $tab) {
        $this->get(route('crm.index', ['tab' => $tab]))->assertOk()->assertSee('class="app-header-search"', false);
    }
});

test('offer and project search applies before pagination and keeps filters', function () {
    $company = Company::create(['name' => 'Search Client', 'company_type' => 'client', 'status' => 'active']);
    foreach (range(1, 25) as $i) {
        Project::create(['number' => 'PR-'.$i, 'name' => 'Project '.$i, 'status' => 'active', 'manager_id' => $this->admin->id]);
        Offer::create(['company_id' => $company->id, 'offer_number' => 'OF-'.$i, 'offer_full_number' => 'OF-'.$i, 'offer_title' => 'Offer '.$i, 'status' => 'w_toku', 'is_template' => false]);
    }
    $this->get(route('projects.index', ['q' => 'PR-25', 'status' => 'active']))->assertOk()
        ->assertViewHas('projects', fn ($rows) => $rows->total() === 1 && $rows->first()->number === 'PR-25')
        ->assertSee('name="status" value="active"', false);
    $this->get(route('offers.index', ['q' => 'OF-25 Search', 'status' => 'w_toku']))->assertOk()
        ->assertViewHas('offers', fn ($rows) => $rows->total() === 1 && $rows->first()->offer_full_number === 'OF-25');
    $this->get(route('offers.index', ['q' => 'OF-25', 'status' => 'wygrana']))->assertOk()
        ->assertViewHas('offers', fn ($rows) => $rows->total() === 0);
});

test('project search cannot expose projects outside the user access scope', function () {
    Role::findOrCreate('auditor');
    $user = User::factory()->create();
    $user->assignRole('auditor');
    $user->givePermissionTo('projects.view');
    Project::create(['number' => 'SECRET-1', 'name' => 'Hidden matching project', 'status' => 'active', 'manager_id' => $this->admin->id]);
    $this->actingAs($user)->get(route('projects.index', ['q' => 'SECRET']))->assertOk()
        ->assertViewHas('projects', fn ($rows) => $rows->total() === 0)->assertDontSee('Hidden matching project');
});

test('supplier search preserves scope and handles multiple terms', function () {
    Company::create(['name' => 'Pump Seller', 'company_type' => 'supplier', 'city' => 'Katowice', 'status' => 'active']);
    Company::create(['name' => 'Pump Client', 'company_type' => 'client', 'city' => 'Katowice', 'status' => 'active']);
    $this->get(route('suppliers.index', ['q' => 'Pump Katowice']))->assertOk()
        ->assertViewHas('suppliers', fn ($rows) => $rows->total() === 1)->assertDontSee('Pump Client');
});
