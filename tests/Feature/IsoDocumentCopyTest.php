<?php

use App\Models\Document;
use App\Models\IsoSectionDocument;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

test('ISO document actions use accessible compact icons in every section and preserve permissions', function () {
    [$audit, $client] = plantFixture();
    $this->actingAs($client);
    $document = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => 'intro', 'scope' => 'client', 'title' => 'Profil', 'version_number' => '1.0', 'original_filename' => 'profil.pdf', 'stored_path' => 'iso/icons.pdf', 'mime_type' => 'application/pdf', 'size' => 8, 'content_base64' => base64_encode('%PDF demo'), 'uploaded_by' => $client->id]);
    foreach (['intro', '4-1', '5-2', '6-6', '7-3', '9-1'] as $section) {
        $document->section_id = $section;
        $html = view('audit-types.partials.iso-client-document-row', ['audit' => $audit, 'document' => $document, 'clientView' => true, 'canUpload' => false, 'canDeleteClientDocument' => true])->render();
        foreach (['Pobierz dokument', 'Kopiuj do Dokumentów', 'Usuń dokument'] as $label) {
            expect($html)->toContain('title="'.$label.'"', 'aria-label="'.$label.'"');
        }
        expect(substr_count($html, 'iso-doc-btn iso-doc-icon'))->toBe(3);
        expect($html)->not->toContain('flex-wrap:wrap', '</i> Pobierz', '</i> Usuń', '</i> Kopiuj');
    }
    $client->syncRoles([Role::findOrCreate('client_user')]);
    $html = view('audit-types.partials.iso-client-document-row', ['audit' => $audit, 'document' => $document, 'clientView' => true, 'canUpload' => false, 'canDeleteClientDocument' => false])->render();
    expect($html)->toContain('aria-label="Pobierz dokument"')->not->toContain('aria-label="Kopiuj do Dokumentów"', 'aria-label="Usuń dokument"');
});

test('client admin copies ISO document once without losing original and other clients cannot copy it', function () {
    Storage::fake('local');
    [$audit, $client] = plantFixture();
    $source = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => 'intro', 'scope' => 'client', 'title' => '1 Wstęp ISO Profil zakładu', 'version_number' => '1.0', 'original_filename' => '1 Wstęp ISO Profil zakładu.pdf', 'stored_path' => 'iso/source.pdf', 'mime_type' => 'application/pdf', 'size' => 8, 'content_base64' => base64_encode('%PDF demo'), 'uploaded_by' => $client->id]);
    $url = route('client.audits.iso-documents.copy', [$audit, $source]);
    $this->actingAs($client)->post($url)->assertRedirect()->assertSessionHasNoErrors();
    $this->post($url)->assertRedirect()->assertSessionHasNoErrors();
    expect(Document::count())->toBe(1)->and($source->fresh()->contents())->toBe('%PDF demo');
    $copy = Document::firstOrFail();
    expect($copy->original_filename)->toBe($source->original_filename)->and($copy->audit_id)->toBe($audit->id);
    expect(Storage::disk('local')->get($copy->stored_path))->toBe('%PDF demo');
    [$otherAudit, $otherClient] = plantFixture();
    $this->actingAs($otherClient)->post($url)->assertNotFound();
    $this->post(route('client.audits.iso-documents.copy', [$otherAudit, $source]))->assertNotFound();
    $client->syncRoles([Role::findOrCreate('client_user')]);
    $this->actingAs($client)->post($url)->assertForbidden();
    $client->syncRoles([Role::findOrCreate('client_admin')]);
    $this->actingAs($client)->delete(route('client.audits.iso-documents.destroy', [$audit, $source]))->assertRedirect();
    expect(Storage::disk('local')->get($copy->stored_path))->toBe('%PDF demo');
    expect($copy->fresh())->not->toBeNull();
});

test('copying a document ignores legacy user quota and retains attribution', function () {
    Storage::fake('local');
    [$audit, $client] = plantFixture();
    $source = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => 'intro', 'scope' => 'client', 'title' => 'Profil', 'version_number' => '1.0', 'original_filename' => 'profil.pdf', 'stored_path' => 'iso/source.pdf', 'mime_type' => 'application/pdf', 'size' => 8, 'content_base64' => base64_encode('%PDF demo'), 'uploaded_by' => $client->id]);
    $client->forceFill(['document_limit_bytes' => 8])->save();
    $this->actingAs($client)->post(route('client.audits.iso-documents.copy', [$audit, $source]))->assertRedirect()->assertSessionHasNoErrors();
    expect(Document::count())->toBe(1)->and(Document::first()->storage_owner_id)->toBe($client->id);
    Storage::disk('local')->assertExists(Document::first()->stored_path);
});
