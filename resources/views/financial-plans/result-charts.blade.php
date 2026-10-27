@php
    $chartMonths = [];
    $revenueTotal = $costTotal = $profitTotal = $lossTotal = $predictedTotal = $offerTotal = 0;
    foreach ($calculation['months'] as $index => $month) {
        $revenue = $forecast['contracted'][$index] + $forecast['pipeline'][$index] + $forecast['manual_revenue'][$index];
        $cost = $forecast['direct_cost'][$index] + $forecast['salary'][$index] + $forecast['car'][$index] + $forecast['overhead'][$index];
        $monthlyResult = $forecast['result'][$index] - $calculation['offerForecast']['revenue'][$index] + $calculation['offerForecast']['cost'][$index];
        $predictedTotal += $forecast['result'][$index];
        $offerTotal += $calculation['offerForecast']['value'][$index];
        $revenueTotal += $revenue;
        $costTotal += $cost;
        $profitTotal += max(0, $monthlyResult);
        $lossTotal += min(0, $monthlyResult);
        $chartMonths[] = ['date' => $month->format('Y-m'), 'label' => $month->format('m.Y'), 'revenue' => $revenueTotal, 'cost' => $costTotal, 'profit' => $profitTotal, 'loss' => $lossTotal, 'balance' => $profitTotal + $lossTotal, 'predicted' => $predictedTotal, 'offers' => $offerTotal, 'monthlyResult' => $monthlyResult];
    }
@endphp
@include('financial-plans.trend-chart', [
    'chartSummaryKey' => 'predicted', 'chartSummaryNote' => 'prognoza z ofertami',
    'chartId' => 'fp-result-chart', 'chartTitle' => 'Zysk i strata · narastająco',
    'chartDescription' => 'Zyski, straty i wynik bez ofert obejmują projekty, ręczną sprzedaż i utrzymanie działu. Prognoza z ofertami dodaje przychody i koszty ofert ważone wybranym scenariuszem. Wartość ofert (100%) to przychód ofert ujętych w scenariuszu przed ważeniem, a nie zysk. Wszystkie linie są narastające.',
    'chartSeries' => ['profit' => ['label' => 'Zyski narastająco', 'color' => '#245b45'], 'loss' => ['label' => 'Straty narastająco', 'color' => '#bd493c'], 'balance' => ['label' => 'Wynik bez ofert', 'color' => '#346caa'], 'predicted' => ['label' => 'Prognoza z ofertami', 'color' => '#8b5cb0'], 'offers' => ['label' => 'Wartość ofert (100%)', 'color' => '#b87b17']],
    'chartColumns' => ['monthlyResult' => 'Wynik miesiąca', 'profit' => 'Zyski narastająco', 'loss' => 'Straty narastająco', 'balance' => 'Wynik bez ofert', 'predicted' => 'Prognoza z ofertami', 'offers' => 'Wartość ofert (100%)'],
])
