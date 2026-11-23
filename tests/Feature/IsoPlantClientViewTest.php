<?php

use App\Services\IsoQuestionnaireWorkbook;

test('compact client profile hides technical fields and preserves omitted provenance when saving', function () {
    [$audit, $profile, $client, $staff] = workbookFixture();
    $profile->update(['review_note' => 'Uwaga do danych', 'answers' => [
        'site.name' => ['value' => 'Zakład testowy', 'detail' => 'Zachowany opis nazwy', 'source' => 'Karta klienta'],
        'FAKT_BRANZA' => ['value' => 'Produkcja', 'detail' => 'Opis działalności', 'source' => 'Dokument źródłowy'],
    ]]);
    $url = route('client.audits.plant-profile.show', [$audit, $profile]);
    $response = $this->actingAs($client)->get($url)->assertOk()
        ->assertSee('Wypełnij Excela offline')->assertSee('Załaduj Excela')
        ->assertSee('Sugerowana osoba wypełniająca')->assertDontSee('Dział / osoba wspierająca')
        ->assertDontSee('Źródło / nazwa dokumentu')->assertDontSee('Uzupełnienie i źródło odpowiedzi')
        ->assertDontSee('Z karty klienta, potwierdzenie lub zgłoszenie korekty')
        ->assertDontSee('class="plant-badge"', false)->assertDontSee('class="plant-help plant-variable"', false)
        ->assertDontSee('name="answers[site.name][detail]"', false)->assertDontSee('name="answers[site.name][source]"', false)
        ->assertSee('name="answers[FAKT_BRANZA][detail]"', false)->assertDontSee('name="answers[FAKT_BRANZA][source]"', false);
    expect($response->getContent())->toContain('<details class="plant-notice plant-review-notes">');
    $this->post(route('client.audits.plant-profile.update', [$audit, $profile]), [
        'operation' => 'save', 'lock_version' => 0, 'as_of_date' => '2026-11-23', 'complete_form' => 1,
        'answers' => ['site.name' => ['value' => 'Nowa nazwa'], 'FAKT_BRANZA' => ['value' => 'Produkcja', 'detail' => 'Nowy opis']],
    ])->assertSessionHasNoErrors();
    $answers = $profile->fresh()->answers;
    expect($answers['site.name']['value'])->toBe('Nowa nazwa');
    expect($answers['site.name']['detail'])->toBe('Zachowany opis nazwy');
    expect($answers['site.name']['source'])->toBe('Karta klienta');
    expect($answers['FAKT_BRANZA']['detail'])->toBe('Nowy opis');
    expect($answers['FAKT_BRANZA']['source'])->toBe('Dokument źródłowy');
    $this->actingAs($staff)->get(route('audits.plant-profile.show', [$audit, $profile]))->assertOk()
        ->assertSee('class="plant-help plant-variable"', false)->assertSee('Dokument źródłowy')->assertSee('Zachowany opis nazwy');
});

test('compact Excel import retains explicit mapped metadata and does not approve answers', function () {
    [$audit, $profile, $client] = workbookFixture();
    $profile->update(['answers' => ['site.name' => ['value' => 'Zakład', 'detail' => 'Stara uwaga', 'source' => 'Stare źródło']]]);
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'plant');
    $sheet = $book->getSheetByName('Pytania');
    $row = workbookRow($sheet, 'site.name');
    $sheet->setCellValue('C'.$row, 'Nazwa z Excela');
    $sheet->setCellValue('E'.$row, '');
    $sheet->setCellValue('F'.$row, 'Nowe źródło z Excela');
    workbookUpload($book, function ($file) use ($audit, $profile, $client) {
        $this->actingAs($client)->post(route('client.audits.plant-profile.excel-import', [$audit, $profile]), ['excel' => $file])->assertSessionHasNoErrors();
        $saved = $profile->fresh();
        expect($saved->answers['site.name']['value'])->toBe('Nazwa z Excela');
        expect($saved->answers['site.name']['detail'])->toBeNull();
        expect($saved->answers['site.name']['source'])->toBe('Nowe źródło z Excela');
        expect($saved->status)->toBe('editing')->and($saved->client_approval)->toBeNull();
    });
});
