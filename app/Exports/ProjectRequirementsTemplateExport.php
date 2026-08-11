<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectRequirementsTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function headings(): array
    {
        return [[
            'Rodzaj', 'Nazwa', 'Technologia', 'Ilość', 'Jednostka', 'Szacowany koszt łącznie', 'Potrzebne do',
            'Status', 'Dostawca', 'NIP dostawcy', 'E-mail odpowiedzialnego', 'Osoba odpowiedzialna', 'Opis / uwagi',
        ]];
    }

    public function array(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Materiały i usługi';
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:M1');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1:M1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:M1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1A4D3A');
        $sheet->getStyle('A1:M1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A2:M200')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFAFAF6');
        $sheet->getStyle('D2:D200')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F2:F200')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('G2:G200')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('M2:M200')->getAlignment()->setWrapText(true);

        $typeValidation = $sheet->getCell('A2')->getDataValidation();
        $typeValidation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
            ->setFormula1('"Materiał,Usługa"')->setSqref('A2:A200');
        $statusValidation = $sheet->getCell('H2')->getDataValidation();
        $statusValidation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
            ->setFormula1('"Planowane,Zapotrzebowanie,Zamówione,W realizacji,Kupione,Anulowane"')->setSqref('H2:H200');

        $comments = [
            'A1' => 'Opcjonalnie: Materiał albo Usługa. Puste pole oznacza materiał.',
            'B1' => 'Pole wymagane.',
            'C1' => 'Np. Pomiary, Zawory, Obieg kotłowy albo oznaczenie ze schematu technologicznego.',
            'D1' => 'Opcjonalnie. Domyślna ilość to 1.',
            'F1' => 'Łączny koszt pozycji, nie cena jednostkowa.',
            'G1' => 'Akceptowane są daty Excela oraz formaty np. 2026-08-31 i 31.08.2026.',
            'I1' => 'Nazwa zostanie dopasowana do dostawcy z CRM, jeśli istnieje.',
            'J1' => 'NIP pozwala dokładniej dopasować dostawcę z CRM.',
            'K1' => 'E-mail członka zespołu projektu.',
        ];
        foreach ($comments as $cell => $comment) {
            $sheet->getComment($cell)->getText()->createTextRun($comment);
        }

        return [];
    }
}
