<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class FinancialPlanExcelFormulas
{
    public static function build(string $section, array $rows): array
    {
        $formulas = [];
        $lastColumn = count($rows[6]);
        if (in_array($section, ['forecast', 'cash'], true)) {
            $end = Coordinate::stringFromColumnIndex($lastColumn - 1);
            $total = Coordinate::stringFromColumnIndex($lastColumn);
            for ($row = 8; $row <= count($rows); $row++) {
                $formulas[$total.$row] = $section === 'forecast' && $row === 17 ? '=MAX(0,-'.$total.'16)' : '=SUM(B'.$row.':'.$end.$row.')';
            }
            for ($column = 2; $column < $lastColumn; $column++) {
                $letter = Coordinate::stringFromColumnIndex($column);
                if ($section === 'forecast') {
                    $formulas[$letter.'12'] = '=SUM('.$letter.'8:'.$letter.'10)-'.$letter.'11';
                    $formulas[$letter.'16'] = '='.$letter.'12-SUM('.$letter.'13:'.$letter.'15)';
                    $formulas[$letter.'17'] = '=MAX(0,-'.$letter.'16)';
                } else {
                    $formulas[$letter.'10'] = '='.$letter.'8-'.$letter.'9';
                }
            }

            return $formulas;
        }
        for ($row = 8; $row <= count($rows); $row++) {
            if ($section === 'sources') {
                $income = $cost = [];
                for ($column = 12; $column <= $lastColumn; $column += 2) {
                    $income[] = Coordinate::stringFromColumnIndex($column).$row;
                    $cost[] = Coordinate::stringFromColumnIndex($column + 1).$row;
                }
                $formulas['I'.$row] = '=SUM('.implode(',', $income).')';
                $formulas['J'.$row] = '=SUM('.implode(',', $cost).')';
            } elseif ($section === 'people') {
                $formulas['F'.$row] = '=ROUND(SUM(C'.$row.',E'.$row.')*G'.$row.',2)';
            } elseif ($section === 'result') {
                $formulas['B'.$row] = '=H'.$row.'-SUM(I'.$row.':J'.$row.')';
                $formulas['C'.$row] = '=SUMIF($B$8:B'.$row.',">0")';
                $formulas['D'.$row] = '=SUMIF($B$8:B'.$row.',"<0")';
                $formulas['E'.$row] = '=SUM(C'.$row.':D'.$row.')';
                $formulas['F'.$row] = '=SUM($B$8:B'.$row.')+SUM($K$8:K'.$row.')-SUM($L$8:L'.$row.')';
                $formulas['G'.$row] = '=SUM($M$8:M'.$row.')';
            } else {
                $formulas['E'.$row] = '=SUM(B'.$row.':D'.$row.')';
                if ($section === 'cost-cumulative') {
                    foreach (['B' => 'F', 'C' => 'G', 'D' => 'H'] as $target => $source) {
                        $formulas[$target.$row] = '=SUM($'.$source.'$8:'.$source.$row.')';
                    }
                } elseif ($section === 'cost-values') {
                    $formulas['F'.$row] = '=SUM($E$8:E'.$row.')';
                }
            }
        }

        return $formulas;
    }
}
