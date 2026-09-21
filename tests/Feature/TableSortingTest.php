<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Cylinder;
use App\Models\Document;
use App\Models\Offer;
use App\Models\Project;
use App\Models\User;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->staff->assignRole(Role::findOrCreate('admin'));
    $this->actingAs($this->staff);
});

test('server sorting keeps scopes and ignores unknown or malformed SQL keys', function () {
    Company::create(['name' => 'ZZ Visible', 'company_type' => 'supplier']);
    Company::create(['name' => 'AA Hidden', 'company_type' => 'client']);
    foreach (['name', 'name desc; DROP TABLE companies', ['name']] as $key) {
        $query = Company::where('company_type', 'supplier');
        TableSort::apply($query, Request::create('/', 'GET', ['table_sort' => $key]), ['name' => 'name']);
        expect($query->pluck('name')->all())->toBe(['ZZ Visible']);
    }
});

test('completed projects sort globally across pages by each visible column', function () {
    foreach (range(1, 23) as $i) {
        Project::create(['number' => sprintf('PR-%02d', $i), 'name' => sprintf('Projekt %02d', $i), 'status' => 'completed', 'contract_value' => $i]);
    }
    foreach (['number', 'name', 'company', 'manager', 'team', 'date', 'amount'] as $column) {
        $this->get(route('projects.index', ['completed_sort' => $column, 'completed_direction' => 'desc']))->assertOk();
    }
    $this->get(route('projects.index', ['completed_sort' => 'amount', 'completed_direction' => 'desc']))
        ->assertViewHas('completedProjects', fn ($page) => $page->first()->number === 'PR-23' && $page->count() === 20)
        ->assertSee('data-server-sort=', false)->assertSee('js/table-sort.js', false);
});

test('offers sort before pagination and support related company and owner columns', function () {
    $company = Company::create(['name' => 'Sort Client', 'company_type' => 'client']);
    foreach (range(1, 22) as $i) {
        Offer::create(['company_id' => $company->id, 'offer_number' => sprintf('OF-%02d', $i), 'offer_full_number' => sprintf('OF-%02d', $i), 'kwota_netto' => $i, 'status' => 'w_toku', 'is_template' => false]);
    }
    foreach (['number', 'title', 'company', 'owner', 'amount', 'status', 'date'] as $column) {
        $this->get(route('offers.index', ['table_sort' => $column, 'table_direction' => 'desc']))->assertOk();
    }
    $this->get(route('offers.index', ['table_sort' => 'amount', 'table_direction' => 'desc']))
        ->assertViewHas('offers', fn ($page) => (float) $page->first()->kwota_netto === 22.0);
});

test('documents sort numerically by size before pagination and keep search filters', function () {
    foreach (range(1, 52) as $i) {
        Document::create(['original_filename' => sprintf('File-%02d.pdf', $i), 'stored_path' => 'test/'.$i, 'type' => 'upload', 'size' => $i, 'uploaded_by' => $this->staff->id]);
    }
    foreach (['name', 'type', 'size', 'date', 'uploader'] as $column) {
        $this->get(route('documents.index', ['table_sort' => $column]))->assertOk();
    }
    $this->get(route('documents.index', ['table_sort' => 'size', 'table_direction' => 'desc', 'q' => 'File-']))
        ->assertViewHas('documentPage', fn ($page) => $page->first()->size === 52 && $page->total() === 52);
});

test('cylinder sorting supports latest inspections and their paginated history', function () {
    CompanySettings::create(['name' => 'Test', 'enabled_modules' => ['cylinders', 'crm']]);
    $company = Company::create(['name' => 'Client', 'company_type' => 'client']);
    $cylinder = Cylinder::create(['company_id' => $company->id, 'serial_number' => 'AAA', 'type' => 'Test']);
    $cylinder->inspections()->create(['inspected_at' => '2026-01-01', 'next_due_at' => '2027-01-01', 'inspector_name' => 'Jan', 'result' => 'no_findings', 'observations' => 'Test', 'inspector_id' => $this->staff->id]);
    foreach (['serial', 'company', 'type', 'status', 'last', 'due'] as $column) {
        $this->get(route('cylinders.index', ['table_sort' => $column]))->assertOk();
    }
    foreach (['date', 'inspector', 'result', 'notes', 'due'] as $column) {
        $this->get(route('cylinders.show', ['cylinder' => $cylinder, 'table_sort' => $column]))->assertOk();
    }
});
