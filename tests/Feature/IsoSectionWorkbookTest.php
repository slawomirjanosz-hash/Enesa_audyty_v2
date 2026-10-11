<?php

use App\Models\IsoEnergyCards;
use App\Models\IsoImplementationResponse;
use App\Models\IsoStakeholderReview;
use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\IsoExecutionQuestionnaire;
use App\Services\IsoSectionWorkbook;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function sectionWorkbookCell($book, string $key): array
{
    foreach ($book->getAllSheets() as $sheet) {
        if (! str_starts_with($sheet->getTitle(), 'Dzial ')) {
            continue;
        }
        for ($r = 2; $r <= $sheet->getHighestDataRow(); $r++) {
            if ($sheet->getCell('A'.$r)->getValue() === $key) {
                return [$sheet, 'C'.$r];
            }
        }
    }
    throw new RuntimeException('Missing field '.$key);
}

test('every system questionnaire exports and reimports an offline template without approval', function (string $section) {
    [$audit, $profile, $client] = workbookFixture();
    $service = app(IsoSectionWorkbook::class);
    $book = $service->sectionExport($profile, $section);
    expect($book->getSheetByName('Instrukcja'))->not->toBeNull();
    expect($book->getSheetByName('_ENESA')->getSheetState())->toBe('veryHidden');
    workbookUpload($book, function ($file) use ($audit, $profile, $client, $section) {
        $this->actingAs($client)->post(route('client.audits.system.excel-import', [$audit, $profile, $section]), ['excel' => $file])
            ->assertSessionHasNoErrors()->assertRedirect();
        $review = IsoSystemReview::where('audit_id', $audit->id)->where('section', $section)->firstOrFail();
        expect($review->client_approval)->toBeNull()->and($review->auditor_approval)->toBeNull()->and($review->document_id)->toBeNull();
    });
})->with(array_keys(IsoSystemReview::TITLES));

test('system workbook maps repeated nested answers choices dates and numeric zero', function () {
    [$audit, $profile, $client] = workbookFixture();
    $book = app(IsoSectionWorkbook::class)->sectionExport($profile, '4-3');
    foreach (['ZAKRES.opis' => 'Zakres z Excela', 'GRANICE.1.nazwa' => 'Nowa lokalizacja', 'SPRAWDZONE.data' => Date::PHPToExcel(today()->toDateTime()), 'ZAKRES.dzialania|produkcja' => 'Tak'] as $key => $value) {
        [$sheet, $cell] = sectionWorkbookCell($book, $key);
        $sheet->setCellValue($cell, $value);
    }
    workbookUpload($book, function ($file) use ($audit, $profile, $client) {
        $this->actingAs($client)->post(route('client.audits.system.excel-import', [$audit, $profile, '4-3']), ['excel' => $file])->assertSessionHasNoErrors();
        $answers = IsoSystemReview::firstOrFail()->answers;
        expect($answers['ZAKRES']['opis'])->toBe('Zakres z Excela')->and($answers['ZAKRES']['dzialania'])->toBe(['produkcja'])
            ->and($answers['GRANICE'][1]['nazwa'])->toBe('Nowa lokalizacja')->and($answers['GRANICE'])->not->toHaveKey(4)
            ->and($answers['SPRAWDZONE']['data'])->toBe(today()->toDateString());
    });
    $book = app(IsoSectionWorkbook::class)->sectionExport($profile, '8-1');
    [$sheet, $cell] = sectionWorkbookCell($book, 'KARTY.1.seu');
    $sheet->setCellValue($cell, 'Sprężarkownia');
    // Use a real nested list from the shared schema, rather than inventing an answer key.
    $node = app(IsoExecutionQuestionnaire::class)->tree('8-1')['KARTY'];
    $nested = collect($node['children'])->filter(fn ($n) => $n['type'] === 'list')->keys()->first();
    $leaf = collect($node['children'][$nested]['children'])->filter(fn ($n) => ! in_array($n['type'], ['object', 'list']) && empty($n['options']))->keys()->first();
    $path = "KARTY.1.$nested.1.$leaf";
    [$sheet, $cell] = sectionWorkbookCell($book, $path);
    $sheet->setCellValue($cell, 0);
    workbookUpload($book, function ($file, $filePath) use ($profile, $path) {
        $data = app(IsoSectionWorkbook::class)->sectionRead($filePath, $profile, '8-1');
        expect(data_get($data['answers'], $path))->toBe('0')->and($data['answers']['KARTY'])->not->toHaveKey(4);
    });
});

test('section workbook rejects stale foreign tampered and formula workbooks', function () {
    [$audit, $profile] = workbookFixture();
    foreach (['formula', 'duplicate', 'metadata', 'version', 'source', 'section'] as $case) {
        $book = app(IsoSectionWorkbook::class)->sectionExport($profile->fresh(), '5-2');
        [$sheet, $cell] = sectionWorkbookCell($book, 'POL.nazwa');
        if ($case === 'formula') {
            $sheet->setCellValue($cell, '=1+1');
        } elseif ($case === 'duplicate') {
            $sheet->setCellValue('A3', 'POL.nazwa');
        } elseif ($case === 'metadata') {
            $book->getSheetByName('_ENESA')->setCellValue('A1', 'tampered');
        } elseif ($case === 'version') {
            $profile->increment('lock_version');
        } elseif ($case === 'source') {
            $profile->update(['answers' => ['site.name' => ['value' => 'Changed']]]);
        }
        workbookUpload($book, function ($file, $path) use ($profile, $case) {
            expect(fn () => app(IsoSectionWorkbook::class)->sectionRead($path, $profile->fresh(), $case === 'section' ? '5-1' : '5-2'))->toThrow(ValidationException::class);
        });
    }
});

test('section import retains consultant data and invalidates approvals only through normal workflow', function () {
    [$audit, $profile, $client, $staff] = workbookFixture();
    $sources = app(IsoSystemQuestionnaire::class)->sources($profile, '5-2');
    $review = IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => '5-2', 'status' => 'approved', 'lock_version' => 1,
        'source_hash' => $sources['hash'], 'answers' => ['POL' => ['nazwa' => 'Przed']], 'consultant' => ['secret' => 'Private consultant note'],
        'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id]]);
    $book = app(IsoSectionWorkbook::class)->sectionExport($profile, '5-2');
    foreach ($book->getAllSheets() as $sheet) {
        expect(json_encode($sheet->toArray()))->not->toContain('Private consultant note');
    }
    [$sheet, $cell] = sectionWorkbookCell($book, 'POL.nazwa');
    $sheet->setCellValueExplicit($cell, '=tekst, nie formuła', DataType::TYPE_STRING);
    workbookUpload($book, function ($file) use ($audit, $profile, $client, $staff, $review) {
        $route = route('client.audits.system.excel-import', [$audit, $profile, '5-2']);
        $this->actingAs($client)->post($route, ['excel' => $file])->assertStatus(409);
        $this->actingAs($staff)->post(route('audits.system.excel-import', [$audit, $profile, '5-2']), ['excel' => $file])->assertSessionHasNoErrors();
        $review->refresh();
        expect($review->status)->toBe('auditor_corrected')->and($review->client_approval)->toBeNull()->and($review->auditor_approval)->toBeNull()
            ->and($review->consultant['secret'])->toBe('Private consultant note')->and($review->answers['POL']['nazwa'])->toBe('=tekst, nie formuła');
    });
});

test('stakeholder excel imports custom requirements and client answers', function () {
    [$audit, $client, $staff, $profile] = stakeholderFixture();
    $book = app(IsoSectionWorkbook::class)->sectionExport($profile, '4-2');
    foreach (['climate_reason' => 'Ocena klienta', 'custom.0.nazwa' => 'Nowy odbiorca', 'custom.0.typ' => 'Zewnętrzna [zewnętrzna]', 'custom.0.wymagania' => 'Raport miesięczny'] as $key => $value) {
        [$sheet, $cell] = sectionWorkbookCell($book, $key);
        $sheet->setCellValue($cell, $value);
    }
    workbookUpload($book, function ($file) use ($audit, $profile, $client) {
        $this->actingAs($client)->post(route('client.audits.stakeholders.excel-import', [$audit, $profile]), ['excel' => $file])->assertSessionHasNoErrors()->assertRedirect();
        $review = IsoStakeholderReview::firstOrFail();
        expect($review->answers['custom'])->toHaveCount(1)->and($review->answers['custom'][0]['nazwa'])->toBe('Nowy odbiorca')->and($review->client_approval)->toBeNull();
        $next = app(IsoSectionWorkbook::class)->sectionExport($profile, '4-2');
        foreach (['custom.4.nazwa' => 'Kolejny odbiorca', 'custom.4.typ' => 'Zewnętrzna [zewnętrzna]', 'custom.4.wymagania' => 'Raport roczny'] as $key => $value) {
            [$sheet, $cell] = sectionWorkbookCell($next, $key);
            $sheet->setCellValue($cell, $value);
        }
        workbookUpload($next, function ($nextFile) use ($audit, $profile, $review) {
            $this->post(route('client.audits.stakeholders.excel-import', [$audit, $profile]), ['excel' => $nextFile])->assertSessionHasNoErrors();
            expect($review->fresh()->answers['custom'])->toHaveCount(2);
        });
    });
});

test('section excel routes enforce tenant access and render controls for client and auditor', function () {
    [$audit, $profile, $client, $staff] = workbookFixture();
    foreach (['4-2', '5-2', '8-1'] as $section) {
        $module = $section === '4-2' ? 'stakeholders' : 'system';
        $params = $section === '4-2' ? [$audit, $profile] : [$audit, $profile, $section];
        foreach ([$client, $staff] as $user) {
            $prefix = ($user === $client ? 'client.' : '')."audits.$module.";
            $this->actingAs($user)->get(route($prefix.'show', $params))->assertOk()->assertSee('Pobierz ankietę Excel')->assertSee(route($prefix.'excel', $params), false);
            $this->get(route($prefix.'excel', $params))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        $stranger = User::factory()->create();
        $stranger->assignRole(Role::findOrCreate('client_admin'));
        $this->actingAs($stranger)->get(route("client.audits.$module.excel", $params))->assertNotFound();
        $this->post(route("client.audits.$module.excel-import", $params))->assertNotFound();
    }
});

test('preparation questionnaires support offline excel with stale and tenant protection', function (string $action) {
    [$audit, $profile, $client, $staff] = workbookFixture();
    $service = app(IsoSectionWorkbook::class);
    $this->actingAs($staff)->get(route('audits.implementation.excel', [$audit, $action]))->assertOk();
    $book = $service->implementationExport($audit, $action);
    $key = array_key_first(config('iso50001-workflows.3-1.'.$action.'.fields'));
    [$sheet, $cell] = sectionWorkbookCell($book, $key);
    $value = $action === 'business_case' ? '5' : 'Dane klienta';
    $sheet->setCellValue($cell, $value);
    workbookUpload($book, function ($file) use ($audit, $client, $action, $key, $value) {
        $url = route('client.audits.implementation.excel-import', [$audit, $action]);
        $this->actingAs($client)->post($url, ['excel' => $file])->assertSessionHasNoErrors()->assertRedirect();
        $response = IsoImplementationResponse::where('audit_id', $audit->id)->where('action_key', $action)->firstOrFail();
        expect((string) $response->answers[$key])->toBe($value)->and($response->generated_at)->toBeNull();
        $this->post($url, ['excel' => $file])->assertSessionHasErrors('excel');
        $stranger = User::factory()->create();
        $stranger->assignRole(Role::findOrCreate('client_admin'));
        $this->actingAs($stranger)->post($url, ['excel' => $file])->assertNotFound();
    });
})->with(['baseline', 'business_case', 'sponsor']);

test('section excel preserves authoritative source fields despite workbook tampering', function () {
    [$audit, $profile] = workbookFixture();
    IsoEnergyCards::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'cards' => [['nr' => 'ENPI-1', 'nazwa' => 'Energia na tonę', 'wartoscEnB' => '100']]]);
    $book = app(IsoSectionWorkbook::class)->sectionExport($profile, '9-1-1');
    $index = 'card_'.substr(hash('sha256', 'ENPI-1'), 0, 20);
    [$sheet, $cell] = sectionWorkbookCell($book, "WSKAZNIKI.$index.enb");
    expect($sheet->getStyle($cell)->getProtection()->getLocked())->not->toBe('unprotected');
    $sheet->setCellValue($cell, '999');
    workbookUpload($book, function ($file, $path) use ($profile, $index) {
        $data = app(IsoSectionWorkbook::class)->sectionRead($path, $profile, '9-1-1');
        expect(data_get($data['answers'], "WSKAZNIKI.$index.enb"))->toBe('100');
    });
});

test('populated execution workbooks preserve nested data on a round trip', function (string $section) {
    [$audit, $profile] = workbookFixture();
    $service = app(IsoSystemQuestionnaire::class);
    $sources = $service->sources($profile, $section);
    $answers = $service->normalize($section, executionAnswers($section, $sources), $sources, false);
    IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => $section, 'status' => 'editing', 'lock_version' => 1, 'source_hash' => $sources['hash'], 'answers' => $answers]);
    $book = app(IsoSectionWorkbook::class)->sectionExport($profile, $section);
    workbookUpload($book, function ($file, $path) use ($profile, $section, $answers, $sources, $service) {
        $data = app(IsoSectionWorkbook::class)->sectionRead($path, $profile, $section);
        expect($service->normalize($section, $data['answers'], $sources, false))->toBe($answers);
    });
})->with(array_keys(IsoExecutionQuestionnaire::TITLES));

test('excel viewers cannot import or expose data from unassigned companies', function () {
    [$audit, $profile] = workbookFixture();
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('employee'));
    $viewer->givePermissionTo(Permission::findOrCreate('audits.view'));
    foreach ([['system', [$audit, $profile, '5-2']], ['stakeholders', [$audit, $profile]], ['implementation', [$audit, 'baseline']]] as [$module, $params]) {
        $this->actingAs($viewer)->get(route("audits.$module.excel", $params))->assertOk();
        $this->post(route("audits.$module.excel-import", $params))->assertForbidden();
    }
    $viewer->syncRoles(Role::findOrCreate('auditor'));
    $this->actingAs($viewer)->get(route('audits.system.excel', [$audit, $profile, '5-2']))->assertForbidden();
});
