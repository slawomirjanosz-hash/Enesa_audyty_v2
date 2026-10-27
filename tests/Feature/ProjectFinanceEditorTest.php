<?php

use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->manager = User::factory()->create(['individual_permissions' => true]);
    $this->manager->assignRole(Role::findOrCreate('admin'));
    $this->manager->givePermissionTo(['projects.view', 'projects.edit', 'projects.finances.view', 'projects.finances.manage']);
    $this->project = Project::create([
        'number' => 'FIN-EDITOR-001', 'name' => 'Projekt edycji finansów', 'status' => 'active',
        'manager_id' => $this->manager->id, 'created_by' => $this->manager->id, 'contract_value' => 100000,
    ]);
    $this->invoice = $this->project->financialEntries()->create([
        'type' => 'invoice', 'name' => 'Faktura etapowa', 'document_number' => 'FV/2026/01',
        'amount' => 12000, 'status' => 'paid', 'entry_date' => '2026-10-01', 'payment_date' => '2026-10-15',
        'source' => 'excel_import', 'import_fingerprint' => str_repeat('a', 64), 'import_row_order' => 5,
        'notes' => 'Opis faktury', 'created_by' => $this->manager->id,
    ]);
});

function projectFinanceEditorConfig(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $element = (new DOMXPath($document))->query('//*[@id="project-finance-editor-config"]')->item(0);

    return json_decode($element->textContent, true, 512, JSON_THROW_ON_ERROR);
}

test('finance edit and invoice copy use one dialog outside the scrolling register', function () {
    $cost = $this->project->financialEntries()->create([
        'type' => 'cost', 'name' => 'Koszt', 'amount' => 500, 'status' => 'planned', 'entry_date' => '2026-10-02', 'created_by' => $this->manager->id,
    ]);
    $html = $this->actingAs($this->manager)->get(route('projects.show', [$this->project, 'tab' => 'finances']))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//dialog[@id="project-finance-dialog"]')->length)->toBe(1);
    expect($xpath->query('//dialog[@id="project-finance-dialog"]/ancestor::table')->length)->toBe(0);
    expect($xpath->query('//dialog[@id="project-finance-dialog"]/ancestor::section')->length)->toBe(0);
    expect($xpath->query('//*[@data-finance-editor-open="copy"]')->length)->toBe(1);
    expect($xpath->query('//*[@data-finance-editor-open="copy"]')->item(0)->getAttribute('data-finance-entry'))->toBe((string) $this->invoice->id);
    expect($xpath->query('//*[@data-finance-editor-open="edit"]')->length)->toBe(2);
    expect($xpath->query('//details[contains(@class,"finance-edit")]')->length)->toBe(0);
    $config = projectFinanceEditorConfig($html);
    expect($config['entries'][$this->invoice->id]['amount'])->toBe('12000.00');
    expect($config['entries'][$cost->id]['type'])->toBe('cost');
    expect($config['store_url'])->toBe(route('projects.finances.store', $this->project));
});

test('saving changed invoice copy creates a clean entry and leaves the original unchanged', function () {
    $before = $this->invoice->fresh()->getAttributes();
    $this->actingAs($this->manager)->post(route('projects.finances.store', $this->project), [
        'type' => 'invoice', 'name' => 'Faktura etap drugi', 'document_number' => 'FV/2026/02',
        'amount' => 14500, 'status' => 'planned', 'entry_date' => '2026-11-01', 'payment_date' => '2026-11-15',
        'notes' => 'Zmienione dane kopii', '_finance_editor_mode' => 'copy', '_finance_editor_entry' => $this->invoice->id,
        'source' => 'excel_import', 'import_fingerprint' => 'nie wolno kopiować', 'created_by' => 999999,
    ])->assertSessionHasNoErrors()->assertRedirect(route('projects.show', [$this->project, 'tab' => 'finances']));
    expect($this->invoice->fresh()->getAttributes())->toBe($before);
    $copy = $this->project->financialEntries()->where('id', '!=', $this->invoice->id)->firstOrFail();
    expect($copy->name)->toBe('Faktura etap drugi')->and($copy->amount)->toBe('14500.00')
        ->and($copy->status)->toBe('planned')->and($copy->document_number)->toBe('FV/2026/02')
        ->and($copy->entry_date->format('Y-m-d'))->toBe('2026-11-01')
        ->and($copy->payment_date->format('Y-m-d'))->toBe('2026-11-15')
        ->and($copy->import_fingerprint)->toBeNull()->and($copy->import_row_order)->toBeNull()
        ->and($copy->source)->toBe('manual')->and($copy->created_by)->toBe($this->manager->id)
        ->and($copy->supplier_company_id)->toBeNull()->and($copy->financeGroup->name)->toBe('Wystawione');
});

test('finance dialog edits preserve entry identity and use the scoped update endpoint', function () {
    $this->actingAs($this->manager)->patch(route('projects.finances.update', [$this->project, $this->invoice]), [
        'type' => 'invoice', 'name' => 'Poprawiona faktura', 'amount' => 13000, 'status' => 'issued', 'entry_date' => '2026-10-03',
    ])->assertSessionHasNoErrors();
    expect($this->project->financialEntries()->count())->toBe(1);
    expect($this->invoice->fresh()->name)->toBe('Poprawiona faktura');
    $other = Project::create(['number' => 'FIN-EDITOR-002', 'name' => 'Inny projekt', 'status' => 'active', 'manager_id' => $this->manager->id, 'created_by' => $this->manager->id]);
    $this->patch(route('projects.finances.update', [$other, $this->invoice]), [])->assertNotFound();
});

test('finance view permission alone never exposes the editor or permits saving a copy', function () {
    $this->manager->revokePermissionTo('projects.finances.manage');
    $this->actingAs($this->manager)->get(route('projects.show', [$this->project, 'tab' => 'finances']))->assertOk()
        ->assertDontSee('id="project-finance-editor-config"', false)->assertDontSee('data-finance-editor-open=', false);
    $this->post(route('projects.finances.store', $this->project), [])->assertForbidden();
    expect($this->project->financialEntries()->count())->toBe(1);
});

test('financial editors remain scoped to project membership', function () {
    $outsider = User::factory()->create(['individual_permissions' => true]);
    $outsider->assignRole('admin');
    $outsider->givePermissionTo(['projects.view', 'projects.edit', 'projects.finances.view', 'projects.finances.manage']);
    $this->actingAs($outsider)->get(route('projects.show', $this->project))->assertForbidden();
    $this->post(route('projects.finances.store', $this->project), [])->assertForbidden();
    $this->patch(route('projects.finances.update', [$this->project, $this->invoice]), [])->assertForbidden();
});

test('invalid copied invoice keeps user changes for reopening the full dialog without creating an entry', function () {
    $url = route('projects.show', [$this->project, 'tab' => 'finances']);
    $this->actingAs($this->manager)->from($url)->post(route('projects.finances.store', $this->project), [
        'type' => 'invoice', 'name' => 'Dane do poprawy', 'amount' => -1, 'status' => 'planned', 'entry_date' => '2026-11-01',
        '_finance_editor_mode' => 'copy', '_finance_editor_entry' => $this->invoice->id,
    ])->assertSessionHasErrors('amount');
    $html = $this->get($url)->assertOk()->getContent();
    $config = projectFinanceEditorConfig($html);
    expect($config['recovery']['mode'])->toBe('copy')->and($config['recovery']['id'])->toBe($this->invoice->id)
        ->and($config['recovery']['values']['name'])->toBe('Dane do poprawy');
    expect($this->project->financialEntries()->count())->toBe(1);
});
