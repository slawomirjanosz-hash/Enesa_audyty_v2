<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findOrCreate('admin'));
    $this->project = Project::create(['number' => 'PR/2026/014', 'name' => 'Modernizacja instalacji technologicznej', 'status' => 'active', 'manager_id' => $this->admin->id]);
    $this->supplier = Company::create(['company_type' => 'supplier', 'name' => 'Przykładowy dostawca Sp. z o.o.', 'status' => 'active', 'nip' => '1234567890', 'address' => 'Przemysłowa 12', 'city' => 'Katowice']);
    CompanySettings::create(['name' => 'Firma testowa', 'primary_color' => '#203D60', 'address' => 'Techniczna 10', 'city' => 'Warszawa', 'postcode' => '00-001', 'nip' => '9876543210']);
    $this->data = ['revision' => 0, 'supplier_company_id' => $this->supplier->id, 'acceptance_date' => '2026-09-21', 'kind' => 'partial', 'outcome' => 'reserved', 'invoice_decision' => 'conditional',
        'place' => 'Zakład produkcyjny, hala nr 2', 'reference' => 'ZAM/2026/128', 'description' => 'Odbiór dostawy armatury oraz montażu instalacji. Sprawdzono zgodność z zamówieniem i dokumentacją techniczną.',
        'remarks' => 'Uzupełnić oznaczenia zaworów oraz dostarczyć brakujący atest.', 'remedy_deadline' => '2026-09-28', 'invoice_conditions' => 'Faktura po potwierdzeniu usunięcia uwag przez odbierającego.',
        'attachments' => "1. Dokumentacja powykonawcza\n2. Wyniki prób szczelności", 'receiver_name' => 'Jan Kowalski', 'supplier_representative' => 'Anna Nowak',
        'items' => [['name' => 'Dostawa i montaż zaworu regulacyjnego DN80', 'quantity' => 2, 'unit' => 'szt.', 'price' => '1234.56', 'vat' => '23']]];
    $this->actingAs($this->admin);
});

test('project protocols save totals snapshots and render branded pdf with signatures', function () {
    CompanySettings::first()->update(['logo_data' => base64_encode(file_get_contents(public_path('Logo2.png'))), 'logo_mime' => 'image/png']);
    $this->get(route('projects.protocols.create', $this->project))->assertOk()->assertSee('Przedstawiciel dostawcy');
    $this->post(route('projects.protocols.store', $this->project), $this->data)->assertRedirect()->assertSessionHasNoErrors();
    $protocol = ProjectProtocol::firstOrFail();
    expect($protocol->totals())->toBe(['net' => 246912, 'vat' => 56790, 'gross' => 303702]);
    CompanySettings::first()->update(['name' => 'Zmieniona nazwa']);
    $this->supplier->update(['name' => 'Zmieniony dostawca']);
    expect($protocol->issuer_snapshot['name'])->toBe('Firma testowa')->and($protocol->supplier_snapshot['name'])->toBe('Przykładowy dostawca Sp. z o.o.');
    $this->get(route('projects.show', [$this->project, 'tab' => 'protocols']))->assertOk()->assertSee($protocol->number)->assertSee('Protokoły odbioru dostawców');
    $this->get(route('projects.protocols.edit', [$this->project, $protocol]))->assertOk();
    $response = $this->get(route('projects.protocols.pdf', [$this->project, $protocol]));
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
    if ($path = getenv('PROTOCOL_PDF_QA')) {
        file_put_contents($path, $response->getContent());
    }
});

test('protocols reject clients invalid decisions forged totals and stale edits', function () {
    $client = Company::create(['name' => 'Client', 'company_type' => 'client']);
    $this->post(route('projects.protocols.store', $this->project), array_replace($this->data, ['supplier_company_id' => $client->id]))->assertSessionHasErrors('supplier_company_id');
    $this->post(route('projects.protocols.store', $this->project), array_replace($this->data, ['outcome' => 'rejected', 'invoice_decision' => 'yes']))->assertSessionHasErrors('invoice_decision');
    $data = $this->data;
    $data['items'][0]['net_cents'] = 1;
    $this->post(route('projects.protocols.store', $this->project), $data)->assertRedirect()->assertSessionHasNoErrors();
    $protocol = ProjectProtocol::firstOrFail();
    expect($protocol->totals()['net'])->toBe(246912);
    $data['revision'] = 1;
    $this->put(route('projects.protocols.update', [$this->project, $protocol]), $data)->assertRedirect();
    expect($protocol->fresh()->revision)->toBe(2);
    $this->put(route('projects.protocols.update', [$this->project, $protocol]), $data)->assertStatus(409);
    $this->post(route('projects.protocols.store', $this->project), array_replace($this->data, ['use_signature' => 1]))->assertSessionHasErrors('use_signature');
});

test('protocol access requires project membership and separate permission and rejects mismatched project', function () {
    $this->post(route('projects.protocols.store', $this->project), $this->data);
    $protocol = ProjectProtocol::firstOrFail();
    $other = Project::create(['number' => 'OTHER', 'name' => 'Other', 'status' => 'active']);
    $this->get(route('projects.protocols.pdf', [$other, $protocol]))->assertNotFound();
    $this->get(route('projects.protocols.copy', [$other, $protocol]))->assertNotFound();
    $member = User::factory()->create();
    $role = Role::findOrCreate('protocol-reader');
    $role->givePermissionTo('projects.view');
    $member->assignRole($role);
    $this->project->members()->attach($member);
    $this->actingAs($member)->get(route('projects.protocols.pdf', [$this->project, $protocol]))->assertForbidden();
    $member->givePermissionTo('projects.protocols.view');
    $this->get(route('projects.protocols.pdf', [$this->project, $protocol]))->assertOk();
    $this->get(route('projects.protocols.edit', [$this->project, $protocol]))->assertForbidden();
    $this->get(route('projects.protocols.copy', [$this->project, $protocol]))->assertForbidden();
    $member->givePermissionTo('projects.protocols.manage');
    $this->get(route('projects.protocols.create', $other))->assertForbidden();
});

test('copy opens a new unsigned protocol and saving gives it a separate number', function () {
    $this->post(route('projects.protocols.store', $this->project), $this->data);
    $source = ProjectProtocol::firstOrFail();
    $source->update(['signature_data' => 'old-signature', 'revision' => 4]);
    $response = $this->get(route('projects.protocols.copy', [$this->project, $source]));
    $response->assertOk()->assertSee('Kopia protokołu')->assertSee(route('projects.protocols.store', $this->project), false);
    $copy = $response->viewData('protocol');
    expect($copy->exists)->toBeFalse()->and($copy->number)->toBeNull()
        ->and($copy->signature_data)->toBeNull()->and($copy->remedy_deadline)->toBeNull()
        ->and($copy->invoice_decision)->toBe('no')->and($copy->items)->toBe($source->items)
        ->and($copy->description)->toBe($source->description)->and(ProjectProtocol::count())->toBe(1);
    $data = array_replace($this->data, $copy->only(array_keys($this->data)));
    $data['revision'] = 0;
    $data['acceptance_date'] = $copy->acceptance_date->toDateString();
    $this->post(route('projects.protocols.store', $this->project), $data)->assertSessionHasNoErrors()->assertRedirect();
    $saved = ProjectProtocol::latest('id')->firstOrFail();
    expect($saved->number)->not->toBe($source->number)->and($saved->revision)->toBe(1)
        ->and($saved->signature_data)->toBeNull()->and($source->fresh()->signature_data)->toBe('old-signature');
});

test('pdf filename contains protocol number and safe bounded description for preview and download', function () {
    $this->post(route('projects.protocols.store', $this->project), $this->data);
    $protocol = ProjectProtocol::firstOrFail();
    $protocol->update(['description' => "Odbiór pomp / próba: 2\nNowa linia"]);
    $filename = $protocol->pdfFilename();
    expect($filename)->toBe(str_replace('/', '-', $protocol->number).' - Odbior pomp - proba- 2 Nowa linia.pdf');
    foreach ([0, 1] as $download) {
        $response = $this->get(route('projects.protocols.pdf', [$this->project, $protocol, 'download' => $download]));
        $response->assertOk();
        expect($response->headers->get('Content-Disposition'))->toContain($filename);
    }
    $protocol->description = str_repeat('Zażółć / "<>:?*', 1000);
    expect(strlen($protocol->pdfFilename()))->toBeLessThan(200);
    expect(preg_match('/[\\\\\/<>:"|?*\r\n]/', $protocol->pdfFilename()))->toBe(0);
});
