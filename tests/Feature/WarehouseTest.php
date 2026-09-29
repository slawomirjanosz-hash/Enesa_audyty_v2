<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseDocumentLine;
use App\Models\WarehouseItem;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CompanySettings::create(['name' => 'Firma testowa', 'enabled_modules' => array_keys(CompanySettings::APP_MODULES)]);
    $this->operator = User::factory()->create();
    $this->operator->assignRole(Role::findOrCreate('superadmin'));
    $this->actingAs($this->operator);
    $this->item = WarehouseItem::create(['sku' => 'TEST-01', 'name' => 'Zawór testowy', 'unit' => 'szt.', 'minimum_stock' => 2]);
});

function warehousePayload(string $type, WarehouseItem $item, string $quantity, array $extra = []): array
{
    return array_replace([
        'type' => $type, 'submission_token' => (string) Str::uuid(), 'document_date' => now()->toDateString(),
        'notes' => 'Test operacji magazynowej',
        'lines' => [['item_id' => $item->id, 'quantity' => $quantity, 'unit_cost' => '10.00', 'revision' => $item->fresh()->revision]],
    ], $extra);
}

test('warehouse is opt in and clients or unprivileged staff cannot enter', function () {
    expect(CompanySettings::defaultModules())->not->toContain('warehouse');
    CompanySettings::first()->update(['enabled_modules' => CompanySettings::defaultModules()]);
    $this->get(route('warehouse.index'))->assertForbidden();
    $this->post(route('warehouse.documents.store'), warehousePayload('receipt', $this->item, '1'))->assertForbidden();
    CompanySettings::first()->update(['enabled_modules' => array_keys(CompanySettings::APP_MODULES)]);
    foreach (['client_admin', 'client_user', 'no_warehouse'] as $name) {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($name));
        $this->actingAs($user)->get(route('warehouse.index'))->assertForbidden();
        $this->get(route('warehouse.lookup'))->assertForbidden();
        $this->get(route('warehouse.export'))->assertForbidden();
    }
});

test('warehouse receipt issue correction and immutable snapshots reconcile with stock', function () {
    $receipt = warehousePayload('receipt', $this->item, '10.125');
    $this->post(route('warehouse.documents.store'), $receipt)->assertRedirect();
    expect($this->item->fresh()->quantity)->toBe('10.125')->and($this->item->fresh()->unit_cost)->toBe('10.00');
    $this->post(route('warehouse.documents.store'), $receipt)->assertRedirect();
    expect(WarehouseDocument::count())->toBe(1)->and($this->item->fresh()->quantity)->toBe('10.125');
    $this->post(route('warehouse.documents.store'), array_replace($receipt, ['notes' => 'Different request']))->assertSessionHasErrors('submission_token');
    $receiptDoc = WarehouseDocument::first();
    $this->item->update(['name' => 'Nowa nazwa']);
    $this->get(route('warehouse.documents.show', $receiptDoc))->assertOk()->assertSee('Zawór testowy');
    $this->post(route('warehouse.documents.store'), warehousePayload('issue', $this->item, '0.125'))->assertRedirect();
    expect($this->item->fresh()->quantity)->toBe('10.000');
    $this->post(route('warehouse.documents.store'), warehousePayload('adjustment', $this->item, '8.5'))->assertRedirect();
    expect($this->item->fresh()->quantity)->toBe('8.500');
    $lines = WarehouseDocumentLine::all();
    expect(round($lines->sum('quantity_change'), 3))->toBe(8.5)
        ->and(ActivityLog::where('auditable_type', WarehouseDocument::class)->exists())->toBeTrue();
    $this->put('/warehouse/documents/'.$receiptDoc->id, ['notes' => 'forged'])->assertStatus(405);
    $this->delete('/warehouse/documents/'.$receiptDoc->id)->assertStatus(405);
});

test('warehouse rejects overselling and atomically rolls back multi item documents', function () {
    $second = WarehouseItem::create(['sku' => 'TEST-02', 'name' => 'Śruba', 'unit' => 'szt.', 'minimum_stock' => 0]);
    $this->post(route('warehouse.documents.store'), warehousePayload('receipt', $this->item, '5'))->assertRedirect();
    $data = warehousePayload('issue', $this->item, '3');
    $data['lines'][] = ['item_id' => $second->id, 'quantity' => '1'];
    $this->post(route('warehouse.documents.store'), $data)->assertSessionHasErrors('lines.1.quantity');
    expect($this->item->fresh()->quantity)->toBe('5.000')->and(WarehouseDocument::count())->toBe(1);
    $data = warehousePayload('receipt', $this->item, '1');
    $data['lines'][] = $data['lines'][0];
    $this->post(route('warehouse.documents.store'), $data)->assertSessionHasErrors('lines.0.item_id');
    $this->post(route('warehouse.documents.store'), warehousePayload('issue', $this->item, '-1'))->assertSessionHasErrors('lines.0.quantity');
    $this->post(route('warehouse.documents.store'), warehousePayload('receipt', $this->item, '0.0001'))->assertSessionHasErrors('lines.0.quantity');
});

test('warehouse adjustment rejects stale counts and quantity cannot be edited through catalog', function () {
    $stale = warehousePayload('adjustment', $this->item, '8');
    $this->post(route('warehouse.documents.store'), warehousePayload('receipt', $this->item, '10'))->assertRedirect();
    $this->post(route('warehouse.documents.store'), $stale)->assertSessionHasErrors('lines.0.quantity');
    expect($this->item->fresh()->quantity)->toBe('10.000');
    $data = ['sku' => 'TEST-01', 'name' => 'Zawór', 'unit' => 'szt.', 'minimum_stock' => '3', 'quantity' => 999, 'revision' => $this->item->fresh()->revision];
    $this->put(route('warehouse.items.update', $this->item), $data)->assertRedirect();
    expect($this->item->fresh()->quantity)->toBe('10.000');
    $this->put(route('warehouse.items.update', $this->item), $data)->assertSessionHasErrors('revision');
    $data['revision'] = $this->item->fresh()->revision;
    $data['unit'] = 'kg';
    $this->put(route('warehouse.items.update', $this->item), $data)->assertSessionHasErrors('unit');
    $this->patch(route('warehouse.items.archive', $this->item), ['is_active' => 0])->assertSessionHasErrors('is_active');
    $this->post(route('warehouse.documents.store'), warehousePayload('issue', $this->item, '10'))->assertRedirect();
    $this->patch(route('warehouse.items.archive', $this->item), ['is_active' => 0])->assertRedirect();
    $this->post(route('warehouse.documents.store'), warehousePayload('receipt', $this->item, '1'))->assertSessionHasErrors('lines.0.item_id');
    $this->get(route('warehouse.items.show', $this->item))->assertOk()->assertSee('Archiwum');
});

test('warehouse permissions distinguish viewing receipts issues and adjustments', function () {
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('warehouse_viewer');
    $role->givePermissionTo(['warehouse.view', 'warehouse.receive']);
    $viewer->assignRole($role);
    $this->actingAs($viewer)->get(route('warehouse.index'))->assertOk();
    $this->get(route('warehouse.items.create'))->assertForbidden();
    $this->get(route('warehouse.documents.create', 'receipt'))->assertOk();
    $this->get(route('warehouse.documents.create', 'issue'))->assertForbidden();
    $this->post(route('warehouse.documents.store'), warehousePayload('receipt', $this->item, '1'))->assertRedirect();
    foreach (['issue', 'adjustment'] as $type) {
        $this->post(route('warehouse.documents.store'), warehousePayload($type, $this->item, '1'))->assertForbidden();
    }
});

test('warehouse project and supplier assignment obey existing access scopes', function () {
    $project = Project::create(['number' => 'SECRET-01', 'name' => 'Projekt chroniony', 'status' => 'active', 'manager_id' => $this->operator->id]);
    $supplier = Company::create(['name' => 'Dostawca', 'company_type' => 'supplier']);
    $this->post(route('warehouse.documents.store'), warehousePayload('receipt', $this->item, '10', ['supplier_id' => $supplier->id]))->assertRedirect();
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('warehouse_worker');
    $role->givePermissionTo(['warehouse.view', 'warehouse.issue', 'projects.view']);
    $viewer->assignRole($role);
    $this->actingAs($viewer)->get(route('warehouse.documents.create', 'issue'))->assertOk()->assertDontSee('SECRET-01');
    $this->post(route('warehouse.documents.store'), warehousePayload('issue', $this->item, '1', ['project_id' => $project->id]))->assertNotFound();
    $project->members()->attach($viewer->id);
    $this->post(route('warehouse.documents.store'), warehousePayload('issue', $this->item, '1', ['project_id' => $project->id]))->assertRedirect();
    expect(WarehouseDocument::where('type', 'issue')->first()->project_label)->toContain('SECRET-01');
});

test('warehouse tables sort across pagination and retain filters', function () {
    for ($i = 0; $i < 35; $i++) {
        WarehouseItem::create(['sku' => sprintf('SORT-%02d', $i), 'name' => sprintf('Pozycja %02d', $i), 'unit' => 'kg', 'minimum_stock' => $i, 'category' => 'Test']);
    }
    $columns = ['sku', 'name', 'category', 'location', 'unit', 'quantity', 'minimum_stock', 'unit_cost', 'stock_value'];
    foreach ($columns as $column) {
        foreach (['asc', 'desc'] as $direction) {
            $this->get(route('warehouse.index', ['category' => 'Test', 'table_sort' => $column, 'table_direction' => $direction]))->assertOk();
        }
    }
    $response = $this->get(route('warehouse.index', ['category' => 'Test', 'table_sort' => 'minimum_stock', 'table_direction' => 'desc']));
    expect($response->viewData('items')->first()->sku)->toBe('SORT-34');
    $response->assertSee('table_direction=desc', false)->assertSee('category=Test', false)->assertDontSee('TEST-01');
    $this->get(route('warehouse.index', ['table_sort' => 'DROP TABLE users', 'table_direction' => 'evil']))->assertOk();
    $this->get(route('warehouse.lookup', ['q' => 'SORT-34']))->assertOk()->assertJsonPath('0.sku', 'SORT-34');
});

test('warehouse pages render and csv export neutralizes spreadsheet formulas', function () {
    $this->get(route('warehouse.items.create'))->assertOk();
    $this->get(route('warehouse.items.edit', $this->item))->assertOk();
    $this->get(route('warehouse.items.show', $this->item))->assertOk();
    foreach (array_keys(WarehouseDocument::TYPES) as $type) {
        $this->get(route('warehouse.documents.create', ['type' => $type, 'item' => $this->item->id]))->assertOk();
    }
    $this->post(route('warehouse.items.store'), ['sku' => '=FORMULA', 'name' => '+FORMULA', 'unit' => 'kg', 'minimum_stock' => 0])->assertRedirect();
    $this->get(route('warehouse.documents.index'))->assertOk();
    $csv = $this->get(route('warehouse.export'))->assertOk()->streamedContent();
    expect($csv)->toContain("'=FORMULA", "'+FORMULA");
});

test('warehouse receipt valuation uses weighted cost and issues retain that price', function () {
    $this->post(route('warehouse.documents.store'), warehousePayload('receipt', $this->item, '10'))->assertRedirect();
    $data = warehousePayload('receipt', $this->item, '10');
    $data['lines'][0]['unit_cost'] = '20';
    $this->post(route('warehouse.documents.store'), $data)->assertRedirect();
    expect($this->item->fresh()->unit_cost)->toBe('15.00');
    $this->post(route('warehouse.documents.store'), warehousePayload('issue', $this->item, '5'))->assertRedirect();
    expect(WarehouseDocument::where('type', 'issue')->first()->lines->first()->unit_cost)->toBe('15.00');
    foreach (['number', 'type', 'document_date', 'author_name', 'project_label', 'supplier_name', 'reference', 'created_at'] as $column) {
        foreach (['asc', 'desc'] as $direction) {
            $this->get(route('warehouse.documents.index', ['table_sort' => $column, 'table_direction' => $direction]))->assertOk();
        }
    }
    $result = $this->get(route('warehouse.documents.index', ['table_sort' => 'type', 'table_direction' => 'asc']));
    expect($result->viewData('documents')->first()->type)->toBe('receipt');
});
