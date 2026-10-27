@php
    $chartMonths = [];
    $revenueTotal = $costTotal = $profitTotal = $lossTotal = 0;
    foreach ($calculation['months'] as $index => $month) {
        $revenue = $forecast['contracted'][$index] + $forecast['pipeline'][$index] + $forecast['manual_revenue'][$index];
        $cost = $forecast['direct_cost'][$index] + $forecast['salary'][$index] + $forecast['car'][$index] + $forecast['overhead'][$index];
        $monthlyResult = $forecast['result'][$index];
        $revenueTotal += $revenue;
        $costTotal += $cost;
        $profitTotal += max(0, $monthlyResult);
        $lossTotal += min(0, $monthlyResult);
        $chartMonths[] = ['date' => $month->format('Y-m'), 'label' => $month->format('m.Y'), 'revenue' => $revenueTotal, 'cost' => $costTotal, 'profit' => $profitTotal, 'loss' => $lossTotal, 'balance' => $revenueTotal - $costTotal, 'monthlyResult' => $monthlyResult];
    }
@endphp
@include('financial-plans.trend-chart', [
    'chartId' => 'fp-result-chart', 'chartTitle' => 'Zysk i strata · narastająco',
    'chartDescription' => 'Zielona linia sumuje dodatnie wyniki miesięcy, czerwona sumuje straty. Przerywana linia pokazuje wynik netto od początku wybranego okresu. To prognoza uwzględniająca scenariusz oraz wszystkie koszty projektów i utrzymania działu.',
    'chartSeries' => ['profit' => ['label' => 'Zyski narastająco', 'color' => '#245b45'], 'loss' => ['label' => 'Straty narastająco', 'color' => '#bd493c'], 'balance' => ['label' => 'Wynik netto', 'color' => '#346caa']],
    'chartColumns' => ['monthlyResult' => 'Wynik miesiąca', 'profit' => 'Zyski narastająco', 'loss' => 'Straty narastająco', 'balance' => 'Wynik netto'],
])
@include('financial-plans.account-chart')
