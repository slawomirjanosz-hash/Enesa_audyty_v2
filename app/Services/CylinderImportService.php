<?php

namespace App\Services;

use App\Http\Requests\CylinderRequest;
use App\Models\Company;
use App\Models\Cylinder;
use App\Models\CylinderImport;
use App\Models\CylinderInspectionSeries;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CylinderImportService
{
    public const MAX_ROWS = 300;

    public function columns(): array
    {
        return Cylinder::PARAMETER_LABELS + ['notes' => 'Uwagi'];
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value)));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    public function parse(UploadedFile $file, ?int $companyId): array
    {
        if (file_get_contents($file->getRealPath(), false, null, 0, 2) === 'PK') {
            $zip = new \ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                $this->fail('Nie można otworzyć pliku XLSX.');
            }
            $size = 0;
            if ($zip->numFiles > 2000) {
                $zip->close();
                $this->fail('Skoroszyt zawiera zbyt wiele elementów. Użyj prostego pliku według szablonu.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            $zip->close();
            if ($size > 25 * 1024 * 1024) {
                $this->fail('Plik po rozpakowaniu przekracza 25 MB. Podziel wykaz na mniejsze pliki.');
            }
        }
        try {
            $type = IOFactory::identify($file->getRealPath(), ['Xlsx', 'Xls']);
            $reader = IOFactory::createReader($type);
            $info = $reader->listWorksheetInfo($file->getRealPath());
            $sheetInfo = collect($info)->firstWhere('worksheetName', 'Urządzenia') ?? $info[0] ?? null;
            if (! $sheetInfo || $sheetInfo['totalRows'] > self::MAX_ROWS + 1 || $sheetInfo['totalColumns'] > 40) {
                $this->fail('Arkusz może zawierać maksymalnie 300 wierszy urządzeń i 40 kolumn. Usuń także formatowanie pustych wierszy poza tym zakresem.');
            }
            $reader->setReadDataOnly(false)->setLoadSheetsOnly($sheetInfo['worksheetName']);
            $book = $reader->load($file->getRealPath());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            $this->fail('Nie udało się odczytać skoroszytu. Użyj poprawnego pliku XLSX lub XLS.');
        }
        try {
            $sheet = $book->getSheet(0);
            $aliases = [];
            foreach ($this->columns() as $field => $label) {
                $aliases[$this->normalize($field)] = $field;
                $aliases[$this->normalize($label)] = $field;
            }
            // Keep previously downloaded templates compatible after label changes.
            foreach (['Znak wytwórczy' => 'manufacturer_mark', 'Symbol masy przewożonej / dopuszczalnej' => 'filling_mass_symbol', 'Osprzęt — znak Π lub CE' => 'equipment_mark'] as $label => $field) {
                $aliases[$this->normalize($label)] = $field;
            }
            $columns = [];
            for ($column = 1; $column <= $sheetInfo['totalColumns']; $column++) {
                $cell = $sheet->getCell([$column, 1]);
                $header = trim((string) $cell->getValue());
                if ($header === '') {
                    continue;
                }
                $field = $aliases[$this->normalize($header)] ?? null;
                if ($cell->getDataType() === DataType::TYPE_FORMULA || ! $field || in_array($field, $columns, true)) {
                    $this->fail('W pierwszym wierszu są nierozpoznane lub powtórzone nagłówki. Użyj nagłówków z szablonu.');
                }
                $columns[$column] = $field;
            }
            foreach (['name', 'manufacturer_mark', 'serial_number'] as $field) {
                if (! in_array($field, $columns, true)) {
                    $this->fail('Brakuje wymaganej kolumny: '.$this->columns()[$field].'.');
                }
            }
            $rows = [];
            $seen = [];
            for ($line = 2; $line <= $sheetInfo['totalRows']; $line++) {
                $data = [];
                $errors = [];
                $nonEmpty = false;
                for ($column = 1; $column <= $sheetInfo['totalColumns']; $column++) {
                    $cell = $sheet->getCell([$column, $line]);
                    $value = $cell->getValue();
                    if ($value === null || trim((string) $value) === '') {
                        continue;
                    }
                    $nonEmpty = true;
                    if (! isset($columns[$column])) {
                        $errors[] = 'Dane w kolumnie bez nagłówka.';

                        continue;
                    }
                    $field = $columns[$column];
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $errors[] = 'Formuły nie są obsługiwane: '.$this->columns()[$field].'. Wklej wartości.';

                        continue;
                    }
                    $data[$field] = trim((string) (in_array($field, ['serial_number', 'manufacturer_mark', 'inventory_number'], true) ? $cell->getFormattedValue() : $value));
                }
                if (! $nonEmpty) {
                    continue;
                }
                foreach (['manufacturer_mark', 'serial_number'] as $field) {
                    $data[$field] = Str::upper($data[$field] ?? '');
                }
                foreach (Cylinder::DECIMAL_PARAMETERS as $field) {
                    if (isset($data[$field])) {
                        $data[$field] = str_replace(',', '.', $data[$field]);
                    }
                }
                $request = new CylinderRequest($data);
                $request->setRouteResolver(fn () => null);
                $rules = $request->rules();
                $rules['serial_number'] = ['required', 'string', 'max:100'];
                unset($rules['company_id']);
                $validator = Validator::make($data, $rules, [], $request->attributes());
                $errors = [...$errors, ...$validator->errors()->all()];
                $key = json_encode([$data['manufacturer_mark'], $data['serial_number']], JSON_THROW_ON_ERROR);
                if (isset($seen[$key])) {
                    $errors[] = 'Powtórzona identyfikacja z wiersza '.$seen[$key].'.';
                }
                $seen[$key] = $line;
                $existing = Cylinder::where('manufacturer_mark', $data['manufacturer_mark'])->where('serial_number', $data['serial_number'])->first();
                if ($existing?->archived_at) {
                    $errors[] = 'Urządzenie jest w archiwum. Przywróć je przed importem.';
                }
                if ($existing && $companyId && (int) $existing->company_id !== $companyId) {
                    $errors[] = 'Istniejące urządzenie nie jest przypisane do wybranego klienta. Import nie zmienia przypisania.';
                }
                $rows[] = ['line' => $line, 'data' => $data, 'errors' => $errors, 'existing_id' => $existing?->id, 'existing_hash' => $existing ? $this->deviceHash($existing) : null];
            }
            if (! $rows) {
                $this->fail('Arkusz nie zawiera urządzeń do importu.');
            }

            return $rows;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function deviceHash(Cylinder $device): string
    {
        return hash('sha256', json_encode($device->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function commit(CylinderImport $import): CylinderImport
    {
        try {
            return DB::transaction(function () use ($import) {
                $import = CylinderImport::lockForUpdate()->findOrFail($import->id);
                if ($import->completed_at) {
                    return $import;
                }
                abort_if($import->expires_at->isPast(), 410, 'Podgląd importu wygasł. Wczytaj plik ponownie.');
                if (collect($import->rows)->contains(fn ($row) => $row['errors'] !== [])) {
                    throw ValidationException::withMessages(['import' => 'Popraw błędne wiersze i wczytaj plik ponownie. Niczego nie zapisano.']);
                }
                if ($import->company_id && ! Company::clients()->active()->whereKey($import->company_id)->exists()) {
                    throw ValidationException::withMessages(['import' => 'Wybrany klient nie jest już dostępny. Wczytaj plik ponownie.']);
                }
                if ($import->create_series && ! User::whereKey($import->responsible_id)->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['client_admin', 'client_user']))->exists()) {
                    throw ValidationException::withMessages(['import' => 'Osoba prowadząca serię nie jest już dostępna. Wczytaj plik ponownie.']);
                }
                $ids = [];
                foreach ($import->rows as $row) {
                    $device = Cylinder::where('manufacturer_mark', $row['data']['manufacturer_mark'])->where('serial_number', $row['data']['serial_number'])->lockForUpdate()->first();
                    if (($device?->id !== $row['existing_id']) || ($device && ! hash_equals($row['existing_hash'], $this->deviceHash($device)))) {
                        throw ValidationException::withMessages(['import' => 'Rejestr zmienił się od podglądu. Wczytaj plik ponownie. Niczego nie zapisano.']);
                    }
                    if (! $device) {
                        $device = Cylinder::create($row['data'] + ['company_id' => $import->company_id, 'device_type' => 'butla', 'type' => $row['data']['name']]);
                    }
                    $ids[] = $device->id;
                }
                if ($import->create_series) {
                    $series = CylinderInspectionSeries::create(['name' => $import->series_name, 'company_id' => $import->company_id, 'responsible_id' => $import->responsible_id, 'created_by' => $import->user_id]);
                    $series->items()->createMany(array_map(fn ($id) => ['cylinder_id' => $id], $ids));
                    $import->series_id = $series->id;
                }
                $import->completed_at = now();
                $import->save();

                return $import;
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages(['import' => 'Wystąpił konflikt danych. Wczytaj plik ponownie. Niczego nie zapisano.']);
            }
            throw $exception;
        }
    }
}
