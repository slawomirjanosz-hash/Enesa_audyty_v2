<?php

use App\Models\AuditorCompanyAccess;
use App\Models\Company;
use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('company and pipeline opportunity previews share full history and linked active and archived CRM tasks', function () {
    $admin = User::factory()->create(['name' => 'Serwisant CRM']);
    $admin->assignRole(Role::findOrCreate('admin'));
    $company = Company::create(['name' => 'Klient pełnego podglądu', 'status' => 'active']);
    $lead = CrmOpportunity::create(['company_id' => $company->id, 'title' => 'Rozbudowany lead', 'stage' => 'contact', 'assigned_to' => $admin->id]);
    CrmActivity::create(['company_id' => $company->id, 'crm_opportunity_id' => $lead->id, 'type' => 'lead_action', 'description' => 'Pełna historia rozmowy <b>tekst</b>', 'occurred_on' => '2026-10-20', 'performed_by' => $admin->id, 'user_id' => $admin->id]);
    $active = Task::create(['company_id' => $company->id, 'crm_opportunity_id' => $lead->id, 'title' => 'Aktywne zadanie leada', 'status' => 'todo', 'assigned_to' => $admin->id, 'due_date' => '2026-11-15']);
    $archived = Task::create(['company_id' => $company->id, 'crm_opportunity_id' => $lead->id, 'title' => 'Archiwalne zadanie leada', 'status' => 'done']);
    $archived->delete();
    $response = $this->actingAs($admin)->get(route('companies.show', $company))->assertOk()
        ->assertSee('Historia działań')->assertSee('Powiązane zadania')->assertSee('Dodaj działanie')->assertSee('Dodaj zadanie')
        ->assertSee('crm-opportunity-history.js')->assertSee('20.10.2026')->assertSee('15.11.2026');
    $companyLead = $response->viewData('crmOpportunities')->sole();
    expect($companyLead->activities->sole()->description)->toBe('Pełna historia rozmowy <b>tekst</b>');
    expect($companyLead->tasks->pluck('id')->sort()->values()->all())->toBe([$active->id, $archived->id]);
    expect($companyLead->tasks->firstWhere('id', $archived->id)->trashed())->toBeTrue();
    $pipeline = $this->get(route('crm.index', ['tab' => 'pipeline']))->assertOk()->assertSee('crm-opportunity-history.js');
    foreach ([$response, $pipeline] as $page) {
        $page->assertSee('lead-notes-autogrow.js');
        expect(substr_count($page->getContent(), 'data-lead-notes-autogrow'))->toBe(2);
    }
    expect($pipeline->viewData('opportunities')->sole()->activities->pluck('id')->all())->toBe($companyLead->activities->pluck('id')->all());
    $this->get(route('companies.show', $company))->assertViewHas('crmTasks', fn ($tasks) => $tasks->pluck('id')->all() === [$active->id]);
    if (getenv('CRM_PREVIEW_QA')) {
        file_put_contents(base_path('tmp/company-opportunity-preview.html'), $response->getContent());
        file_put_contents(base_path('tmp/pipeline-opportunity-preview.html'), $pipeline->getContent());
    }
});

test('company opportunity details respect company delegation offer visibility and read-only controls', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('auditor'));
    $company = Company::create(['name' => 'Firma dostępna', 'status' => 'active']);
    $secret = Company::create(['name' => 'Firma niedostępna', 'status' => 'active']);
    AuditorCompanyAccess::create(['auditor_id' => $viewer->id, 'company_id' => $company->id, 'can_view_dashboard' => true]);
    foreach ([$company, $secret] as $item) {
        $lead = CrmOpportunity::create(['company_id' => $item->id, 'title' => 'Lead '.$item->name, 'stage' => 'new_lead']);
        CrmActivity::create(['company_id' => $item->id, 'crm_opportunity_id' => $lead->id, 'type' => 'lead_action', 'description' => 'Historia '.$item->name, 'performed_by' => $viewer->id, 'user_id' => $viewer->id]);
        Task::create(['company_id' => $item->id, 'crm_opportunity_id' => $lead->id, 'title' => 'Zadanie '.$item->name, 'status' => 'todo']);
    }
    $this->actingAs($viewer)->get(route('companies.show', $company))->assertOk()
        ->assertSee('Historia Firma dostępna')->assertSee('Zadanie Firma dostępna')
        ->assertDontSee('Historia Firma niedostępna')->assertDontSee('Zadanie Firma niedostępna')
        ->assertDontSee('onclick="editCompanyOpportunity(true)"', false)
        ->assertDontSee('id="companyOpportunityEdit"', false);
    $this->get(route('companies.show', $secret))->assertForbidden();
});

test('company lead action records information without creating a task and returns to company', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $company = Company::create(['name' => 'Klient działań', 'status' => 'active']);
    $lead = CrmOpportunity::create(['company_id' => $company->id, 'title' => 'Lead', 'stage' => 'contact']);
    $this->actingAs($admin)->patch(route('crm.opportunities.update', $lead), [
        'company_id' => $company->id, 'company_context_id' => $company->id,
        'title' => $lead->title, 'stage' => $lead->stage,
        'action_description' => 'Rozmowa na karcie klienta', 'action_due_date' => '2026-11-02', 'action_assigned_to' => $admin->id,
    ])->assertSessionHasNoErrors()->assertRedirect(route('companies.show', $company).'#crm');
    expect($lead->activities()->where('type', 'lead_action')->sole()->description)->toBe('Rozmowa na karcie klienta');
    $this->assertDatabaseCount('tasks', 0);
});
