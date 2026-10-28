<?php

namespace App\Services;

class FinancialPlanChartData
{
    public static function costs(array $calculation): array
    {
        $rows = [];
        $running = 0;
        foreach ($calculation['months'] as $index => $date) {
            $forecast = $calculation['forecast'];
            $monthly = $forecast['salary'][$index] + $forecast['car'][$index] + $forecast['overhead'][$index];
            $running += $monthly;
            $rows[] = ['label' => $date->format('m.Y'), 'salary' => $forecast['salary'][$index], 'car' => $forecast['car'][$index], 'overhead' => $forecast['overhead'][$index], 'monthly' => $monthly, 'cumulative' => $running];
        }

        return $rows;
    }

    public static function result(array $calculation): array
    {
        $rows = [];
        $profit = $loss = $predicted = $offers = 0;
        foreach ($calculation['months'] as $index => $date) {
            $monthly = $calculation['forecast']['result'][$index] - $calculation['offerForecast']['revenue'][$index] + $calculation['offerForecast']['cost'][$index];
            $profit += max(0, $monthly);
            $loss += min(0, $monthly);
            $predicted += $calculation['forecast']['result'][$index];
            $offers += $calculation['offerForecast']['value'][$index];
            $rows[] = ['date' => $date->format('Y-m'), 'label' => $date->format('m.Y'), 'monthlyResult' => $monthly, 'profit' => $profit, 'loss' => $loss, 'balance' => $profit + $loss, 'predicted' => $predicted, 'offers' => $offers];
        }

        return $rows;
    }
}
