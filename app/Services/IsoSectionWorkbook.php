<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\IsoFactorReview;
use App\Models\IsoImplementationResponse;
use App\Models\IsoPlantProfile;
use App\Models\IsoStakeholderReview;
use App\Models\IsoSystemReview;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Offline client answers only; approvals and consultant notes never leave the workflow. */
class IsoSectionWorkbook extends IsoQuestionnaireWorkbook
{
    private function layout(IsoPlantProfile $profile, string $section): array
    {
        $extra = [];
        if ($section === '4-2') {
            $service = app(IsoStakeholderQuestionnaire::class);
            $review = IsoStakeholderReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->first();
            $factor = IsoFactorReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->first();
            $parties = $service->parties($profile, $factor);
            $answers = $service->currentAnswers($review?->answers ?? [], $review?->basis ?? [], $parties);
            $sourceHash = $service->hash($profile, $factor);
            $groups = $this->stakeholders($answers, $parties, $extra, $profile->id);
            $title = '4.2 Strony zainteresowane';
        } else {
            abort_unless(isset(IsoSystemReview::TITLES[$section]), 404);
            $service = app(IsoSystemQuestionnaire::class);
            $sources = $service->sources($profile, $section);
            $review = IsoSystemReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->where('section', $section)->first();
            $answers = $review?->answers ?? $service->seed($section, $sources);
            $sourceHash = $sources['hash'];
            if (str_starts_with($section, '6-')) {
                $answers = app(IsoPlanningQuestionnaire::class)->projectCards($section, $answers, $sources);
            }
            if (IsoExecutionQuestionnaire::handles($section)) {
                $execution = app(IsoExecutionQuestionnaire::class);
                $answers = $execution->project($section, $answers, $sources);
                foreach ($execution->tree($section) as $root => $node) {
                    $this->prepareTree($node, $root, $answers, $extra);
                }
            } else {
                foreach ($service->sections($section, $answers, $sources) as $group) {
                    if (isset($group['repeater'])) {
                        $this->prepareRows($group['repeater'], $answers, $extra, 5);
                    }
                }
            }
            $groups = $service->sections($section, $answers, $sources);
            $title = IsoSystemReview::TITLES[$section];
        }
        // Stable, unique choice labels also identify newly prepared rows before they have names.
        foreach ($groups as &$group) {
            foreach ($group['fields'] as &$field) {
                if ($field['options'] ?? []) {
                    foreach ($field['options'] as $key => &$label) {
                        $label = (string) $label.' ['.$key.']';
                    }
                    unset($label);
                }
            }
            unset($field);
        }
        unset($group);
        $context = ['format' => 2, 'section' => $section, 'audit' => $profile->audit_id, 'profile' => $profile->id,
            'deployment' => implode('|', [config('app.url'), getenv('RAILWAY_PROJECT_ID') ?: '', getenv('RAILWAY_SERVICE_ID') ?: '', getenv('RAILWAY_ENVIRONMENT_ID') ?: '']),
            'profile_version' => $profile->lock_version, 'lock_version' => $review?->lock_version ?? 0,
            'source_hash' => $sourceHash, 'layout' => hash('sha256', json_encode($groups, JSON_THROW_ON_ERROR))];

        return compact('groups', 'answers', 'extra', 'context', 'title');
    }

    private function prepareRows(string $path, array &$answers, array &$extra, int $target): void
    {
        $rows = data_get($answers, $path, []);
        $rows = is_array($rows) ? $rows : [];
        for ($i = 0; count($rows) < $target; $i++) {
            if (! array_key_exists($i, $rows)) {
                $rows[$i] = [];
            }
        }
        foreach (array_keys($rows) as $i) {
            $extra[] = $path.'.'.$i;
        }
        data_set($answers, $path, $rows);
    }

    private function prepareTree(array $node, string $path, array &$answers, array &$extra, int $depth = 0): void
    {
        if ($node['type'] === 'list') {
            if (! ($node['fixed'] ?? false)) {
                $this->prepareRows($path, $answers, $extra, $depth === 0 ? 5 : 2);
            }
            foreach (array_keys(data_get($answers, $path, [])) as $i) {
                foreach ($node['children'] as $key => $child) {
                    $this->prepareTree($child, "$path.$i.$key", $answers, $extra, $depth + 1);
                }
            }
        } elseif ($node['type'] === 'object') {
            foreach ($node['children'] as $key => $child) {
                $this->prepareTree($child, "$path.$key", $answers, $extra, $depth);
            }
        }
    }

    private function stakeholders(array &$answers, array $parties, array &$extra, int $profileId): array
    {
        $field = fn ($key, $label, $options = [], $type = 'text', $help = '') => compact('key', 'label', 'options', 'type', 'help');
        $groups = [['title' => 'Klimat i przegląd', 'fields' => [
            $field('FAKT_KLIMAT_STRONY', 'Czy strony mają wymagania związane ze zmianą klimatu?', ['tak' => 'Tak', 'nie' => 'Nie']),
            $field('climate_reason', 'Wymagania klimatyczne lub uzasadnienie ich braku'),
            $field('review_interval', 'Częstotliwość przeglądu', ['kwartal' => 'Co kwartał', 'polrocze' => 'Co pół roku', 'rok' => 'Co rok', 'rok_umowy' => 'Co rok i przy zmianie umów']),
        ]]];
        $definitions = [];
        foreach ($parties as $party) {
            $path = 'parties.'.$party['kod'];
            foreach (['wymagania', 'jak'] as $key) {
                if (data_get($answers, "$path.$key") === null) {
                    data_set($answers, "$path.$key", $party[$key]);
                }
            }
            $definitions[$path] = [$party['kod'].' — '.$party['nazwa'], ($party['visible'] ? 'Dotyczy' : 'Warunkowe').' — '.($party['warunek'] ?? '')];
        }
        $identifierBasis = hash('sha256', json_encode($answers['custom'] ?? [], JSON_THROW_ON_ERROR));
        $this->prepareRows('custom', $answers, $extra, 5);
        foreach ($answers['custom'] as $i => &$row) {
            // Deterministic placeholder UUID: layout is reconstructed rather than trusted from the file.
            $hash = hash('sha256', "stakeholder:$profileId:$identifierBasis:$i");
            $row['id'] ??= substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
            $definitions['custom.'.$i] = ['Własna strona — pozycja '.($i + 1), 'Puste dodatkowe pozycje nie będą importowane.'];
        }
        unset($row);
        foreach ($definitions as $path => [$title, $help]) {
            $custom = str_starts_with($path, 'custom.');
            $fields = $custom ? [$field("$path.nazwa", 'Nazwa strony'), $field("$path.typ", 'Typ', ['wewnętrzna' => 'Wewnętrzna', 'zewnętrzna' => 'Zewnętrzna'])]
                : [$field("$path.decyzja", 'Decyzja', ['potwierdzona' => 'Potwierdzam', 'odrzucona' => 'Odrzucam'])];
            foreach (['wymagania' => 'Wymagania i oczekiwania', 'jak' => 'Jak uwzględniono w systemie', 'powod' => 'Uzasadnienie', ...IsoStakeholderQuestionnaire::REQUIREMENT_FIELDS] as $key => $label) {
                $fields[] = $field("$path.$key", $label, $key === 'kwalifikacja' ? IsoStakeholderQuestionnaire::QUALIFICATIONS : [], in_array($key, ['zr_data', 'data_brzmienia']) ? 'date' : 'text', $help);
            }
            $groups[] = compact('title', 'fields');
        }

        return $groups;
    }

    public function sectionExport(IsoPlantProfile $profile, string $section): Spreadsheet
    {
        return $this->exportLayout($this->layout($profile, $section), $profile->answers['site.name']['value'] ?? '');
    }

    private function implementationLayout(Audit $audit, string $action): array
    {
        $workflow = config('iso50001-workflows.3-1.'.$action);
        abort_unless(is_array($workflow), 404);
        $response = IsoImplementationResponse::where('audit_id', $audit->id)->where('section_id', '3-1')->where('action_key', $action)->first();
        $title = '3.1 '.$workflow['title'];
        $answers = $response?->answers ?? [];
        $groups = [['title' => $title, 'fields' => collect($workflow['fields'])->map(fn ($field, $key) => ['key' => $key] + $field)->values()->all()]];
        $context = ['format' => 2, 'audit' => $audit->id, 'section' => '3-1', 'action' => $action,
            'deployment' => implode('|', [config('app.url'), getenv('RAILWAY_PROJECT_ID') ?: '', getenv('RAILWAY_SERVICE_ID') ?: '', getenv('RAILWAY_ENVIRONMENT_ID') ?: '']),
            'state' => hash('sha256', json_encode([$response?->id, $response?->updated_at, $answers, $groups], JSON_THROW_ON_ERROR)),
            'lock_version' => 0, 'source_hash' => ''];

        return compact('groups', 'answers', 'context', 'title') + ['extra' => []];
    }

    public function implementationExport(Audit $audit, string $action): Spreadsheet
    {
        return $this->exportLayout($this->implementationLayout($audit, $action), $audit->company->name);
    }

    public function implementationRead(string $path, Audit $audit, string $action): array
    {
        return $this->readLayout($path, $this->implementationLayout($audit, $action));
    }

    private function exportLayout(array $layout, string $site): Spreadsheet
    {
        $this->book = new Spreadsheet;
        $this->book->removeSheetByIndex(0);
        $this->listColumn = 0;
        $this->listRanges = [];
        $intro = $this->sheet('Instrukcja', ['Informacja', 'Wartość']);
        foreach ([['Ankieta', $layout['title']], ['Zakład', $site],
            ['Wypełnianie', 'Wypełnij niebieskie komórki Odpowiedź. Nie zmieniaj kodów, arkuszy ani wierszy. Zapisz plik jako XLSX.'],
            ['Wiele odpowiedzi', 'Wiersze z kodem opcji oznacz Tak lub Nie.'],
            ['Pozycje powtarzalne', 'Przygotowano minimum 5 pozycji, a w podtabelach 2. Pozostaw niewykorzystane pozycje puste. Więcej pozycji dodaj w systemie przed eksportem. Numery w kodach wiążą dane tej samej pozycji.'],
            ['Dane źródłowe', 'Szare odpowiedzi pochodzą z innych punktów i nie są importowane. Warunkowe pytania są pokazane, a system ponownie oceni ich zastosowanie.'],
            ['Daty', 'Wpisz datę RRRR-MM-DD lub datę Excela. Nie używaj formuł.'],
            ['Import', 'Import zastępuje odpowiedzi (również pustymi polami), nie zatwierdza ankiety. Plik musi pochodzić z tego zakładu i aktualnego stanu ankiety.'],
            ['Zatwierdzenia', 'Klient i audytor zatwierdzają osobno w aplikacji. Notatki konsultanta nie są eksportowane ani zmieniane.'],
        ] as $i => $row) {
            $this->row($intro, $i + 2, $row);
        }
        $intro->getColumnDimension('B')->setWidth(100);
        $this->sheet('_Listy', [])->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        foreach ($this->sheets($layout['groups']) as $name => $rows) {
            $this->row($intro, $intro->getHighestDataRow() + 1, [$name, strtok($rows[0]['label'], "\n")]);
            $sheet = $this->sheet($name, ['Kod zmiennej / opcji', 'Dział / pytanie', 'Odpowiedź', 'Wskazówki']);
            $sheet->getColumnDimension('A')->setWidth(34);
            $sheet->getColumnDimension('B')->setWidth(55);
            $sheet->getColumnDimension('C')->setWidth(45);
            $sheet->getColumnDimension('D')->setWidth(55);
            foreach ($rows as $i => $row) {
                $f = $row['field'];
                $value = data_get($layout['answers'], $f['key']);
                if (isset($row['option'])) {
                    $value = in_array((string) $row['option'], array_map('strval', (array) $value), true) ? 'Tak' : 'Nie';
                } else {
                    $value = $f['options'][$value ?? ''] ?? $value;
                }
                $hint = $f['help'] ?? '';
                if ($f['when'] ?? null) {
                    $hint .= ' Warunek: '.$f['when'][0].' = '.implode(' / ', $f['when'][1]);
                }
                $r = $i + 2;
                $readonly = $f['readonly'] ?? false;
                $this->row($sheet, $r, [$row['id'], $row['label'], is_scalar($value) ? $value : '', ($readonly ? 'Z danych źródłowych — tylko do odczytu. ' : '').$hint]);
                if (! $readonly) {
                    $this->editable($sheet, 'C'.$r, isset($row['option']) ? ['Tak', 'Nie'] : ($f['options'] ?? []));
                    if (! isset($row['option']) && empty($f['options'])) {
                        $this->inputFormat($sheet, 'C'.$r, $f);
                    }
                } else {
                    $sheet->getStyle('C'.$r)->getFill()->setFillType('solid')->getStartColor()->setRGB('F0F2F1');
                }
            }
        }
        $meta = $this->sheet('_ENESA', []);
        $meta->setCellValueExplicit('A1', Crypt::encryptString(json_encode($layout['context'], JSON_THROW_ON_ERROR)), DataType::TYPE_STRING);
        $meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        foreach ($this->book->getAllSheets() as $sheet) {
            if ($sheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE) {
                continue;
            }
            $range = 'A1:'.$sheet->getHighestDataColumn().$sheet->getHighestDataRow();
            $sheet->getStyle($range)->getFont()->setName('Calibri')->setSize(11);
            $sheet->getStyle($range)->getAlignment()->setWrapText(true)->setVertical('top');
            $sheet->getStyle('A1:'.$sheet->getHighestDataColumn().'1')->getFill()->setFillType('solid')->getStartColor()->setRGB('1A4D3A');
            $sheet->getStyle('A1:'.$sheet->getHighestDataColumn().'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getRowDimension(1)->setRowHeight(30);
            for ($r = 2; $r <= $sheet->getHighestDataRow(); $r++) {
                $sheet->getRowDimension($r)->setRowHeight($sheet === $intro ? 54 : 84);
            }
            $sheet->getProtection()->setSheet(true)->setAutoFilter(false)->setFormatRows(false)->setFormatColumns(false);
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
        }
        $this->book->setActiveSheetIndex(0);

        return $this->book;
    }

    /** Keep sheets bounded and in the same order as the questionnaire, including conditional fields. */
    private function sheets(array $groups): array
    {
        $sheets = [];
        foreach ($groups as $group) {
            $rows = [];
            foreach ($group['fields'] as $field) {
                if (str_starts_with($field['key'], 'CHECK.')) {
                    continue;
                }
                $label = $group['title']."\n".$field['label'];
                if (($field['type'] ?? '') === 'multi') {
                    foreach ($field['options'] as $key => $option) {
                        $rows[] = ['id' => $field['key'].'|'.$key, 'label' => $label.' — '.$option, 'field' => $field, 'option' => (string) $key];
                    }
                } else {
                    $rows[] = ['id' => $field['key'], 'label' => $label, 'field' => $field];
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                $sheets['Dzial '.str_pad((string) (count($sheets) + 1), 2, '0', STR_PAD_LEFT)] = $chunk;
            }
        }

        return $sheets;
    }

    public function sectionRead(string $path, IsoPlantProfile $profile, string $section): array
    {
        return $this->readLayout($path, $this->layout($profile, $section));
    }

    private function readLayout(string $path, array $layout): array
    {
        $this->guardZip($path);
        $reader = new Xlsx;
        // Keep formula types: never evaluate or trust cached formula results.
        $reader->setReadDataOnly(false);
        try {
            $info = $reader->listWorksheetInfo($path);
            if (count($info) > 100 || array_sum(array_map(fn ($s) => $s['totalRows'] * $s['totalColumns'], $info)) > 100000) {
                $this->fail('Plik zawiera zbyt dużo komórek lub arkuszy.');
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
                $this->fail('Brak prawidłowych danych identyfikacyjnych. Pobierz Excel z tej ankiety.');
            }
            if ($context !== $layout['context']) {
                $this->fail('Plik pochodzi z innej ankiety albo dane w systemie zmieniły się po eksporcie. Pobierz aktualny Excel.');
            }
            foreach ($book->getAllSheets() as $sheet) {
                foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                    $cell = $sheet->getCell($coordinate);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA || ($sheet->getTitle() !== '_ENESA' && mb_strlen((string) $cell->getValue()) > 10000)) {
                        $this->fail('Niedozwolona formuła lub zbyt długa wartość: '.$sheet->getTitle().'!'.$coordinate);
                    }
                }
            }
            $answers = $layout['answers'];
            $multi = [];
            foreach ($this->sheets($layout['groups']) as $name => $fields) {
                $rows = $this->indexed($book, $name);
                $this->exact(array_keys($rows), array_column($fields, 'id'), $name);
                $sheet = $book->getSheetByName($name);
                foreach ($fields as $row) {
                    $f = $row['field'];
                    if ($f['readonly'] ?? false) {
                        continue;
                    }
                    $cell = 'C'.$rows[$row['id']];
                    if (isset($row['option'])) {
                        $multi[$f['key']] ??= [];
                        if ($this->yes($sheet, $cell)) {
                            $multi[$f['key']][] = $row['option'];
                        }
                    } else {
                        if (empty($f['options'])) {
                            unset($f['options']);
                        }
                        data_set($answers, $f['key'], $this->value($sheet, $cell, $f));
                    }
                }
            }
            foreach ($multi as $key => $value) {
                data_set($answers, $key, $value);
            }
            foreach (array_reverse($layout['extra']) as $path) {
                $row = data_get($answers, $path, []);
                unset($row['id']);
                if (! collect(Arr::dot($row))->contains(fn ($v) => filled($v))) {
                    Arr::forget($answers, $path);
                }
            }

            return ['answers' => $answers, 'lock_version' => $context['lock_version'], 'source_hash' => $context['source_hash']];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function sectionDownload(IsoPlantProfile $profile, string $section)
    {
        $book = $this->sectionExport($profile, $section);
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'download', 'auditable_type' => Audit::class, 'auditable_id' => $profile->audit_id, 'subject_label' => 'Excel ankiety ISO '.str_replace('-', '.', $section), 'route_name' => request()->route()->getName()]);

        return $this->stream($book, 'Ankieta ISO '.str_replace('-', '.', $section).' - '.$profile->id.'.xlsx');
    }

    public function implementationDownload(Audit $audit, string $action)
    {
        $book = $this->implementationExport($audit, $action);
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'download', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => 'Excel ankiety ISO 3.1 '.$action, 'route_name' => request()->route()->getName()]);

        return $this->stream($book, 'Ankieta ISO 3.1 '.$action.' - '.$audit->id.'.xlsx');
    }

    private function stream(Spreadsheet $book, string $filename)
    {
        return response()->streamDownload(function () use ($book) {
            try {
                (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->setPreCalculateFormulas(false)->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }
}
