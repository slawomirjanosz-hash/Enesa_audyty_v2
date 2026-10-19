<?php

use App\Models\CrmOpportunity;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('pipeline search is in the page header and only indexes accessible opportunities', function () {
    Role::findOrCreate('auditor');
    $user = User::factory()->create();
    $user->assignRole('auditor');
    $user->givePermissionTo('crm.view');
    $other = User::factory()->create();
    $visible = CrmOpportunity::create(['title' => 'Łódź <test>', 'stage' => 'new_lead', 'created_by' => $user->id]);
    $hidden = CrmOpportunity::create(['title' => 'Hidden confidential lead', 'stage' => 'new_lead', 'created_by' => $other->id]);
    $response = $this->actingAs($user)->get(route('crm.index', ['tab' => 'pipeline']))->assertOk();
    $response->assertSeeInOrder(['id="pipeline-search"', 'Nowa szansa', 'data-pipeline-total'], false)
        ->assertSee('data-pipeline-id="'.$visible->id.'"', false)
        ->assertDontSee('data-pipeline-id="'.$hidden->id.'"', false)
        ->assertDontSee('Hidden confidential lead')
        ->assertSee('Łódź &lt;test&gt;', false)
        ->assertDontSee('placeholder="Szukaj szans..."', false);
    $this->get(route('crm.index', ['tab' => 'companies']))->assertOk()->assertDontSee('id="pipeline-search"', false);
});

test('empty pipeline renders the search without referring to an undefined opportunity', function () {
    Role::findOrCreate('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user)->get(route('crm.index', ['tab' => 'pipeline']))
        ->assertOk()->assertSee('id="pipeline-search"', false)->assertDontSee('data-pipeline-id=', false);
});
