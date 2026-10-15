<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Cylinder;
use App\Models\CylinderImport;
use App\Models\CylinderInspectionSeries;
use App\Models\User;
use App\Services\CylinderImportService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;

function cylinderImportFile(array $rows, ?array $headers = null, string $format = 'Xlsx'): UploadedFile
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('Urządzenia');
    $sheet->fromArray($headers ?? ['Nazwa urządzenia', 'Znak wytwórczy', 'Numer fabryczny', 'Ciśnienie robocze [bar]', 'Temperatura dopuszczalna min. [°C]'], null, 'A1');
    foreach ($rows as $index => $row) {
        $sheet->fromArray($row, null, 'A'.($index + 2));
    }
    $path = tempnam(sys_get_temp_dir(), 'cylinder-import-');
    $writer = IOFactory::createWriter($book, $format);
    $writer->setPreCalculateFormulas(false)->save($path);
    $book->disconnectWorksheets();

    return new UploadedFile($path, 'devices.'.strtolower($format), null, null, true);
}

beforeEach(function () {
    CompanySettings::create(['name' => 'TPED', 'enabled_modules' => ['cylinders']]);
    $this->operator = User::factory()->create();
    $this->operator->assignRole(Role::findOrCreate('superadmin'));
    $this->company = Company::create(['name' => 'Klient bez NIP', 'company_type' => 'client', 'status' => 'pending']);
    $this->actingAs($this->operator);
});

test('Excel preview writes no devices then imports new records and a resumable series exactly once', function () {
    $file = cylinderImportFile([['Butla 1', ' acme ', ' sn-01 ', '200,125', '-20,5'], ['Butla 2', 'ACME', 'SN-02', '200', '-10']]);
    $this->post(route('cylinders.import.preview'), ['file' => $file, 'company_id' => $this->company->id, 'create_series' => 1, 'series_name' => 'Z importu', 'responsible_id' => $this->operator->id])->assertRedirect()->assertSessionHasNoErrors();
    $import = CylinderImport::sole();
    expect(Cylinder::count())->toBe(0)->and(CylinderInspectionSeries::count())->toBe(0);
    $this->get(route('cylinders.import.show', $import))->assertOk()->assertSee('SN-01')->assertSee('Zatwierdź import');
    $this->post(route('cylinders.import.store', $import), ['rows' => [['name' => 'FORGED']]])->assertSessionHasNoErrors()->assertRedirect();
    expect(Cylinder::count())->toBe(2)->and(CylinderInspectionSeries::sole()->status)->toBe('draft')->and(CylinderInspectionSeries::sole()->items()->count())->toBe(2);
    $device = Cylinder::where('serial_number', 'SN-01')->sole();
    expect((float) $device->working_pressure_bar)->toBe(200.125)->and((float) $device->temperature_min_c)->toBe(-20.5)->and($device->company_id)->toBe($this->company->id);
    $this->post(route('cylinders.import.store', $import))->assertRedirect();
    expect(Cylinder::count())->toBe(2)->and(CylinderInspectionSeries::count())->toBe(1)->and($import->fresh()->completed_at)->not->toBeNull();
    expect(ActivityLog::where('auditable_type', CylinderImport::class)->first()->changes)->not->toHaveKey('rows');
});

test('Excel matching preserves existing parameters client and inspection history and accepts another manufacturer', function () {
    $existing = Cylinder::create(['name' => 'Dane z rejestru', 'type' => 'Butla', 'manufacturer_mark' => 'ACME', 'serial_number' => '00123', 'working_pressure_bar' => 150, 'company_id' => $this->company->id]);
    $entry = $existing->inspections()->create(['inspected_at' => '2026-01-01', 'result' => 'no_findings', 'observations' => 'Historia', 'inspector_name' => 'Inspektor']);
    $this->post(route('cylinders.import.preview'), ['file' => cylinderImportFile([['Nazwa z arkusza', 'acme', '00123', '200'], ['Nowa butla', 'OTHER', '00123', '200']]), 'create_series' => 1, 'series_name' => 'Wspólna', 'responsible_id' => $this->operator->id])->assertSessionHasNoErrors();
    $import = CylinderImport::sole();
    expect($import->rows[0]['existing_id'])->toBe($existing->id);
    $this->get(route('cylinders.import.show', $import))->assertOk()->assertSee('Wykorzystaj istniejące');
    $this->post(route('cylinders.import.store', $import))->assertSessionHasNoErrors();
    expect($existing->fresh()->name)->toBe('Dane z rejestru')->and((float) $existing->fresh()->working_pressure_bar)->toBe(150.0)
        ->and($existing->fresh()->company_id)->toBe($this->company->id)->and($entry->fresh()->observations)->toBe('Historia')
        ->and(Cylinder::count())->toBe(2)->and(Cylinder::where('manufacturer_mark', 'OTHER')->sole()->company_id)->toBeNull();
});

test('duplicate identity formulas invalid parameters and missing identities block the entire import', function () {
    $this->post(route('cylinders.import.preview'), ['file' => cylinderImportFile([
        ['Dobra', 'ACME', 'SN1', '200'], ['Duplikat', 'acme', 'sn1', '200'],
        ['Błędna', '', 'SN3', '-5'], ['Formuła', 'ACME', '=1+1', '200'],
    ])])->assertSessionHasNoErrors();
    $import = CylinderImport::sole();
    $this->get(route('cylinders.import.show', $import))->assertOk()->assertSee('Powtórzona identyfikacja')->assertSee('Formuły nie są obsługiwane')->assertDontSee('Zatwierdź import');
    $this->post(route('cylinders.import.store', $import))->assertSessionHasErrors('import');
    expect(Cylinder::count())->toBe(0)->and(CylinderInspectionSeries::count())->toBe(0);
});

test('archived devices and client conflicts cannot be silently reassigned during import', function () {
    $device = Cylinder::create(['name' => 'Istniejąca', 'type' => 'Butla', 'manufacturer_mark' => 'ACME', 'serial_number' => 'SN1']);
    $this->post(route('cylinders.import.preview'), ['file' => cylinderImportFile([['Nowa nazwa', 'ACME', 'SN1']]), 'company_id' => $this->company->id])->assertSessionHasNoErrors();
    expect(CylinderImport::sole()->rows[0]['errors'])->not->toBeEmpty();
    $this->post(route('cylinders.import.store', CylinderImport::sole()))->assertSessionHasErrors('import');
    $device->update(['archived_at' => now()]);
    $this->post(route('cylinders.import.preview'), ['file' => cylinderImportFile([['Nowa nazwa', 'ACME', 'SN1']])])->assertSessionHasNoErrors();
    $import = CylinderImport::latest('id')->first();
    $this->get(route('cylinders.import.show', $import))->assertOk()->assertSee('Urządzenie jest w archiwum');
    expect($device->fresh()->company_id)->toBeNull()->and($device->fresh()->name)->toBe('Istniejąca');
});

test('stale device previews and newly appeared duplicates roll back all device creation', function () {
    $device = Cylinder::create(['name' => 'Istniejąca', 'type' => 'Butla', 'manufacturer_mark' => 'ACME', 'serial_number' => 'SN2']);
    $this->post(route('cylinders.import.preview'), ['file' => cylinderImportFile([['Nowa', 'ACME', 'SN1'], ['Inna', 'ACME', 'SN2']])])->assertSessionHasNoErrors();
    $device->update(['notes' => 'Zmiana po podglądzie']);
    $this->post(route('cylinders.import.store', CylinderImport::sole()))->assertSessionHasErrors('import');
    expect(Cylinder::count())->toBe(1);
    $this->post(route('cylinders.import.preview'), ['file' => cylinderImportFile([['Nowa', 'ACME', 'SN1']])])->assertSessionHasNoErrors();
    Cylinder::create(['name' => 'Dodana równocześnie', 'type' => 'Butla', 'manufacturer_mark' => 'ACME', 'serial_number' => 'SN1']);
    $this->post(route('cylinders.import.store', CylinderImport::latest('id')->first()))->assertSessionHasErrors('import');
    expect(Cylinder::count())->toBe(2);
});

test('import previews are user owned expire and recheck client eligibility', function () {
    $this->post(route('cylinders.import.preview'), ['file' => cylinderImportFile([['Nowa', 'ACME', 'SN1']]), 'company_id' => $this->company->id])->assertSessionHasNoErrors();
    $import = CylinderImport::sole();
    $other = User::factory()->create();
    $other->assignRole('superadmin');
    $this->actingAs($other)->get(route('cylinders.import.show', $import))->assertNotFound();
    $this->post(route('cylinders.import.store', $import))->assertNotFound();
    $this->actingAs($this->operator);
    $this->company->update(['archived_at' => now()]);
    $this->post(route('cylinders.import.store', $import))->assertSessionHasErrors('import');
    $import->update(['expires_at' => now()->subMinute()]);
    $this->get(route('cylinders.import.show', $import))->assertStatus(410);
    $this->post(route('cylinders.import.store', $import))->assertStatus(410);
    expect(Cylinder::count())->toBe(0);
});

test('template has fixed headers and text identifiers while xls and missing optional columns import', function () {
    $this->get(route('cylinders.import.template'))->assertOk()->assertDownload('szablon-urzadzen.xlsx');
    $book = IOFactory::load(resource_path('templates/cylinder-import.xlsx'));
    $sheet = $book->getSheetByName('Urządzenia');
    expect($sheet->rangeToArray('A1:S1')[0])->toBe(array_values(app(CylinderImportService::class)->columns()))
        ->and($sheet->getStyle('D2')->getNumberFormat()->getFormatCode())->toBe('@');
    $book->disconnectWorksheets();
    $this->post(route('cylinders.import.preview'), ['file' => cylinderImportFile([['Nowa', 'ACME', 'SN1']], ['name', 'manufacturer_mark', 'serial_number'], 'Xls')])->assertSessionHasNoErrors();
    $this->post(route('cylinders.import.store', CylinderImport::sole()))->assertSessionHasNoErrors();
    expect(Cylinder::sole()->company_id)->toBeNull();
});

test('downloaded template roundtrips identifiers with leading zeros and numeric zero', function () {
    $book = IOFactory::load(resource_path('templates/cylinder-import.xlsx'));
    $sheet = $book->getSheetByName('Urządzenia');
    $sheet->setCellValue('A2', 'Butla z szablonu');
    $sheet->setCellValue('B2', 'ACME');
    $sheet->setCellValue('D2', '000123');
    $sheet->setCellValue('G2', 0);
    $path = tempnam(sys_get_temp_dir(), 'cylinder-template-');
    IOFactory::createWriter($book, 'Xlsx')->save($path);
    $book->disconnectWorksheets();
    $this->post(route('cylinders.import.preview'), ['file' => new UploadedFile($path, 'filled.xlsx', null, null, true)])->assertSessionHasNoErrors();
    $this->post(route('cylinders.import.store', CylinderImport::sole()))->assertSessionHasNoErrors();
    expect(Cylinder::sole()->serial_number)->toBe('000123')->and((float) Cylinder::sole()->temperature_min_c)->toBe(0.0);
});

test('import rejects missing duplicate unknown headers oversized sheets and empty workbooks', function () {
    foreach ([
        cylinderImportFile([['Nowa', 'SN1']], ['Nazwa urządzenia', 'Numer fabryczny']),
        cylinderImportFile([['Nowa', 'ACME', 'SN1', 'x']], ['name', 'manufacturer_mark', 'serial_number', 'unexpected']),
        cylinderImportFile([['Nowa', 'ACME', 'SN1', 'x']], ['name', 'manufacturer_mark', 'serial_number', 'name']),
        cylinderImportFile([]),
        cylinderImportFile(array_fill(0, 301, ['Nowa', 'ACME', 'SN1'])),
    ] as $file) {
        $this->post(route('cylinders.import.preview'), ['file' => $file])->assertSessionHasErrors('file');
    }
    expect(CylinderImport::count())->toBe(0)->and(Cylinder::count())->toBe(0);
});

test('import endpoints require write permission and enabled module', function () {
    $reader = User::factory()->create();
    $role = Role::findOrCreate('import_reader');
    $role->givePermissionTo('cylinders.view');
    $reader->assignRole($role);
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    foreach ([$reader, $client] as $user) {
        $this->actingAs($user);
        $this->get(route('cylinders.import.create'))->assertForbidden();
        $this->get(route('cylinders.import.template'))->assertForbidden();
        $this->get(route('cylinders.import.show', 'unknown'))->assertForbidden();
        $this->post(route('cylinders.import.preview'), ['file' => cylinderImportFile([['Nowa', 'ACME', 'SN1']])])->assertForbidden();
        $this->post(route('cylinders.import.store', 'unknown'))->assertForbidden();
    }
    $this->actingAs($this->operator);
    CompanySettings::first()->update(['enabled_modules' => []]);
    $this->get(route('cylinders.import.create'))->assertForbidden();
    expect(Cylinder::count())->toBe(0);
});
