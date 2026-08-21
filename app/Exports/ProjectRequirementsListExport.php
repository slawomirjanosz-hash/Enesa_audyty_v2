<?php

namespace App\Exports;

use App\Models\Project;
use App\Models\ProjectRequirement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectRequirementsListExport implements FromArray, WithStyles, WithTitle
{
    private const STATUS_LABELS = [
        'planned' => 'Planowane',
        'requested' => 'Zapotrzebowanie',
        'ordered' => 'Zamówione',
        'in_progress' => 'W realizacji',
        'purchased' => 'Kupione',
        'cancelled' => 'Anulowane',
    ];

    public function __construct(
        private readonly Project $project,
        private readonly Collection $requirements,
        private readonly string $documentType,
        private readonly string $supplierLabel,
        private readonly array $statuses,
        private readonly bool $includePrices,
    ) {}

    public function array(): array
    {
        $title = $this->documentType === 'order' ? 'ZAMÓWIENIE' : 'ZAPYTANIE OFERTOWE';
        $statusLabels = collect($this->statuses)
            ->map(fn (string $status) => self::STATUS_LABELS[$status] ?? $status)
            ->implode(', ');

        $rows = [
            [$title],
            ['Projekt', $this->project->number.' — '.$this->project->name],
            ['Klient', $this->project->company?->name ?? 'Projekt wewnętrzny'],
            ['Dostawca', $this->supplierLabel],
            ['Wybrane statusy', $statusLabels],
            ['Data wygenerowania', now()->format('d.m.Y H:i')],
            [],
            [
                'Lp.', 'Rodzaj', 'Nazwa', 'Technologia', 'Opis / uwagi', 'Ilość', 'Jednostka',
                'Potrzebne do', 'Status', 'Dostawca',
                $this->includePrices ? 'Cena jednostkowa netto' : 'Cena oferowana netto',
                $this->includePrices ? 'Wartość netto' : 'Wartość oferowana netto',
                'Uwagi dostawcy',
            ],
        ];

        foreach ($this->requirements->values() as $index => $requirement) {
            /** @var ProjectRequirement $requirement */
            $excelRow = $index + 8;
            $quantity = (float) $requirement->quantity;
            $unitCost = $this->includePrices ? $requirement->unitCost() : null;
            $total = $this->includePrices
                ? ($requirement->estimated_cost !== null ? (float) $requirement->estimated_cost : null)
                : sprintf('=IF(OR(F%d="",K%d=""),"",F%d*K%d)', $excelRow, $excelRow, $excelRow, $excelRow);

            $rows[] = [
                $index + 1,
                $requirement->type === 'service' ? 'Usługa' : 'Materiał',
                $requirement->name,
                $requirement->technology,
                $requirement->description,
                $quantity,
                $requirement->displayUnit(),
                $requirement->needed_by?->format('Y-m-d'),
                self::STATUS_LABELS[$requirement->status] ?? $requirement->status,
                $requirement->supplierCompany?->name ?? $requirement->supplier,
                $unitCost,
                $total,
                null,
            ];
        }

        $firstDataRow = 8;
        $lastDataRow = $firstDataRow + $this->requirements->count() - 1;
        $sumFormula = $this->requirements->isEmpty() ? null : sprintf('=SUM(L%d:L%d)', $firstDataRow, $lastDataRow);
        $rows[] = [null, null, null, null, null, null, null, null, null, 'Łącznie netto', null, $sumFormula, null];

        return $rows;
    }

    public function title(): string
    {
        return $this->documentType === 'order' ? 'Zamówienie' : 'Zapytanie ofertowe';
    }

    public function styles(Worksheet $sheet): array
    {
        $lastDataRow = 7 + $this->requirements->count();
        $totalRow = $lastDataRow + 1;

        $sheet->mergeCells('A1:M1');
        foreach (range(2, 6) as $row) {
            $sheet->mergeCells("B{$row}:M{$row}");
        }

        $sheet->freezePane('A8');
        if ($lastDataRow >= 8) {
            $sheet->setAutoFilter("A7:M{$lastDataRow}");
        }

        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getStyle('A1:M1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:M1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1A4D3A');
        $sheet->getStyle('A1:M1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A2:A6')->getFont()->setBold(true)->getColor()->setARGB('FF1A4D3A');
        $sheet->getStyle('A7:M7')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A7:M7')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1A4D3A');
        $sheet->getStyle('A7:M7')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(7)->setRowHeight(34);

        if ($lastDataRow >= 8) {
            $sheet->getStyle("A8:M{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE4E7E2');
            $sheet->getStyle("C8:E{$lastDataRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("F8:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("H8:H{$lastDataRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            $sheet->getStyle("K8:L{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00 [$zł-pl-PL]');
        }

        $sheet->getStyle("J{$totalRow}:L{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("J{$totalRow}:L{$totalRow}")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE8F1EB');
        $sheet->getStyle("L{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00 [$zł-pl-PL]');

        $widths = [
            'A' => 7, 'B' => 12, 'C' => 34, 'D' => 20, 'E' => 40, 'F' => 11, 'G' => 12,
            'H' => 15, 'I' => 18, 'J' => 28, 'K' => 19, 'L' => 20, 'M' => 34,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
        }

        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(13).$totalRow)
            ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        return [];
    }
}
