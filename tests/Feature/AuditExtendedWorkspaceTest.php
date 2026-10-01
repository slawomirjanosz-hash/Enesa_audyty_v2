<?php

use App\Models\Audit;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

function extendedAudit(): array
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin'));
    $company = Company::create(['name' => 'Klient', 'company_type' => 'client', 'status' => 'active']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => uniqid('A/'), 'title' => 'Analiza', 'status' => 'draft', 'manager_id' => $user->id]);

    return [$user, $company, $audit];
}
test('audit finances support supplier groups statuses and bulk actions without crossing audits', function () {
    [$user,$company,$audit] = extendedAudit();
    $other = Audit::create(['company_id' => $company->id, 'number' => 'OTHER', 'title' => 'Obcy', 'status' => 'draft']);
    $foreign = $other->financeGroups()->create(['name' => 'Obca grupa']);
    $supplier = Company::create(['name' => 'Dostawca', 'company_type' => 'supplier', 'status' => 'active']);
    $this->actingAs($user)->post(route('audits.finance-groups.store', $audit), ['name' => 'Koszty'])->assertRedirect();
    $group = $audit->financeGroups()->sole();
    $data = ['type' => 'cost', 'name' => 'Koszt', 'entry_date' => '2026-10-01', 'payment_date' => '2026-10-15', 'amount' => 123.45, 'status' => 'planned', 'supplier_company_id' => $supplier->id, 'finance_group_id' => $group->id];
    $this->post(route('audits.finances.store', $audit), array_replace($data, ['finance_group_id' => $foreign->id]))->assertSessionHasErrors('finance_group_id');
    $this->post(route('audits.finances.store', $audit), $data)->assertRedirect();
    $entry = $audit->financialEntries()->sole();
    expect($entry->supplier)->toBe('Dostawca');
    expect($audit->fresh()->totalCosts())->toBe(0.0);
    $this->patchJson(route('audits.finances.status', [$audit, $entry]), ['status' => 'issued'])->assertOk()->assertJsonPath('summary.costs', 123.45);
    $this->patchJson(route('audits.finances.status', [$other, $entry]), ['status' => 'paid'])->assertNotFound();
    $this->get(route('audits.show', [$audit, 'tab' => 'finances']))->assertOk()->assertSee('Import z Excela')->assertSee('Koszty')->assertSee('data-sort-value="123.45"', false);
    $this->post(route('audits.finances.bulk', $audit), ['entry_ids' => [$entry->id], 'action' => 'paid'])->assertRedirect();
    $this->patch(route('audits.finances.update', [$audit, $entry]), array_replace($data, ['type' => 'invoice']))->assertRedirect();
    expect($entry->fresh()->supplier_company_id)->toBeNull();
    expect($entry->fresh()->financeGroup->name)->toBe('Wystawione');
    $this->delete(route('audits.finance-groups.destroy', [$audit, $group]))->assertRedirect();
    $this->post(route('audits.finances.bulk', $audit), ['entry_ids' => [$entry->id], 'action' => 'delete'])->assertRedirect();
    expect($audit->financialEntries()->count())->toBe(0);
});
test('audit finance import detects duplicates and preserves amounts', function () {
    [$user,,$audit] = extendedAudit();
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([['Data', 'Kwota netto', 'Numer faktury', 'Opis'], ['01.10.2026', '1 234,56', 'FV/1', 'Analiza']]);
    $path = tempnam(sys_get_temp_dir(), 'audit-import-');
    try {
        (new Xlsx($sheet))->save($path);
        for ($i = 0; $i < 2; $i++) {
            $file = new UploadedFile($path, 'finanse.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $this->actingAs($user)->post(route('audits.finances.import', $audit), ['type' => 'cost', 'file' => $file])->assertRedirect()->assertSessionHas('finance_import_report');
        }
        expect($audit->financialEntries()->count())->toBe(1);
        expect((float) $audit->financialEntries()->sole()->amount)->toBe(1234.56);
    } finally {
        unlink($path);
    }
});
test('audit folders support uploads and moves while rejecting foreign folders and client writes', function () {
    Storage::fake('local');
    [$user,$company,$audit] = extendedAudit();
    $other = Audit::create(['company_id' => $company->id, 'number' => 'OTHER', 'title' => 'Obcy', 'status' => 'draft']);
    $foreign = $other->documentFolders()->create(['name' => 'Obcy']);
    $this->actingAs($user)->post(route('audits.document-folders.store', $audit), ['name' => 'Pomiary'])->assertRedirect();
    $folder = $audit->documentFolders()->sole();
    $this->post(route('audits.documents.store', $audit), ['files' => [UploadedFile::fake()->create('pomiar.pdf', 1, 'application/pdf')], 'audit_document_folder_id' => $foreign->id])->assertNotFound();
    $this->post(route('audits.documents.store', $audit), ['files' => [UploadedFile::fake()->create('pomiar.pdf', 1, 'application/pdf')], 'audit_document_folder_id' => $folder->id])->assertRedirect();
    $document = $audit->documents()->sole();
    Storage::disk('local')->assertExists($document->stored_path);
    $this->delete(route('audits.document-folders.destroy', [$audit, $folder]))->assertStatus(422);
    $this->patch(route('audits.documents.move', [$audit, $document]), ['audit_document_folder_id' => $foreign->id])->assertNotFound();
    $this->patch(route('audits.documents.move', [$audit, $document]), ['audit_document_folder_id' => null])->assertRedirect();
    $this->delete(route('audits.document-folders.destroy', [$audit, $folder]))->assertRedirect();
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->companies()->attach($company);
    $this->actingAs($client)->get(route('client.audits.show', $audit))->assertOk()->assertSee('pomiar.pdf')->assertDontSee('Import z Excela');
    $this->post(route('audits.document-folders.store', $audit), ['name' => 'Nie'])->assertForbidden();
});
test('external drive links are https only scoped and never expose credentials', function () {
    [$user,$company,$audit] = extendedAudit();
    $this->actingAs($user);
    foreach (['javascript:alert(1)', 'http://example.com', 'https://user:password@example.com'] as $url) {
        $this->post(route('audits.document-links.store', $audit), ['name' => 'Zły', 'url' => $url])->assertSessionHasErrors('url');
    }
    $this->post(route('audits.document-links.store', $audit), ['name' => 'Dysk klienta', 'url' => 'https://drive.google.com/drive/folders/example'])->assertRedirect();
    $this->get(route('audits.show', $audit))->assertOk()->assertSee('Dysk klienta')->assertSee('rel="noopener noreferrer"', false);
    $project = Project::create(['number' => 'P/LINK', 'name' => 'Projekt', 'status' => 'active', 'manager_id' => $user->id]);
    $this->post(route('projects.document-links.store', $project), ['name' => 'OneDrive', 'url' => 'https://onedrive.live.com/'])->assertRedirect();
    $this->get(route('projects.show', $project))->assertOk()->assertSee('OneDrive');
    $this->delete(route('projects.document-links.destroy', [$project, $audit->documentLinks()->sole()]))->assertNotFound();
    $this->delete(route('audits.document-links.destroy', [$audit, $audit->documentLinks()->sole()]))->assertRedirect();
});
test('audit gantt link is read only revocable and excludes financial and private task data', function () {
    [$user,$company,$audit] = extendedAudit();
    $audit->financialEntries()->create(['type' => 'cost', 'name' => 'SECRET FINANCE', 'amount' => 987654, 'entry_date' => '2026-10-01', 'status' => 'paid']);
    Task::create(['audit_id' => $audit->id, 'title' => 'Widoczne zadanie', 'description' => 'SECRET NOTE', 'status' => 'pending', 'priority' => 'medium']);
    $auditor = User::factory()->create();
    $auditor->assignRole(Role::findOrCreate('auditor'));
    $this->actingAs($auditor)->postJson(route('audits.public-gantt.generate', $audit))->assertForbidden();
    $response = $this->actingAs($user)->postJson(route('audits.public-gantt.generate', $audit))->assertOk();
    $url = $response->json('url');
    auth()->forgetGuards();
    $this->get($url)->assertOk()->assertSee('Widoczne zadanie')->assertDontSee('SECRET FINANCE')->assertDontSee('SECRET NOTE')->assertDontSee('987654');
    $this->actingAs($user)->delete(route('audits.public-gantt.destroy',$audit))->assertRedirect();
    $this->get($url)->assertNotFound();
});
