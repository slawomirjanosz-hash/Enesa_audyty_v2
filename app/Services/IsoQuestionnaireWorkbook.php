<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

class IsoQuestionnaireWorkbook
{
    private Spreadsheet $book;

    private int $listColumn = 0;

    private array $listRanges = [];

    public function download(IsoPlantProfile $profile, string $kind, ?IsoFactorReview $review = null)
    {
        $book = $this->export($profile, $kind, $review);
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'download', 'auditable_type' => Audit::class, 'auditable_id' => $profile->audit_id, 'subject_label' => 'Excel ankiety '.($kind === 'plant' ? 'Profil zakładu' : '4.1'), 'route_name' => request()->route()->getName()]);

        return response()->streamDownload(function () use ($book) {
            try {
                (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->setPreCalculateFormulas(false)->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, ($kind === 'plant' ? '1 Profil zakładu' : '4.1 Czynniki').' - '.$profile->id.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    private function context(IsoPlantProfile $profile, string $kind, ?IsoFactorReview $review): array
    {
        return ['format' => 1, 'kind' => $kind, 'audit' => $profile->audit_id, 'profile' => $profile->id,
            'deployment' => implode('|', [config('app.url'), getenv('RAILWAY_PROJECT_ID') ?: '', getenv('RAILWAY_SERVICE_ID') ?: '', getenv('RAILWAY_ENVIRONMENT_ID') ?: '']),
            'lock_version' => $kind === 'plant' ? $profile->lock_version : ($review?->lock_version ?? 0),
            'schema' => hash('sha256', json_encode($kind === 'plant' ? $profile->definition : app(IsoFactorQuestionnaire::class)->schema(), JSON_THROW_ON_ERROR)),
            'source_hash' => $kind === 'factors' ? app(IsoFactorQuestionnaire::class)->hash($profile) : null];
    }

    public function export(IsoPlantProfile $profile, string $kind, ?IsoFactorReview $review = null): Spreadsheet
    {
        $this->book = new Spreadsheet;
        $this->book->removeSheetByIndex(0);
        $this->listColumn = 0;
        $this->listRanges = [];
        $intro = $this->sheet('Instrukcja', ['Informacja', 'Wartość']);
        $rows = [['Ankieta', $kind === 'plant' ? '1 Wstęp ISO — Profil zakładu' : '4.1 Czynniki kontekstowe'], ['Audyt', (string) $profile->audit_id], ['Zakład', $profile->answers['site.name']['value'] ?? ''], ['Sposób wypełnienia', 'Wypełnij jasnoniebieskie pola. Korzystaj z list rozwijanych. Nie zmieniaj kodów pytań ani nazw arkuszy.'], ['Import', 'Import zastępuje odpowiedzi zawartością tego pliku, również pustymi polami. Przed importem zapisz plik jako XLSX.'], ['Zatwierdzenie', 'Import nie zatwierdza ankiety. Po imporcie sprawdź dane w systemie.'], ['Aktualność', 'Jeśli dane w systemie zmieniły się po eksporcie, pobierz nowy plik.'], ['Pytania warunkowe', 'W pliku są również pytania warunkowe. Warunki opisano w kolumnie Wskazówki. System ponownie oceni je po imporcie.'], ['Obliczenia', 'Pola obliczane są informacyjne; system przeliczy je po imporcie. Nie wpisuj formuł.'], ['Daty', 'Wpisuj daty w formacie RRRR-MM-DD lub jako datę Excela.'], ['Wiele odpowiedzi', 'W arkuszu Wybory zaznacz Tak przy wybranych odpowiedziach.'], ['Dane tabelaryczne', 'W arkuszach T01, T02 itd. uzupełniaj przygotowane puste wiersze. Nie usuwaj wierszy ani kolumn.']];
        foreach ($rows as $i => $row) {
            $this->row($intro, $i + 2, $row);
        }
        $intro->getColumnDimension('A')->setWidth(24);
        $intro->getColumnDimension('B')->setWidth(95);
        $lists = $this->book->createSheet()->setTitle('_Listy');
        $lists->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        $context = $this->context($profile, $kind, $review);
        $context['tables'] = [];
        if ($kind === 'plant') {
            $this->plant($profile, $context);
        } else {
            $this->factors($profile, $review, $context);
        }
        $meta = $this->book->createSheet()->setTitle('_ENESA');
        $meta->setCellValueExplicit('A1', Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), DataType::TYPE_STRING);
        $meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        foreach ($this->book->getAllSheets() as $sheet) {
            if (str_starts_with($sheet->getTitle(), '_')) {
                continue;
            }
            $last = $sheet->getHighestDataRow();
            $end = $sheet->getHighestDataColumn();
            $sheet->getStyle('A1:'.$end.$last)->getFont()->setName('Calibri')->setSize(11);
            $sheet->getStyle('A1:'.$end.$last)->getAlignment()->setWrapText(true)->setVertical('top');
            $sheet->getStyle('A1:'.$end.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1A4D3A');
            $sheet->getStyle('A1:'.$end.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getRowDimension(1)->setRowHeight(30);
            for ($r = 2; $r <= $last; $r++) {
                $sheet->getRowDimension($r)->setRowHeight($sheet->getTitle() === 'Instrukcja' ? 45 : 72);
            }
            $sheet->getProtection()->setSheet(true)->setAutoFilter(false)->setSort(false)->setFormatRows(false)->setFormatColumns(false);
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        }
        $this->book->setActiveSheetIndex(0);

        return $this->book;
    }

    private function sheet(string $title, array $headers): Worksheet
    {
        $sheet = $this->book->createSheet()->setTitle($title);
        $this->row($sheet, 1, $headers);
        $sheet->freezePane('C2');
        foreach ($headers as $i => $header) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($i === 0 ? 30 : 42);
        }

        return $sheet;
    }

    private function row(Worksheet $sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $i => $value) {
            $sheet->setCellValueExplicit([$i + 1, $row], $value ?? '', is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }
        $sheet->setAutoFilter('A1:'.$sheet->getHighestDataColumn().$row);
    }

    private function editable(Worksheet $sheet, string $cell, array $options = []): void
    {
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle($cell)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getStyle($cell)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF3FC');
        if (! $options) {
            return;
        }
        $hash = hash('sha256', json_encode(array_values($options), JSON_THROW_ON_ERROR));
        if (! isset($this->listRanges[$hash])) {
            $column = Coordinate::stringFromColumnIndex(++$this->listColumn);
            foreach (array_values($options) as $i => $label) {
                $this->book->getSheetByName('_Listy')->setCellValueExplicit($column.($i + 1), $label, DataType::TYPE_STRING);
            }
            $this->book->addNamedRange(new NamedRange('ENESA_LIST_'.$column, $this->book->getSheetByName('_Listy'), '$'.$column.'$1:$'.$column.'$'.count($options)));
            $this->listRanges[$hash] = $column;
        }
        $column = $this->listRanges[$hash];
        $sheet->getCell($cell)->getDataValidation()->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)->setErrorTitle('Wybierz z listy')->setError('Wybierz jedną z dostępnych odpowiedzi.')->setFormula1('ENESA_LIST_'.$column);
    }

    private function inputFormat(Worksheet $sheet, string $cell, array $field): void
    {
        $value = $sheet->getCell($cell)->getValue();
        if (($field['type'] ?? '') === 'number') {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.########');
            if (is_numeric($value)) {
                $sheet->setCellValueExplicit($cell, (float) $value, DataType::TYPE_NUMERIC);
            }
        } elseif (($field['type'] ?? '') === 'date') {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            if (filled($value)) {
                $sheet->setCellValueExplicit($cell, Date::dateTimeToExcel(Carbon::parse($value)), DataType::TYPE_NUMERIC);
            }
        }
    }

    private function plant(IsoPlantProfile $profile, array &$context): void
    {
        $sheet = $this->sheet('Pytania', ['Kod zmiennej', 'Dział / pytanie', 'Odpowiedź', 'Nie wiem', 'Uzupełnienie', 'Źródło', 'Wskazówki']);
        $this->row($sheet, 2, ['_as_of_date', 'Stan danych na dzień', $profile->as_of_date->format('Y-m-d')]);
        $this->editable($sheet, 'C2');
        $this->inputFormat($sheet, 'C2', ['type' => 'date']);
        $multi = $this->sheet('Wybory', ['Kod zmiennej', 'Pytanie', 'Kod opcji', 'Odpowiedź', 'Wybrano']);
        $mr = 2;
        $r = 3;
        foreach ($profile->definition['groups'] as $group) {
            foreach ($group['questions'] as $q) {
                $answer = $profile->answers[$q['key']] ?? [];
                $value = $answer['value'] ?? null;
                if ($q['type'] === 'rows') {
                    $name = 'T'.str_pad((string) (count($context['tables']) + 1), 2, '0', STR_PAD_LEFT);
                    $fields = $q['fields'];
                    $table = $this->sheet($name, ['Id wiersza', ...array_column($fields, 'label')]);
                    $ids = [];
                    $values = is_array($value) ? array_values($value) : [];
                    for ($i = 0; $i < ($q['max_items'] ?? 50); $i++) {
                        $record = $values[$i] ?? [];
                        $id = $record['id'] ?? (string) Str::uuid();
                        $ids[] = $id;
                        $cells = [$id];
                        foreach ($fields as $key => $field) {
                            $cells[] = $field['options'][$record[$key] ?? ''] ?? ($record[$key] ?? '');
                        }
                        $this->row($table, $i + 2, $cells);
                        foreach (array_values($fields) as $j => $field) {
                            $this->editable($table, Coordinate::stringFromColumnIndex($j + 2).($i + 2), $field['options'] ?? []);
                            $this->inputFormat($table, Coordinate::stringFromColumnIndex($j + 2).($i + 2), $field);
                        }
                    }
                    $table->getColumnDimension('A')->setVisible(false);
                    $context['tables'][$name] = ['key' => $q['key'], 'ids' => $ids];
                    $value = 'Wypełnij arkusz '.$name;
                } elseif ($q['type'] === 'multi') {
                    foreach ($q['options'] as $key => $label) {
                        $this->row($multi, $mr, [$q['key'], $q['label'], (string) $key, $label, in_array($key, $value ?? [], true) ? 'Tak' : 'Nie']);
                        $this->editable($multi, 'E'.$mr, ['Tak', 'Nie']);
                        $mr++;
                    }
                    $value = 'Wypełnij arkusz Wybory';
                } else {
                    $value = $q['options'][$value ?? ''] ?? $value;
                }
                $hint = ($q['hint'] ?? '').(! empty($q['unit']) ? ' Jednostka: '.$q['unit'] : '').(! empty($q['condition']) ? ' Warunek: '.$q['condition']['key'].' '.($q['condition']['operator'] ?? 'in').' '.implode(', ', $q['condition']['values']) : '');
                $this->row($sheet, $r, [$q['key'], $group['title']."\n".$q['label'], is_array($value) ? '' : $value, ($answer['unknown'] ?? false) ? 'Tak' : 'Nie', $answer['detail'] ?? '', $answer['source'] ?? '', $hint]);
                if (! in_array($q['type'], ['auto', 'rows', 'multi'])) {
                    $this->editable($sheet, 'C'.$r, $q['options'] ?? []);
                    $this->inputFormat($sheet, 'C'.$r, $q);
                }
                if ($q['type'] !== 'auto') {
                    $this->editable($sheet, 'E'.$r);
                    $this->editable($sheet, 'F'.$r);
                    if (! in_array($q['type'], ['select', 'multi']) && ! $q['required']) {
                        $this->editable($sheet, 'D'.$r, ['Tak', 'Nie']);
                    }
                }
                $r++;
            }
        }
        $sheet->getColumnDimension('B')->setWidth(65);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(65);
    }

    private function factors(IsoPlantProfile $profile, ?IsoFactorReview $review, array &$context): void
    {
        $service = app(IsoFactorQuestionnaire::class);
        $facts = $service->facts($profile);
        $answers = $service->currentAnswers($review?->answers ?? [], $review?->basis ?? [], $facts);
        $climate = $this->sheet('Klimat', ['Kod', 'Pytanie', 'Odpowiedź']);
        $this->row($climate, 2, ['FAKT_KLIMAT_ISTOTNY', 'Czy zmiana klimatu jest istotna dla systemu zarządzania energią?', $answers['FAKT_KLIMAT_ISTOTNY'] ?? '']);
        $this->editable($climate, 'C2', ['tak', 'nie']);
        $this->row($climate, 3, ['climate_reason', 'Uzasadnienie oceny', $answers['climate_reason'] ?? '']);
        $this->editable($climate, 'C3');
        $sheet = $this->sheet('Czynniki', ['Kod', 'Czynnik', 'Decyzja', 'Treść do dokumentu', 'Powód odrzucenia', 'Zastosowanie / warunek']);
        $r = 2;
        foreach ($service->factors($facts, $answers) as $factor) {
            $answer = $answers['factors'][$factor['kod']] ?? [];
            $this->row($sheet, $r, [$factor['kod'], $factor['tresc'], $answer['decyzja'] ?? '', $answer['tresc'] ?? $factor['tresc'], $answer['powod'] ?? '', ($factor['visible'] ? 'Dotyczy' : 'Warunkowy').' — '.$factor['pokaz_gdy'].($factor['rodzaj'] === 'AUTO' ? ' — obliczane w systemie' : '')]);
            if ($factor['rodzaj'] !== 'AUTO') {
                $this->editable($sheet, 'C'.$r, ['potwierdzony', 'odrzucony']);
                $this->editable($sheet, 'D'.$r);
                $this->editable($sheet, 'E'.$r);
            }$r++;
        }
        $table = $this->sheet('Własne czynniki', ['Id', 'Treść', 'Wpływ']);
        $ids = [];
        for ($i = 0; $i < 20; $i++) {
            $row = $answers['custom'][$i] ?? [];
            $ids[] = $id = $row['id'] ?? (string) Str::uuid();
            $this->row($table, $i + 2, [$id, $row['tresc'] ?? '', $row['wplyw'] ?? '']);
            $this->editable($table, 'B'.($i + 2));
            $this->editable($table, 'C'.($i + 2), ['+', '−', '○']);
        }
        $table->getColumnDimension('A')->setVisible(false);
        $context['tables']['Własne czynniki'] = ['ids' => $ids];
        $source = $this->sheet('Profil źródłowy', ['Zmienna', 'Pytanie', 'Wartość']);
        $r = 2;
        foreach (app(IsoPlantQuestionnaire::class)->questions($profile->definition) as $q) {
            $this->row($source, $r++, [$q['key'], $q['label'], app(IsoPlantQuestionnaire::class)->display($q, $profile->answers[$q['key']] ?? [])]);
        }
    }

    public function read(string $path, IsoPlantProfile $profile, string $kind, ?IsoFactorReview $review = null): array
    {
        $this->guardZip($path);
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        try {
            $info = $reader->listWorksheetInfo($path);
            if (count($info) > 30) {
                $this->fail('Za dużo arkuszy.');
            }
            foreach ($info as $sheet) {
                if ($sheet['totalRows'] > 1000 || $sheet['totalColumns'] > 500) {
                    $this->fail('Arkusz przekracza dopuszczalny rozmiar.');
                }
            }
            $book = $reader->load($path);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->fail('Nie można odczytać pliku XLSX.');
        }
        try {
            try {
                $context = json_decode(Crypt::decryptString((string) $book->getSheetByName('_ENESA')?->getCell('A1')->getValue()), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $this->fail('Brak prawidłowych danych identyfikacyjnych. Użyj pliku pobranego z tej ankiety.');
            }
            foreach ($this->context($profile, $kind, $review) as $key => $value) {
                if (($context[$key] ?? null) !== $value) {
                    $this->fail('Plik pochodzi z innej ankiety albo dane w systemie zmieniły się po eksporcie. Pobierz aktualny Excel.');
                }
            }
            foreach ($book->getAllSheets() as $sheet) {
                foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                    $cell = $sheet->getCell($coordinate);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $this->fail('Formuły nie są dozwolone. Zastąp je wartościami: '.$sheet->getTitle().'!'.$coordinate);
                    }
                    if ($sheet->getTitle() !== '_ENESA' && mb_strlen((string) $cell->getValue()) > 10000) {
                        $this->fail('Zbyt długa zawartość komórki '.$coordinate.'.');
                    }
                }
            }

            return $kind === 'plant' ? $this->readPlant($book, $profile, $context) : $this->readFactors($book, $profile, $context);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function indexed(Spreadsheet $book, string $name): array
    {
        $sheet = $book->getSheetByName($name);
        if (! $sheet) {
            $this->fail('Brak arkusza '.$name.'.');
        }$rows = [];
        for ($r = 2; $r <= $sheet->getHighestDataRow(); $r++) {
            $key = (string) $sheet->getCell('A'.$r)->getValue();
            if ($key === '' || isset($rows[$key])) {
                $this->fail('Brak lub powtórzony kod w arkuszu '.$name.', wiersz '.$r.'.');
            }
            $rows[$key] = $r;
        }

        return $rows;
    }

    private function exact(array $actual, array $expected, string $sheet): void
    {
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            $this->fail('Zmieniono kody lub usunięto wiersze w arkuszu '.$sheet.'. Pobierz plik ponownie.');
        }
    }

    private function value(Worksheet $sheet, string $cell, array $field = []): mixed
    {
        $value = $sheet->getCell($cell)->getValue();
        if ($value === null || $value === '') {
            return null;
        }
        if (isset($field['options'])) {
            foreach ($field['options'] as $key => $label) {
                if ((string) $value === (string) $label) {
                    return (string) $key;
                }
            }
            $this->fail($sheet->getTitle().'!'.$cell.': wybierz odpowiedź z listy.');
        }
        if (($field['type'] ?? null) === 'date' && is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        if (($field['type'] ?? null) === 'number' && is_string($value)) {
            $value = str_replace(["\u{00A0}", ' ', ','], ['', '', '.'], $value);
        }

        return ($field['type'] ?? '') === 'number' ? $value : trim((string) $value);
    }

    private function yes(Worksheet $sheet, string $cell): bool
    {
        $value = $this->value($sheet, $cell);
        if (! in_array($value, [null, 'Tak', 'Nie'], true)) {
            $this->fail($sheet->getTitle().'!'.$cell.': wybierz Tak lub Nie.');
        }

        return $value === 'Tak';
    }

    private function readPlant(Spreadsheet $book, IsoPlantProfile $profile, array $context): array
    {
        $questions = app(IsoPlantQuestionnaire::class)->questions($profile->definition);
        $rows = $this->indexed($book, 'Pytania');
        $this->exact(array_keys($rows), ['_as_of_date', ...array_column($questions, 'key')], 'Pytania');
        $sheet = $book->getSheetByName('Pytania');
        $answers = [];
        foreach ($questions as $q) {
            if ($q['type'] === 'auto') {
                continue;
            }$r = $rows[$q['key']];
            $answer = ['value' => null, 'unknown' => $this->yes($sheet, 'D'.$r), 'detail' => $this->value($sheet, 'E'.$r), 'source' => $this->value($sheet, 'F'.$r)];
            if ($q['type'] === 'rows') {
                $name = array_search($q['key'], array_map(fn ($t) => $t['key'] ?? null, $context['tables']), true);
                if (! $name) {
                    $this->fail('Brak tabeli danych.');
                }
                $table = $book->getSheetByName($name);
                $records = $this->indexed($book, $name);
                $this->exact(array_keys($records), $context['tables'][$name]['ids'], $name);
                $answer['value'] = [];
                foreach ($records as $id => $row) {
                    $record = ['id' => $id];
                    foreach (array_keys($q['fields']) as $i => $field) {
                        $record[$field] = $this->value($table, Coordinate::stringFromColumnIndex($i + 2).$row, $q['fields'][$field]);
                    }if (collect($record)->except('id')->contains(fn ($v) => filled($v))) {
                        $answer['value'][] = $record;
                    }
                }
            } elseif ($q['type'] === 'multi') {
                $answer['value'] = [];
            } else {
                $answer['value'] = $this->value($sheet, 'C'.$r, $q);
            }
            if ($answer['unknown'] && filled($answer['value'])) {
                $this->fail('Pytania!D'.$r.': wpisano odpowiedź i zaznaczono „Nie wiem”. Ustaw „Nie” albo usuń odpowiedź.');
            }
            $answers[$q['key']] = $answer;
        }
        $multi = $book->getSheetByName('Wybory');
        if (! $multi) {
            $this->fail('Brak arkusza Wybory.');
        }$seen = [];
        $expected = [];
        $byKey = array_column($questions, null, 'key');
        foreach ($questions as $q) {
            if ($q['type'] === 'multi') {
                foreach ($q['options'] as $key => $label) {
                    $expected[] = $q['key'].'|'.$key;
                }
            }
        }
        for ($r = 2; $r <= $multi->getHighestDataRow(); $r++) {
            $key = (string) $multi->getCell('A'.$r)->getValue();
            $option = (string) $multi->getCell('C'.$r)->getValue();
            $seen[] = $key.'|'.$option;
            if (($byKey[$key]['type'] ?? null) !== 'multi' || ! array_key_exists($option, $byKey[$key]['options'])) {
                $this->fail('Nieznana opcja w arkuszu Wybory.');
            }if ($this->yes($multi, 'E'.$r)) {
                $answers[$key]['value'][] = $option;
            }
        }
        $this->exact($seen, $expected, 'Wybory');

        return ['lock_version' => $context['lock_version'], 'as_of_date' => $this->value($sheet, 'C'.$rows['_as_of_date'], ['type' => 'date']), 'answers' => $answers];
    }

    private function readFactors(Spreadsheet $book, IsoPlantProfile $profile, array $context): array
    {
        $climate = $book->getSheetByName('Klimat');
        $rows = $this->indexed($book, 'Klimat');
        $this->exact(array_keys($rows), ['FAKT_KLIMAT_ISTOTNY', 'climate_reason'], 'Klimat');
        $answers = ['FAKT_KLIMAT_ISTOTNY' => $this->value($climate, 'C'.$rows['FAKT_KLIMAT_ISTOTNY'], ['options' => ['tak' => 'tak', 'nie' => 'nie']]), 'climate_reason' => $this->value($climate, 'C'.$rows['climate_reason']), 'factors' => [], 'custom' => []];
        $service = app(IsoFactorQuestionnaire::class);
        $factors = $service->factors($service->facts($profile), $answers);
        $rows = $this->indexed($book, 'Czynniki');
        $this->exact(array_keys($rows), array_column($factors, 'kod'), 'Czynniki');
        $sheet = $book->getSheetByName('Czynniki');
        foreach ($factors as $factor) {
            if ($factor['rodzaj'] === 'AUTO') {
                continue;
            }$r = $rows[$factor['kod']];
            $answers['factors'][$factor['kod']] = ['decyzja' => $this->value($sheet, 'C'.$r, ['options' => ['potwierdzony' => 'potwierdzony', 'odrzucony' => 'odrzucony']]), 'tresc' => $this->value($sheet, 'D'.$r), 'powod' => $this->value($sheet, 'E'.$r)];
        }
        $custom = $book->getSheetByName('Własne czynniki');
        $rows = $this->indexed($book, 'Własne czynniki');
        $this->exact(array_keys($rows), $context['tables']['Własne czynniki']['ids'], 'Własne czynniki');
        foreach ($rows as $id => $r) {
            $text = $this->value($custom, 'B'.$r);
            $impact = $this->value($custom, 'C'.$r);
            if (filled($text) || filled($impact)) {
                $answers['custom'][] = ['id' => $id, 'tresc' => $text, 'wplyw' => $impact];
            }
        }

        return ['lock_version' => $context['lock_version'], 'source_hash' => $context['source_hash'], 'answers' => $answers];
    }

    private function guardZip(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('To nie jest prawidłowy plik XLSX.');
        }
        try {
            $size = 0;
            if ($zip->numFiles > 1000) {
                $this->fail('Za dużo elementów w pliku.');
            }for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $size += $entry['size'];
                if ($size > 60000000 || preg_match('~vbaProject|externalLinks/|embeddings/~i', $entry['name'])) {
                    $this->fail('Plik zawiera niedozwolone elementy lub jest zbyt duży.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['excel' => $message]);
    }
}
