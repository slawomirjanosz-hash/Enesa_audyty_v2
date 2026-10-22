<?php

use App\Models\Company;
use App\Models\Offer;
use App\Models\User;

test('Word exports editor HTML line breaks tables and Polish text', function (bool $legacy) {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Klient eksportu', 'company_type' => 'client', 'status' => 'active']);
    $html = '<p>Zażółć gęślą jaźń<br>Drugi wiersz &amp; pomiary&nbsp;energii.</p>'
        .'<table><tbody><tr><th>Zakres</th><th>Termin</th></tr>'
        .'<tr><td>Pomiary<br>Analiza<br/>Raport<br /></td><td>30 dni</td></tr></tbody></table>';
    $offer = Offer::create([
        'company_id' => $company->id, 'offer_number' => 'OF_WORD_001', 'status' => 'w_toku',
        'offer_title' => 'Oferta pomiarów i analizy energii',
        'content_subject' => $legacy ? $html : null,
        'text_sections' => $legacy ? null : [
            ['name' => 'Zakres prac', 'content' => $html, 'placement' => 'before_price'],
            ['name' => 'Warunki realizacji', 'content' => '<p>Uzgodnienie<br>Realizacja</p>', 'placement' => 'after_price'],
        ],
    ]);
    $response = $this->actingAs($admin)->get(route('offers.download-word', $offer))->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue();
    expect($document->textContent)->toContain('Zażółć gęślą jaźń', 'Drugi wiersz', 'Pomiary', 'Analiza', 'Raport', '30 dni');
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    expect($xpath->query('//w:br')->length)->toBeGreaterThanOrEqual(4);
    expect($xpath->query('//w:tbl[.//w:t="Pomiary"]')->length)->toBe(1);
    if (getenv('OFFER_WORD_PREVIEW_DIR')) {
        copy($path, getenv('OFFER_WORD_PREVIEW_DIR').'/offer-word-'.($legacy ? 'legacy' : 'sections').'.docx');
    }
})->with([false, true]);
