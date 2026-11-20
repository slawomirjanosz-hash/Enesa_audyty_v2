<?php

namespace App\Services\Enms;

use App\Models\Audit;
use App\Services\IsoQuestionnaireWorkbook;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Uses the existing Excel conventions; import never approves or changes old mappings. */
final class LeadershipWorkbook extends IsoQuestionnaireWorkbook
{
    private function fields(Workspace $w, bool $includeSelfAssessment = true): array
    {
        $fields = [];
        $service = app(LeadershipWorkflow::class);
        if ($includeSelfAssessment) {
            foreach ($service->library->data()['PYTANIA_4'] as $question) {
                foreach (['stan' => ['label' => 'Odpowiedź kierownictwa', 'options' => LeadershipLibrary::SELF_ASSESSMENT_STATES], 'komentarz' => ['label' => 'Komentarz — opcjonalnie']] as $path => $field) {
                    $fields['SAMO.'.$question['id'].'.'.$path] = $field + ['kind' => 'self_assessment', 'id' => $question['id'], 'path' => $path, 'code' => $question['id'], 'question' => $question['nazwa']];
                }
            }
        }
        foreach ($service->library->positions() as $id => $r) {
            $states = ['jest' => 'Jest i można pokazać', 'czesciowo' => 'Częściowo', 'niema' => 'Jeszcze tego nie ma'];
            if (! $r['normative'] && ($r['dopuszczaNiedotyczy'] ?? true)) {
                $states['niedotyczy'] = 'Nie dotyczy';
            }
            $schema = ['stan' => ['label' => 'Odpowiedź', 'options' => $states], 'opis' => ['label' => 'Komentarz organizacji'], 'plan.opis' => ['label' => 'Co zamierzacie zrobić'], 'plan.termin' => ['label' => 'Do kiedy'], 'plan.kto' => ['label' => 'Kto'], 'uzasadnienieNiedotyczy' => ['label' => 'Dlaczego nie dotyczy']];
            foreach ($r['pola'] as $f) {
                if ($f['typ'] !== 'odwolanie') {
                    $schema['pola.'.$f['klucz']] = ['label' => $f['etykieta'], 'type' => $f['typ'] === 'data' ? 'date' : 'text'] + ($f['typ'] === 'wybor' ? ['options' => array_combine($f['opcje'], $f['opcje'])] : []);
                }
            }
            foreach ($schema as $path => $field) {
                $fields[$id.'/'.$path] = $field + ['id' => $id, 'path' => $path, 'code' => $r['kod'], 'question' => $r['question']];
            }
        }

        return $fields;
    }

    private function stamp(Workspace $w, bool $includeSelfAssessment = true): array
    {
        return ['format' => $includeSelfAssessment ? 'enms-2' : 'enms-1', 'audit' => $w->audit->id, 'company' => $w->audit->company_id, 'profile' => $w->profile->id, 'revision' => $w->revision(), 'source' => app(LeadershipWorkflow::class)->sources($w)['hash'], 'layout' => hash('sha256', json_encode($this->fields($w, $includeSelfAssessment), JSON_THROW_ON_ERROR)), 'deployment' => config('app.url')];
    }

    public function build(Workspace $w): Spreadsheet
    {
        $service = app(LeadershipWorkflow::class);
        $this->book = new Spreadsheet;
        $this->book->removeSheetByIndex(0);
        $this->listColumn = 0;
        $this->listRanges = [];
        $intro = $this->sheet('Instrukcja', ['Informacja', 'Wartość']);
        foreach ([['Ankieta', '5.1 Przywództwo i zaangażowanie'], ['Organizacja', $w->audit->company->name], ['Wypełnienie', 'Edytuj niebieskie pola w arkuszu Odpowiedzi. Nie zmieniaj kodów. Import zastępuje także puste odpowiedzi, nie zatwierdza ankiety.'], ['Dowody', 'Arkusz Dowody jest informacyjny. Dowody i ich przypisania edytuj w rejestrze platformy.'], ['Źródła', 'Cytaty z innych punktów są odczytywane ponownie przez serwer przy imporcie. Zmiana danych po eksporcie wymaga pobrania nowego pliku.']] as $i => $r) {
            $this->row($intro, $i + 2, $r);
        }
        $this->book->createSheet()->setTitle('_Listy')->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        $answers = $this->sheet('Odpowiedzi', ['Mapowanie', 'Kod', 'Pytanie', 'Pole', 'Odpowiedź']);
        $i = 2;
        foreach ($this->fields($w) as $key => $field) {
            $selfAssessment = ($field['kind'] ?? '') === 'self_assessment';
            $p = $service->one($w, $selfAssessment ? 'self_assessment' : 'declaration', $selfAssessment ? 'question_id' : 'requirement_id', $field['id'])['payload'] ?? [];
            $value = data_get($p, $field['path']);
            $this->row($answers, $i, [$key, $field['code'], $field['question'], $field['label'], isset($field['options']) ? ($field['options'][$value ?? ''] ?? '') : $value]);
            $this->editable($answers, 'E'.$i, array_values($field['options'] ?? []));
            $i++;
        }
        $answers->getColumnDimension('A')->setVisible(false);
        $proofs = $this->sheet('Dowody', ['Symbol', 'Rodzaj', 'Tytuł', 'Identyfikator', 'Data', 'Gdzie', 'Dotyczy']);
        foreach (array_values($w->records('evidence')) as $i => $r) {
            $p = $r['payload'];
            $this->row($proofs, $i + 2, [$r['symbol'], $p['rodzaj'] ?? '', $p['tytul'] ?? '', $p['identyfikator'] ?? '', $p['data'] ?? '', $p['gdzie'] ?? '', implode(', ', array_map(fn ($id) => $service->library->positions()[$id]['kod'] ?? 'Pozycja historyczna', $p['requirements'] ?? []))]);
        }
        $meta = $this->book->createSheet()->setTitle('_ENESA');
        $meta->setCellValueExplicit('A1', Crypt::encryptString(json_encode($this->stamp($w), JSON_THROW_ON_ERROR)), DataType::TYPE_STRING);
        $meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        foreach ($this->book->getAllSheets() as $sheet) {
            if (str_starts_with($sheet->getTitle(), '_')) {
                continue;
            } $end = $sheet->getHighestDataColumn();
            $last = $sheet->getHighestDataRow();
            $sheet->getStyle('A1:'.$end.$last)->getAlignment()->setWrapText(true)->setVertical('top');
            $sheet->getStyle('A1:'.$end.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A1:'.$end.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1A4D3A');
            $sheet->getProtection()->setSheet(true)->setAutoFilter(false)->setSort(false);
        }
        $this->book->setActiveSheetIndex(0);

        return $this->book;
    }

    public function import(Workspace $w, string $path): void
    {
        abort_unless($w->writable, 403);
        $this->guardZip($path);
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $info = $reader->listWorksheetInfo($path);
        if (count($info) > 5 || array_sum(array_map(fn ($s) => $s['totalRows'] * $s['totalColumns'], $info)) > 20000) {
            $this->fail('Plik przekracza rozmiar szablonu.');
        }
        $book = $reader->load($path);
        try {
            try {
                $stamp = json_decode(Crypt::decryptString((string) $book->getSheetByName('_ENESA')?->getCell('A1')->getValue()), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $this->fail('Brak prawidłowego mapowania pliku. Pobierz Excel z tej ankiety.');
            }
            foreach ($book->getAllSheets() as $sheet) {
                foreach ($sheet->getCellCollection()->getCoordinates() as $coord) {
                    $cell = $sheet->getCell($coord);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA || mb_strlen((string) $cell->getValue()) > 12000) {
                        $this->fail('Niedozwolona formuła lub za długa wartość: '.$sheet->getTitle().'!'.$coord);
                    }
                }
            }
            if (! in_array($stamp['format'] ?? '', ['enms-1', 'enms-2'], true)) {
                $this->fail('Nieobsługiwana wersja pliku. Pobierz aktualny Excel.');
            }
            // Old signed workbooks retain every original mapping and leave SAMO untouched.
            $includeSelfAssessment = $stamp['format'] === 'enms-2';
            $fields = $this->fields($w, $includeSelfAssessment);
            $indices = $this->indexed($book, 'Odpowiedzi');
            $this->exact(array_keys($indices), array_keys($fields), 'Odpowiedzi');
            $data = [];
            foreach ($fields as $key => $f) {
                Arr::set($data[$f['kind'] ?? 'declaration'][$f['id']], $f['path'], $this->value($book->getSheetByName('Odpowiedzi'), 'E'.$indices[$key], $f));
            }
            DB::transaction(function () use ($w, $stamp, $data, $includeSelfAssessment) {
                Audit::whereKey($w->audit->id)->lockForUpdate()->firstOrFail();
                if ($stamp !== $this->stamp($w, $includeSelfAssessment)) {
                    $this->fail('Plik pochodzi z innej ankiety albo dane zmieniły się po eksporcie. Pobierz aktualny Excel.');
                }
                $service = app(LeadershipWorkflow::class);
                foreach ($data['declaration'] ?? [] as $id => $p) {
                    // Evidence links are informative in this workbook and must not be implicitly adopted or removed.
                    $service->saveDeclaration($w, $w->revision(), $stamp['source'], $id, $p, syncEvidence: false);
                }
                foreach ($data['self_assessment'] ?? [] as $id => $p) {
                    $service->saveSelfAssessment($w, $w->revision(), $stamp['source'], $id, $p);
                }
            });
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
