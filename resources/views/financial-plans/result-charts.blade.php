@php
    $chartMonths = \App\Services\FinancialPlanChartData::result($calculation);
@endphp
@include('financial-plans.trend-chart', [
    'chartSummaryKey' => 'predicted', 'chartSummaryNote' => 'prognoza z ofertami',
    'chartId' => 'fp-result-chart', 'chartTitle' => 'Zysk i strata · narastająco',
    'chartDescription' => 'Zyski, straty i wynik bez ofert obejmują projekty, ręczną sprzedaż i utrzymanie działu. Prognoza z ofertami dodaje pełne przychody i koszty z harmonogramów ofert. Wartość ofert (100%) to pełny przychód ofert z harmonogramów, a nie zysk. Wszystkie linie są narastające.',
    'chartSeries' => ['profit' => ['label' => 'Zyski narastająco', 'color' => '#245b45'], 'loss' => ['label' => 'Straty narastająco', 'color' => '#bd493c'], 'balance' => ['label' => 'Wynik bez ofert', 'color' => '#346caa'], 'predicted' => ['label' => 'Prognoza z ofertami', 'color' => '#8b5cb0'], 'offers' => ['label' => 'Wartość ofert (100%)', 'color' => '#b87b17']],
    'chartColumns' => ['monthlyResult' => 'Wynik miesiąca', 'profit' => 'Zyski narastająco', 'loss' => 'Straty narastająco', 'balance' => 'Wynik bez ofert', 'predicted' => 'Prognoza z ofertami', 'offers' => 'Wartość ofert (100%)'],
])
