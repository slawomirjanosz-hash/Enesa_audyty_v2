<?php

use App\Models\Audit;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('workspace member selectors hide superadmins without removing their access or existing membership', function () {
    $super = User::factory()->create(['name' => 'Administrator techniczny']);
    $super->assignRole(Role::findOrCreate('superadmin'));
    $worker = User::factory()->create(['name' => 'Pracownik zespołu']);
    $worker->assignRole(Role::findOrCreate('employee'));
    $company = Company::create(['name' => 'Firma testowa', 'company_type' => 'client', 'status' => 'active']);
    $project = Project::create(['company_id' => $company->id, 'number' => 'P/1', 'name' => 'Projekt', 'manager_id' => $worker->id, 'status' => 'active']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'A/1', 'title' => 'Audyt', 'manager_id' => $worker->id, 'status' => 'draft']);
    $project->members()->attach([$super->id, $worker->id]);
    $audit->members()->attach([$super->id, $worker->id]);
    $this->actingAs($super);
    foreach ([route('projects.index'), route('projects.show', $project), route('audits.index'), route('audits.show', $audit), route('companies.show', $company)] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);
        expect($xpath->query('//input[@type="checkbox" and @name="member_ids[]" and @value="'.$super->id.'"]')->length)->toBe(0);
        expect($xpath->query('//input[@type="checkbox" and @name="member_ids[]" and @value="'.$worker->id.'"]')->length)->toBeGreaterThan(0);
        if (in_array($url, [route('projects.show', $project), route('audits.show', $audit)])) {
            expect($xpath->query('//input[@type="hidden" and @name="member_ids[]" and @value="'.$super->id.'"]')->length)->toBe(1);
        }
    }
    $project->members()->detach($super);
    $audit->members()->detach($super);
    $this->get(route('projects.show', $project))->assertOk();
    $this->get(route('audits.show', $audit))->assertOk();
});
