<?php

namespace App\Services;

use App\Models\EnergyPassportTemplate;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EnergyPassportImportService
{
    public function import(string $path, ?int $createdBy = null, bool $builtin = false, ?string $sourceFilename = null): EnergyPassportTemplate
    {
        $spreadsheet = IOFactory::load($path);
        $filename = $sourceFilename ?: basename($path);
        $metadata = $this->metadata($filename);
        $stages = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $stage = $this->parseSheet($sheet);
            if ($stage['sections'] !== []) {
                $stages[] = $stage;
            }
        }

        $spreadsheet->disconnectWorksheets();

        return EnergyPassportTemplate::query()->updateOrCreate(
            ['source_filename' => $filename],
            $metadata + [
                'sections' => $stages,
                'is_builtin' => $builtin,
                'created_by' => $createdBy,
            ],
        );
    }

    /** @return array{name:string,code:string,scope:string,category:string,version:string} */
    private function metadata(string $filename): array
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $scope = Str::contains($base, ['System_', 'Systemu_']) ? 'system' : 'device';
        $category = match (true) {
            Str::contains($base, ['Wentylacja', 'Wentylacji', 'AHU']) => 'Wentylacja',
            Str::contains($base, 'COOL') => 'Chłodzenie',
            Str::contains($base, 'HTG') => 'Ogrzewanie',
            Str::contains($base, 'LGT') => 'Oświetlenie',
            Str::contains($base, 'CA') => 'Sprężone powietrze',
            default => 'Inne',
        };
        $code = match (true) {
            Str::contains($base, 'AHU') => 'A-EnMS-VAC-01',
            Str::contains($base, 'CA') => 'A-EnMS-CA',
            Str::contains($base, 'COOL') => 'A-EnMS-COOL',
            Str::contains($base, 'HTG') => 'A-EnMS-HTG',
            Str::contains($base, 'LGT') => 'A-EnMS-LGT',
            Str::contains($base, ['Wentylacja', 'Wentylacji']) => 'A-EnMS-VAC',
            default => 'A-EnMS',
        };
        $version = preg_match('/_v(\d+)/i', $base, $match) ? $match[1].'.0' : '1.0';
        $displayName = preg_replace('/_v\d+$/i', '', $base) ?? $base;

        return [
            'name' => Str::of($displayName)->replace('Paszport_', '')->replace('_', ' ')->toString(),
            'code' => $code,
            'scope' => $scope,
            'category' => $category,
            'version' => $version,
        ];
    }

    /** @return array{name:string,title:string,sections:array<int,array{title:string,questions:array}>} */
    private function parseSheet(Worksheet $sheet): array
    {
        $sections = [];
        $current = ['title' => 'Pytania', 'questions' => []];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $rawCode = (string) $sheet->getCell("A{$row}")->getFormattedValue();
            $code = trim($rawCode);
            $columnB = trim((string) $sheet->getCell("B{$row}")->getFormattedValue());
            $columnC = trim((string) $sheet->getCell("C{$row}")->getFormattedValue());
            $columnD = trim((string) $sheet->getCell("D{$row}")->getFormattedValue());
            $columnE = trim((string) $sheet->getCell("E{$row}")->getFormattedValue());
            $isCodedQuestion = preg_match('/^[A-Z]{1,4}-\d{1,3}$/u', $code) && $columnB !== '';
            $isParameterRow = $row > 3
                && $code !== ''
                && ! preg_match('/^\s{2}/u', $rawCode)
                && ! in_array(Str::lower($code), ['nr', 'parametr', 'wskaźnik'], true)
                && ($columnB !== '' || $columnC !== '' || $columnD !== '' || $columnE !== '');

            if ($isCodedQuestion || $isParameterRow) {
                $questionCode = $isCodedQuestion ? $code : Str::upper(Str::substr(Str::slug($sheet->getTitle()), 0, 4)).'-'.str_pad((string) $row, 3, '0', STR_PAD_LEFT);
                $current['questions'][] = [
                    'key' => Str::slug($sheet->getTitle().'-'.$questionCode.'-'.$row),
                    'code' => $questionCode,
                    'question' => $isCodedQuestion ? $columnB : $code,
                    'unit' => $isCodedQuestion ? $columnD : $columnC,
                    'hint' => $isCodedQuestion ? $columnE : collect([$columnD, $columnE])->filter()->implode(' · '),
                ];
            } elseif ($code !== '' && $columnB === '' && $row > 3 && ! Str::startsWith($code, 'ENESA')) {
                if ($current['questions'] !== []) {
                    $sections[] = $current;
                }
                $current = ['title' => trim($code, " \t\n\r\0\x0B★"), 'questions' => []];
            }
        }
        if ($current['questions'] !== []) {
            $sections[] = $current;
        }

        return [
            'name' => $sheet->getTitle(),
            'title' => trim((string) $sheet->getCell('A1')->getFormattedValue()),
            'sections' => $sections,
        ];
    }
}
