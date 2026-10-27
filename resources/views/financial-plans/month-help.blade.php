@php
    $explanation = \App\Services\FinancialPlanCalculator::EXPLANATIONS[$key] ?? 'Suma danych dla wybranego miesiąca.';
    $items = [];
    if ($tableKey === 'forecast') {
        foreach ($calculation['breakdown'][$key][$monthIndex] ?? [] as $contribution) {
            $items[] = $contribution['name'].': '.$money($contribution['amount']);
        }
    }
    if ($tableKey === 'actual') {
        $explanation = match ($key) {
            'direct_cost' => 'Koszty wystawione lub opłacone w finansach projektów. Planowane dostawy nie wchodzą do wykonania.',
            'salary', 'car', 'overhead' => 'Ręcznie wpisane kwoty wykonania za miesiące obowiązywania pozycji. Brak kwoty wykonania nie oznacza zerowego kosztu.',
            'result' => 'Wynik wykonania = zafakturowane przychody − wystawione/opłacone koszty projektów − wpisane wykonanie wynagrodzeń, samochodów i pozostałych kosztów działu. Przy brakach danych wynik jest częściowy.',
            default => $explanation,
        };
    }
    $revenue = ($rows['contracted'][$monthIndex] ?? 0) + ($rows['pipeline'][$monthIndex] ?? 0) + ($rows['manual_revenue'][$monthIndex] ?? 0);
    if ($key === 'margin') $items = ['Przychody: '.$money($revenue), 'Minus bezpośrednie koszty: '.$money($rows['direct_cost'][$monthIndex])];
    if ($key === 'result') $items = ['Marża: '.$money($rows['margin'][$monthIndex] ?? (($rows['revenue'][$monthIndex] ?? 0) - ($rows['direct_cost'][$monthIndex] ?? 0))), 'Minus wynagrodzenia: '.$money($rows['salary'][$monthIndex]), 'Minus samochody: '.$money($rows['car'][$monthIndex]), 'Minus pozostałe koszty działu: '.$money($rows['overhead'][$monthIndex])];
    if ($key === 'gap') $items = ['Wynik miesiąca: '.$money($rows['result'][$monthIndex])];
    if ($key === 'balance') $items = ['Wpływy: '.$money($rows['in'][$monthIndex]), 'Minus wydatki: '.$money($rows['out'][$monthIndex])];
@endphp
@include('financial-plans.hint', ['hintId' => 'fp-'.$tableKey.'-'.$key.'-'.$monthIndex, 'hintLabel' => number_format($value / 100, 2, ',', ' '), 'hintAria' => 'Wyjaśnienie: '.$label.' · '.$calculation['months'][$monthIndex]->format('m.Y'), 'hintTitle' => $label.' · '.$calculation['months'][$monthIndex]->format('m.Y'), 'hintText' => $explanation, 'hintItems' => $items, 'hintClass' => 'fp-value-hint'])
