<?php

use App\Models\CompanySettings;
use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\User;
use App\Services\QuestionnairePendingActions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('questionnaire status highlights only current approvals and document', function () {
    $record = new IsoPlantProfile(['client_approval' => ['user_id' => 1], 'auditor_approval' => ['user_id' => 2], 'document_id' => 7]);
    $render = fn ($stale) => view('audits.partials.questionnaire-status', ['statusRecord' => $record, 'statusStale' => $stale])->render();
    expect($render(false))->toContain('data-questionnaire-status="3" data-reached="true"');
    expect($render(true))->toContain('data-questionnaire-status="3" data-reached="false"')
        ->toContain('data-questionnaire-status="1" data-reached="false"');
    $record->client_approval = null;
    expect($render(false))->toContain('data-questionnaire-status="3" data-reached="false"');
});

test('dashboard marks submitted questionnaires and links directly to them within visible audits', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    CompanySettings::firstOrCreate([], ['name' => 'ENESA'])->update(['enabled_modules' => ['dashboard', 'crm', 'audits', 'client_zone']]);
    $audit->company->update(['show_in_dashboard' => true]);
    $this->actingAs($client)->post(route('client.audits.factors.update', [$audit, $profile]), factorInput($profile))->assertRedirect()->assertSessionHasNoErrors();
    $service = app(QuestionnairePendingActions::class);
    expect($service->forAudits(collect()))->toBe([]);
    $pending = $service->forAudits(collect([$audit]));
    expect($pending[$audit->id][0]['section'])->toBe('4.1');
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('questionnaire_dashboard_viewer');
    $role->givePermissionTo([
        Permission::findOrCreate('dashboard.view'),
        Permission::findOrCreate('crm.view'),
    ]);
    $viewer->assignRole($role);
    $this->actingAs($viewer)->get(route('dashboard'))->assertOk()
        ->assertDontSee('client-tile questionnaire-attention', false)
        ->assertDontSee(route('audits.factors.show', [$audit, $profile]), false);
    $this->actingAs($staff)->get(route('dashboard'))->assertOk()
        ->assertSee('client-tile questionnaire-attention', false)
        ->assertSee(route('audits.factors.show', [$audit, $profile]), false);
    IsoFactorReview::where('audit_id', $audit->id)->update(['status' => 'approved']);
    expect($service->forAudits(collect([$audit])))->toBe([]);
    $this->get(route('dashboard'))->assertOk()->assertDontSee('client-tile questionnaire-attention', false);
});

test('pending actions exclude historical profiles', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $profile->update(['status' => 'submitted']);
    $latest = $profile->replicate();
    $latest->revision++;
    $latest->status = 'editing';
    $latest->client_approval = null;
    $latest->save();
    expect(app(QuestionnairePendingActions::class)->forAudits(collect([$audit])))->toBe([]);
});
