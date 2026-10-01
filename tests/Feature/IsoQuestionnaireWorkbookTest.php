<?php

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\User;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoPlantQuestionnaire;
use App\Services\IsoQuestionnaireWorkbook;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

function workbookFixture(): array
{
    $company = Company::create(['name' => 'Zakład Excel', 'company_type' => 'client', 'status' => 'active']);
    $staff = User::factory()->create();
    $staff->assignRole(Role::findOrCreate('superadmin'));
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_admin'));
    $client->companies()->attach($company);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'EX/1', 'title' => 'Audyt', 'status' => 'draft']);
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'ISO', 'status' => 'draft']);
    $site = DB::table('iso_plant_sites')->insertGetId(['company_id' => $company->id, 'name' => 'Zakład']);
    $profile = IsoPlantProfile::create(['audit_id' => $audit->id, 'site_id' => $site, 'revision' => 1, 'lock_version' => 0, 'status' => 'editing', 'as_of_date' => '2026-10-01', 'definition' => app(IsoPlantQuestionnaire::class)->definition(), 'answers' => []]);

    return [$audit, $profile, $client, $staff];
}

function workbookUpload($book, callable $test): void
{
    $path = tempnam(sys_get_temp_dir(), 'iso-xlsx-');
    try {
        (new Xlsx($book))->setPreCalculateFormulas(false)->save($path);
        $book->disconnectWorksheets();
        $test(new UploadedFile($path, 'ankieta.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), $path);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function workbookRow($sheet, string $key): int
{
    for ($i = 2; $i <= $sheet->getHighestDataRow(); $i++) {
        if ($sheet->getCell('A'.$i)->getValue() === $key) {
            return $i;
        }
    }
    throw new RuntimeException('Missing key '.$key);
}

test('plant excel round trip imports stable codes choices rows decimals and keeps approval pending', function () {
    [$audit,$profile,$client,$staff] = workbookFixture();
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'plant');
    $sheet = $book->getSheetByName('Pytania');
    expect($sheet->getHighestDataRow())->toBe(92);
    $questions = app(IsoPlantQuestionnaire::class)->questions($profile->definition);
    $text = collect($questions)->firstWhere('type', 'text')['key'];
    $select = collect($questions)->firstWhere('type', 'select');
    $sheet->setCellValue('C'.workbookRow($sheet, $text), 'Zakład z Excela');
    $sheet->setCellValue('C'.workbookRow($sheet, $select['key']), array_values($select['options'])[0]);
    $sheet->setCellValue('C'.workbookRow($sheet, 'FAKT_LOKALIZACJE'), 1);
    $book->getSheetByName('T01')->fromArray(['Oddział', 'Warszawa', 'produkcja', 'tak'], null, 'B2');
    $book->getSheetByName('T02')->fromArray(['energia elektryczna', '1 234,5', 'MWh', '1200,55'], null, 'B2');
    $multi = $book->getSheetByName('Wybory');
    $multi->setCellValue('E2', 'Tak');
    $multiKey = $multi->getCell('A2')->getValue();
    $multiValue = $multi->getCell('C2')->getValue();
    workbookUpload($book, function ($file) use ($audit, $profile, $client, $text, $multiKey, $multiValue) {
        $this->actingAs($client)->post(route('client.audits.plant-profile.excel-import', [$audit, $profile]), ['excel' => $file])->assertSessionHasNoErrors()->assertRedirect();
        $current = $profile->fresh();
        expect($current->answers[$text]['value'])->toBe('Zakład z Excela')->and($current->answers['FAKT_NOSNIKI']['value'][0]['c1'])->toBe('1234.5')->and($current->answers[$multiKey]['value'])->toBe([$multiValue])->and($current->status)->toBe('editing')->and($current->client_approval)->toBeNull()->and($current->lock_version)->toBe(1);
        expect($current->answers['ZUZYCIE_TJ']['value'])->not->toBeNull();
    });
});

test('excel imports reject bad options formulas duplicate codes and stale files without partial writes', function () {
    [$audit,$profile,$client] = workbookFixture();
    foreach (['option', 'formula', 'duplicate', 'stale'] as $case) {
        $book = app(IsoQuestionnaireWorkbook::class)->export($profile->fresh(), 'plant');
        $sheet = $book->getSheetByName('Pytania');
        if ($case === 'option') {
            $q = collect(app(IsoPlantQuestionnaire::class)->questions($profile->definition))->firstWhere('type', 'select');
            $sheet->setCellValue('C'.workbookRow($sheet, $q['key']), 'nieistniejąca');
        }
        if ($case === 'formula') {
            $sheet->setCellValue('C3', '=1+1');
        }
        if ($case === 'duplicate') {
            $sheet->setCellValue('A4', $sheet->getCell('A3')->getValue());
        }
        if ($case === 'stale') {
            $profile->increment('lock_version');
        }
        workbookUpload($book, function ($file) use ($audit, $profile, $client) {
            $this->actingAs($client)->post(route('client.audits.plant-profile.excel-import', [$audit, $profile]), ['excel' => $file])->assertSessionHasErrors('excel');
            expect($profile->fresh()->answers)->toBe([]);
        });
    }
});

test('factor workbook imports decisions and custom factors with existing workflow and profile references', function () {
    [$audit,$profile,$client,$staff] = workbookFixture();
    $profile->update(['status' => 'approved', 'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id]]);
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'factors');
    $book->getSheetByName('Klimat')->setCellValue('C2', 'tak')->setCellValue('C3', 'Wpływ temperatur na zużycie energii.');
    $factor = collect(app(IsoFactorQuestionnaire::class)->factors([], []))->first(fn ($f) => $f['rodzaj'] !== 'AUTO' && ! str_contains($f['pokaz_gdy'], 'FAKT_KLIMAT_ISTOTNY'));
    $row = workbookRow($book->getSheetByName('Czynniki'), $factor['kod']);
    $book->getSheetByName('Czynniki')->setCellValue('C'.$row, 'potwierdzony')->setCellValue('D'.$row, 'Treść klienta');
    $book->getSheetByName('Własne czynniki')->setCellValue('B2', 'Nowa linia produkcyjna')->setCellValue('C2', '+');
    workbookUpload($book, function ($file) use ($audit, $profile, $staff, $factor) {
        $this->actingAs($staff)->post(route('audits.factors.excel-import', [$audit, $profile]), ['excel' => $file])->assertSessionHasNoErrors()->assertRedirect();
        $review = IsoFactorReview::sole();
        expect($review->answers['factors'][$factor['kod']]['tresc'])->toBe('Treść klienta')->and($review->answers['custom'][0]['tresc'])->toBe('Nowa linia produkcyjna')->and($review->status)->toBe('auditor_corrected')->and($review->client_approval)->toBeNull()->and($review->source_hash)->toBe(app(IsoFactorQuestionnaire::class)->hash($profile));
    });
});

test('workbook routes enforce ownership permissions and approved client write locks', function () {
    [$audit,$profile,$client,$staff] = workbookFixture();
    $other = User::factory()->create();
    $other->assignRole(Role::findOrCreate('client_admin'));
    $this->actingAs($other)->get(route('client.audits.plant-profile.excel', [$audit, $profile]))->assertNotFound();
    $this->actingAs($staff)->get(route('audits.plant-profile.excel', [$audit, $profile]))->assertOk()->assertDownload();
    $this->get(route('audits.factors.excel', [$audit, $profile]))->assertStatus(409);
    $profile->update(['status' => 'submitted', 'client_approval' => ['user_id' => $client->id]]);
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'plant');
    workbookUpload($book, function ($file) use ($audit, $profile, $client) {
        $this->actingAs($client)->post(route('client.audits.plant-profile.excel-import', [$audit, $profile]), ['excel' => $file])->assertStatus(409);
        expect($profile->fresh()->status)->toBe('submitted');
    });
});

test('workbook exports untrusted text as literal text not formulas', function () {
    [, $profile] = workbookFixture();
    $profile->update(['answers' => ['organization.name' => ['value' => '=HYPERLINK("https://example.com","x")']]]);
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'plant');
    $sheet = $book->getSheetByName('Pytania');
    $r = workbookRow($sheet, 'organization.name');
    expect($sheet->getCell('C'.$r)->getDataType())->toBe(DataType::TYPE_STRING);
    workbookUpload($book, function ($file, $path) use ($profile) {
        expect(app(IsoQuestionnaireWorkbook::class)->read($path, $profile, 'plant')['answers']['organization.name']['value'])->toStartWith('=HYPERLINK');
    });
});

test('import rejects another profile and changed factor source and invalid row data atomically', function () {
    [$audit,$profile,$client,$staff] = workbookFixture();
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'plant');
    $other = $profile->replicate();
    $other->site_id = DB::table('iso_plant_sites')->insertGetId(['company_id' => $audit->company_id, 'name' => 'Drugi zakład']);
    $other->save();
    workbookUpload($book, function ($file) use ($audit, $other, $client) {
        $this->actingAs($client)->post(route('client.audits.plant-profile.excel-import', [$audit, $other]), ['excel' => $file])->assertSessionHasErrors('excel');
        expect($other->fresh()->answers)->toBe([]);
    });
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'plant');
    $book->getSheetByName('T02')->fromArray(['energia elektryczna', '-2', 'MWh'], null, 'B2');
    workbookUpload($book, function ($file) use ($audit, $profile, $client) {
        $this->actingAs($client)->post(route('client.audits.plant-profile.excel-import', [$audit, $profile]), ['excel' => $file])->assertSessionHasErrors();
        expect($profile->fresh()->lock_version)->toBe(0)->and($profile->fresh()->answers)->toBe([]);
    });
    $profile->update(['status' => 'approved', 'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id]]);
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'factors');
    $profile->increment('lock_version');
    workbookUpload($book, function ($file) use ($audit, $profile, $staff) {
        $this->actingAs($staff)->post(route('audits.factors.excel-import', [$audit, $profile]), ['excel' => $file])->assertSessionHasErrors('excel');
        expect(IsoFactorReview::count())->toBe(0);
    });
});

test('workbooks preserve original questionnaire definitions and label options', function () {
    [, $profile] = workbookFixture();
    $profile->definition = json_decode(file_get_contents(resource_path('iso50001/plant-profile-v1.json')), true);
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'plant');
    workbookUpload($book, function ($file, $path) use ($profile) {
        $data = app(IsoQuestionnaireWorkbook::class)->read($path, $profile, 'plant');
        expect($data['as_of_date'])->toBe('2026-10-01');
        expect(app(IsoPlantQuestionnaire::class)->normalize($data['answers'], $profile->definition, [], 1, false))->toBeArray();
    });
});
