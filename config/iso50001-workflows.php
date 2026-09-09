<?php

return [
    '3-1' => [
        'baseline' => [
            'title' => 'Bazowy koszt energii i potencjał poprawy',
            'sample' => 'Przykładowa analiza zestawia pełny rok faktur i odczytów dla każdego nośnika energii. Pokazuje zużycie, koszt, źródło danych oraz ostrożny cel poprawy. Wynik powinien być możliwy do zweryfikowania na podstawie załączonych danych.',
            'sample_data' => ['Okres bazowy' => '01.2025–12.2025', 'Łączny koszt energii' => '1 250 000 zł/rok', 'Potencjał poprawy' => '8%', 'Szacowana oszczędność' => '100 000 zł/rok', 'Podstawa' => 'Faktury, odczyty liczników i dane produkcyjne'],
            'fields' => [
                'analysis_period' => ['label' => 'Okres bazowy', 'type' => 'text', 'placeholder' => 'np. 01.2025–12.2025'],
                'electricity_cost' => ['label' => 'Roczny koszt energii elektrycznej [zł]', 'type' => 'number'],
                'gas_cost' => ['label' => 'Roczny koszt gazu [zł]', 'type' => 'number'],
                'other_energy_cost' => ['label' => 'Koszt pozostałych nośników [zł]', 'type' => 'number'],
                'total_energy_cost' => ['label' => 'Łączny bazowy koszt energii [zł]', 'type' => 'number'],
                'improvement_potential' => ['label' => 'Realistyczny potencjał poprawy [%]', 'type' => 'number'],
                'estimated_savings' => ['label' => 'Szacowane oszczędności roczne [zł]', 'type' => 'number'],
                'assumptions' => ['label' => 'Źródła danych, założenia i sposób obliczeń', 'type' => 'textarea'],
            ],
        ],
        'business_case' => [
            'title' => 'Zwrot z inwestycji w perspektywie 3–5 lat',
            'sample' => 'Przykładowe uzasadnienie biznesowe obejmuje koszt wdrożenia i utrzymania EnMS, planowane nakłady techniczne, oszczędności roczne, prosty okres zwrotu oraz korzyści i ryzyka niefinansowe.',
            'sample_data' => ['Horyzont analizy' => '5 lat', 'Wdrożenie i utrzymanie' => '180 000 zł', 'Planowane inwestycje' => '320 000 zł', 'Oszczędności roczne' => '140 000 zł', 'Prosty okres zwrotu' => '3,6 roku'],
            'fields' => [
                'analysis_horizon' => ['label' => 'Horyzont analizy [lata]', 'type' => 'number'],
                'implementation_cost' => ['label' => 'Koszt wdrożenia i utrzymania [zł]', 'type' => 'number'],
                'investment_cost' => ['label' => 'Planowane nakłady inwestycyjne [zł]', 'type' => 'number'],
                'annual_savings' => ['label' => 'Przewidywane oszczędności roczne [zł]', 'type' => 'number'],
                'payback_period' => ['label' => 'Przewidywany okres zwrotu [lata]', 'type' => 'number'],
                'benefits' => ['label' => 'Korzyści biznesowe i niefinansowe', 'type' => 'textarea'],
                'risks' => ['label' => 'Najważniejsze ryzyka i sposób ich ograniczenia', 'type' => 'textarea'],
            ],
        ],
        'sponsor' => [
            'title' => 'Sponsor wdrożenia po stronie kierownictwa',
            'sample' => 'Przykładowa decyzja wskazuje sponsora z najwyższego kierownictwa, jego uprawnienia, zapewniane zasoby, sposób raportowania oraz datę zatwierdzenia rozpoczęcia wdrożenia.',
            'sample_data' => ['Sponsor' => 'Jan Kowalski, Dyrektor Operacyjny', 'Uprawnienia' => 'Zatwierdzanie zasobów i usuwanie barier', 'Raportowanie' => 'Raz w miesiącu do zarządu', 'Decyzja' => 'Zatwierdzona i zakomunikowana zespołowi'],
            'fields' => [
                'sponsor_name' => ['label' => 'Imię i nazwisko sponsora', 'type' => 'text'],
                'sponsor_position' => ['label' => 'Stanowisko', 'type' => 'text'],
                'decision_date' => ['label' => 'Data decyzji', 'type' => 'date'],
                'authority' => ['label' => 'Zakres odpowiedzialności i uprawnień', 'type' => 'textarea'],
                'resources' => ['label' => 'Zapewnione zasoby', 'type' => 'textarea'],
                'reporting' => ['label' => 'Sposób i częstotliwość raportowania', 'type' => 'textarea'],
            ],
        ],
    ],
];
