<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialPlanExport extends DefaultValueBinder implements FromArray, WithCharts, WithCustomValueBinder, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private array $rows, private string $label, private array $chartColumns = [], private array $moneyColumns = []) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Plan';
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        $last = count($this->rows);
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->rows[6]));
        $sheet->freezePane('B8');
        $sheet->setAutoFilter('A7:'.$lastColumn.max(7, $last));
        foreach (range(1, count($this->rows[6])) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($column === 1 ? 32 : 22);
        }
        $sheet->getStyle('A7:'.$lastColumn.'7')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1A4D3A');
        $sheet->getStyle('A7:'.$lastColumn.'7')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:'.$lastColumn.$last)->getAlignment()->setWrapText(true);
        foreach ($this->moneyColumns as $column) {
            if ($last >= 8) {
                $letter = Coordinate::stringFromColumnIndex($column);
                $sheet->getStyle($letter.'8:'.$letter.$last)->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            }
        }

        return [1 => ['font' => ['bold' => true, 'size' => 16]]];
    }

    public function charts(): array
    {
        if (! $this->chartColumns || count($this->rows) < 8) {
            return [];
        }
        $last = count($this->rows);
        $labels = $values = [];
        foreach ($this->chartColumns as $column) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Plan!$'.$letter.'$7', null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Plan!$'.$letter.'$8:$'.$letter.'$'.$last, null, $last - 7);
        }
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Plan!$A$8:$A$'.$last, null, $last - 7)];
        $series = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, count($values) - 1), $labels, $categories, $values);
        $chart = new Chart('financial-plan', new Title($this->label), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$series]), true, 'gap', null, new Title('PLN netto'));
        $chart->setTopLeftPosition('A'.($last + 3));
        $chart->setBottomRightPosition('L'.($last + 24));

        return [$chart];
    }
}
