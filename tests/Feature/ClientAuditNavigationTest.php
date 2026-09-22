<?php

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('client ISO sidebar puts workspace links before chapters without duplicate tabs', function () {
    $company = Company::create(['name' => 'Klient ISO', 'company_type' => 'client', 'status' => 'active']);
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_admin'));
    $client->companies()->attach($company);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'NAV/1', 'title' => 'ISO', 'status' => 'draft']);
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => $type->name, 'status' => 'draft']);
    foreach (['overview', 'schedule', 'documents', 'iso50001'] as $tab) {
        $response = $this->actingAs($client)->get(route('client.audits.show', ['audit' => $audit, 'tab' => $tab]))->assertOk();
        $response->assertSeeInOrder(['Podgląd', 'Harmonogram i zadania', 'Dokumenty', '1. Wstęp']);
        $response->assertDontSee('<nav class="aw-tabs"', false)->assertSee('data-client-audit-menu', false);
        $html = $response->getContent();
        preg_match('/<nav class="sidebar-nav" data-client-audit-menu>(.*?)<\/nav>/s', $html, $matches);
        expect($matches[1])->not->toContain('Paszporty')->not->toContain('> ISO 50001</a>');
    }
    $outsider = User::factory()->create();
    $outsider->assignRole(Role::findOrCreate('client_admin'));
    $this->actingAs($outsider)->get(route('client.audits.show', $audit))->assertNotFound();
});

test('energy passports are an audit type with their own entry point', function () {
    $type = AuditType::where('slug', 'energy-passports')->firstOrFail();
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin'));
    $this->actingAs($user)->get(route('audit-types.show', $type))->assertRedirect(route('energy-passports.index'));
    $this->get(route('audit-types.index'))->assertOk()->assertSee('Paszporty energetyczne');
});
