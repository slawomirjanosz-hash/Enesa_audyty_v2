<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Offer;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\ProjectWorkOrder;
use App\Models\User;
use App\Services\ProjectWorkOrderDocument;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole(Role::findOrCreate('superadmin'));
    $this->project = Project::create(['number' => 'WO/2026/014', 'name' => 'Wewnętrzny projekt', 'status' => 'active', 'manager_id' => $this->admin->id]);
    $this->supplier = Company::create(['company_type' => 'supplier', 'name' => 'Wykonawca testowy', 'nip' => '1234567890', 'status' => 'active']);
    $this->customer = Company::create(['company_type' => 'client', 'name' => 'Poufny klient', 'status' => 'active']);
    $this->project->update(['company_id' => $this->customer->id]);
    CompanySettings::create(['name' => 'Firma testowa', 'primary_color' => '#203D60', 'address' => 'Techniczna 10', 'city' => 'Cieszyn', 'nip' => '9876543210']);
    $this->data = [
        'revision' => 0, 'title' => 'Dostawa i montaż Tap Off', 'supplier_company_id' => $this->supplier->id,
        'issued_on' => '2026-11-24', 'place' => 'G50', 'offer_reference' => 'SAT-2705-00', 'offer_date' => '2026-11-04',
        'our_contact' => 'Jan Zamawiający', 'supplier_contact' => 'Piotr Wykonawca',
        'vat_rate' => 23, 'advance' => '17400.00', 'notes' => 'Wewnętrzna poufna notatka',
        'text_sections' => [
            ['name' => 'Zakres prac', 'content' => '<p>Montaż i podłączenie urządzeń.</p>', 'placement' => 'before_price'],
            ['name' => 'Termin i warunki płatności', 'content' => '<p>Po wpłacie zaliczki. Płatność 14 dni.</p>', 'placement' => 'after_price'],
        ],
        'price_sections' => [['name' => 'Wynagrodzenie ryczałtowe', 'rows' => [
            ['opis' => 'Zakup i montaż', 'jedn' => 'kpl', 'ilosc' => 1, 'cena_jedn' => '22500.00', 'net_cents' => 1],
        ]]],
    ];
    $this->actingAs($this->admin);
});

function saveWorkOrder($test): ProjectWorkOrder
{
    $test->post(route('projects.work-orders.store', $test->project), $test->data)->assertSessionHasNoErrors()->assertRedirect();

    return ProjectWorkOrder::latest('id')->firstOrFail();
}

test('work order creation renders offer styled editor and calculates amounts independently of offers and finance', function () {
    $this->get(route('projects.work-orders.create', $this->project))->assertOk()->assertSee('ZLECENIE REALIZACJI PRAC')->assertSee('Zamawiający')
        ->assertSee('wo-editor-data')->assertSee('text_sections', false);
    $order = saveWorkOrder($this);
    expect($order->net_cents)->toBe(2250000)->and($order->vat_cents)->toBe(517500)->and($order->advance_cents)->toBe(1740000)
        ->and($order->number)->toStartWith('ZLE/')->and(Offer::count())->toBe(0)->and($this->project->financialEntries()->count())->toBe(0);
    $this->get(route('projects.show', [$this->project, 'tab' => 'work-orders']))->assertOk()->assertSee($order->number)->assertSee('Zlecenia realizacji prac');
    $this->get(route('projects.work-orders.show', [$this->project, $order]))->assertOk()->assertSee('Netto:')->assertSee('22 500,00');
    $this->put(route('projects.work-orders.update', [$this->project, $order]), array_replace($this->data, ['revision' => 1, 'title' => 'Zmieniony zakres']))
        ->assertSessionHasNoErrors();
    $this->put(route('projects.work-orders.update', [$this->project, $order]), array_replace($this->data, ['revision' => 1]))->assertConflict();
});

test('issued orders freeze snapshots and require recorded contractor confirmation before work starts', function () {
    $order = saveWorkOrder($this);
    $transition = route('projects.work-orders.transition', [$this->project, $order]);
    $this->patch($transition, ['status' => 'issued', 'revision' => 1])->assertSessionHasNoErrors();
    $this->supplier->update(['name' => 'Nowa nazwa wykonawcy']);
    CompanySettings::first()->update(['name' => 'Nowa nazwa wystawcy']);
    $this->get(route('projects.work-orders.edit', [$this->project, $order]))->assertConflict();
    $this->put(route('projects.work-orders.update', [$this->project, $order]), array_replace($this->data, ['revision' => 2]))->assertConflict();
    $this->delete(route('projects.work-orders.destroy', [$this->project, $order]))->assertConflict();
    $this->patch($transition, ['status' => 'in_progress', 'revision' => 2])->assertSessionHasErrors('status');
    $this->patch($transition, ['status' => 'confirmed', 'revision' => 2])->assertSessionHasErrors('confirmation_reference');
    $this->patch($transition, ['status' => 'confirmed', 'revision' => 2, 'confirmation_reference' => 'E-mail z 24.11.2026'])->assertSessionHasNoErrors();
    expect($order->fresh()->supplier_snapshot['name'])->toBe('Wykonawca testowy')->and($order->fresh()->issuer_snapshot['name'])->toBe('Firma testowa');
    $this->patch($transition, ['status' => 'in_progress', 'revision' => 3])->assertSessionHasNoErrors();
    $this->patch($transition, ['status' => 'received', 'revision' => 4])->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe('received');
});

test('pdf and word share the order scope prices and contacts without client names private notes or offer validity', function () {
    $order = saveWorkOrder($this);
    $documents = app(ProjectWorkOrderDocument::class);
    $html = $documents->html($order);
    expect($html)->toContain('ROBOCZE', 'Zamawiający', 'Wykonawca', 'SAT-2705-00', '27 675,00', '17 400,00')
        ->not->toContain('Poufny klient', 'Wewnętrzna poufna notatka', 'Oferta handlowa przygotowana', 'Oferta ważna');
    $this->get(route('projects.work-orders.document', [$this->project, $order]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $response = $this->get(route('projects.work-orders.document', [$this->project, $order, 'format' => 'docx']))->assertOk();
    expect(substr($response->getContent(), 0, 2))->toBe('PK');
    if ($folder = getenv('WORK_ORDER_QA')) {
        file_put_contents($folder.'/order.pdf', $documents->pdf($order));
        file_put_contents($folder.'/order.docx', $response->getContent());
        file_put_contents($folder.'/form.html', $this->get(route('projects.work-orders.edit', [$this->project, $order]))->getContent());
        file_put_contents($folder.'/show.html', $this->get(route('projects.work-orders.show', [$this->project, $order]))->getContent());
    }
});

test('templates copies material selections and source protocol remain project scoped', function () {
    $stage = $this->project->tasks()->create(['title' => 'Montaż', 'status' => 'todo', 'priority' => 'medium']);
    $item = $this->project->requirements()->create(['type' => 'service', 'name' => 'Montaż z materiałów', 'quantity' => 2, 'unit' => 'kpl', 'estimated_cost' => 22500, 'status' => 'planned', 'supplier_company_id' => $this->supplier->id]);
    $response = $this->get(route('projects.work-orders.create', [$this->project, 'requirement_ids' => [$item->id]]))->assertOk();
    expect($response->viewData('order')->price_sections[0]['rows'][0]['cena_jedn'])->toEqual(11250.0);
    $order = saveWorkOrder($this);
    $this->get(route('projects.work-orders.create', [$this->project, 'source_id' => $order->id]))->assertOk()->assertSee($order->title);
    $this->get(route('projects.protocols.create', [$this->project, 'work_order_id' => $order->id]))->assertUnprocessable();
    $this->patch(route('projects.work-orders.transition', [$this->project, $order]), ['status' => 'issued', 'revision' => 1])->assertSessionHasNoErrors();
    $this->get(route('projects.protocols.create', [$this->project, 'work_order_id' => $order->id]))->assertOk()->assertSee($order->number)->assertSee('Zakup i montaż');
    $other = Project::create(['number' => 'WO/OTHER', 'name' => 'Inny projekt', 'status' => 'active']);
    foreach (['show', 'edit', 'document'] as $action) {
        $this->get(route('projects.work-orders.'.$action, [$other, $order]))->assertNotFound();
    }
    $this->get(route('projects.work-orders.create', [$other, 'source_id' => $order->id]))->assertNotFound();
    $this->get(route('projects.work-orders.create', [$other, 'requirement_ids' => [$item->id]]))->assertNotFound();
    $this->get(route('projects.protocols.create', [$other, 'work_order_id' => $order->id]))->assertNotFound();
    $this->post(route('projects.work-orders.store', $other), array_replace($this->data, ['stage_ids' => [$stage->id]]))->assertSessionHasErrors('stage_ids');
});

test('work order readers cannot mutate and project nonmembers cannot read orders or templates', function () {
    $order = saveWorkOrder($this);
    $reader = User::factory()->create();
    $role = Role::findOrCreate('work-order-reader');
    $role->givePermissionTo(Permission::findOrCreate('projects.view'), Permission::findOrCreate('projects.work_orders.view'));
    $reader->assignRole($role);
    $this->project->members()->attach($reader);
    $this->actingAs($reader)->get(route('projects.work-orders.show', [$this->project, $order]))->assertOk();
    $this->get(route('projects.work-orders.create', $this->project))->assertForbidden();
    $this->post(route('projects.work-orders.store', $this->project), $this->data)->assertForbidden();
    $this->put(route('projects.work-orders.update', [$this->project, $order]), $this->data)->assertForbidden();
    $this->patch(route('projects.work-orders.transition', [$this->project, $order]), ['status' => 'issued', 'revision' => 1])->assertForbidden();
    $this->delete(route('projects.work-orders.destroy', [$this->project, $order]))->assertForbidden();
    $this->project->members()->detach($reader);
    $this->get(route('projects.work-orders.document', [$this->project, $order]))->assertForbidden();
});

test('draft deletion preserves numbering and untrusted html or totals never enter the document', function () {
    $this->data['text_sections'][0]['content'] = '<p onclick="alert(1)">Zakres</p><img src="https://evil.test/x"><script>alert(1)</script>';
    $order = saveWorkOrder($this);
    expect($order->text_sections[0]['content'])->not->toContain('onclick', '<img', '<script');
    $number = $order->number;
    $this->delete(route('projects.work-orders.destroy', [$this->project, $order]))->assertRedirect();
    $this->assertSoftDeleted($order);
    expect(saveWorkOrder($this)->number)->not->toBe($number);
    $this->post(route('projects.work-orders.store', $this->project), array_replace($this->data, ['advance' => '999999999.00']))->assertSessionHasErrors('advance');
});

test('saved templates cannot issue and a linked protocol keeps its work order relation', function () {
    $this->data['is_template'] = true;
    $template = saveWorkOrder($this);
    $this->patch(route('projects.work-orders.transition', [$this->project, $template]), ['revision' => 1, 'status' => 'issued'])->assertSessionHasErrors('status');
    $copy = $this->get(route('projects.work-orders.create', [$this->project, 'source_id' => $template->id]))->assertOk()->viewData('order');
    expect($copy->exists)->toBeFalse()->and($copy->is_template)->toBeNull()->and($copy->number)->toBeNull();
    $this->data['is_template'] = false;
    $order = saveWorkOrder($this);
    $this->patch(route('projects.work-orders.transition', [$this->project, $order]), ['revision' => 1, 'status' => 'issued'])->assertSessionHasNoErrors();
    $protocolData = ['revision' => 0, 'work_order_id' => $order->id, 'supplier_company_id' => $this->supplier->id,
        'direction' => 'supplier', 'title' => 'Odbiór montażu', 'acceptance_date' => null, 'kind' => 'partial', 'outcome' => 'accepted',
        'invoice_decision' => 'no', 'description' => 'Wykonano montaż.', 'items_mode' => 'manual', 'manual_items_description' => 'Zakup i montaż'];
    $this->post(route('projects.protocols.store', $this->project), $protocolData)->assertSessionHasNoErrors()->assertRedirect();
    $protocol = ProjectProtocol::firstOrFail();
    expect((int) $protocol->work_order_id)->toBe($order->id)->and($protocol->acceptance_date)->toBeNull();
    $this->put(route('projects.protocols.update', [$this->project, $protocol]), array_replace($protocolData, ['revision' => 1, 'work_order_id' => null]))->assertSessionHasErrors('work_order_id');
    $this->post(route('projects.protocols.store', $this->project), array_replace($protocolData, ['supplier_company_id' => $this->customer->id]))->assertSessionHasErrors('supplier_company_id');
});
