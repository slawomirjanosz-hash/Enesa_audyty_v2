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
    '4-1' => [
        'context_analysis' => [
            'title' => 'Analiza kontekstu organizacji dla EnMS',
            'sample' => 'Przykładowy dokument porządkuje czynniki zewnętrzne i wewnętrzne wpływające na wynik energetyczny oraz zdolność organizacji do osiągania celów EnMS. Każdy istotny czynnik ma uzasadnienie, skutek i właściciela.',
            'sample_data' => ['Czynnik zewnętrzny' => 'Zmiany cen i dostępności energii', 'Czynnik wewnętrzny' => 'Stan techniczny instalacji', 'Wpływ na EnMS' => 'Wysoki', 'Właściciel' => 'Energy Manager', 'Przegląd' => 'Co najmniej raz w roku'],
            'fields' => [
                'organization_profile' => ['label' => 'Profil organizacji i działalności objętej EnMS', 'type' => 'textarea'],
                'external_factors' => ['label' => 'Istotne czynniki zewnętrzne', 'type' => 'textarea'],
                'internal_factors' => ['label' => 'Istotne czynniki wewnętrzne', 'type' => 'textarea'],
                'energy_impact' => ['label' => 'Wpływ czynników na wykorzystanie i zużycie energii', 'type' => 'textarea'],
                'owners' => ['label' => 'Osoby odpowiedzialne za monitorowanie zmian', 'type' => 'textarea'],
                'review_frequency' => ['label' => 'Częstotliwość przeglądu', 'type' => 'text', 'placeholder' => 'np. raz w roku i po istotnej zmianie'],
            ],
        ],
        'climate_relevance' => [
            'title' => 'Ocena istotności zmian klimatu',
            'sample' => 'Przykładowa ocena wskazuje, czy zmiany klimatu są istotne dla EnMS, jakie zjawiska analizowano i jak wpływają one na zapotrzebowanie na energię, infrastrukturę oraz ciągłość dostaw.',
            'sample_data' => ['Ocena istotności' => 'Istotne', 'Analizowane zjawisko' => 'Wzrost temperatur letnich', 'Możliwy skutek' => 'Większe zapotrzebowanie na chłód', 'Reakcja' => 'Aktualizacja EnPI i planu działań'],
            'fields' => [
                'climate_relevant' => ['label' => 'Czy zmiany klimatu są istotne dla EnMS?', 'type' => 'text', 'placeholder' => 'Tak / Nie / Wymaga dalszej analizy'],
                'climate_factors' => ['label' => 'Rozpatrzone zjawiska i scenariusze klimatyczne', 'type' => 'textarea'],
                'energy_effects' => ['label' => 'Wpływ na energię, instalacje i ciągłość działania', 'type' => 'textarea'],
                'evidence_sources' => ['label' => 'Źródła danych i dowody oceny', 'type' => 'textarea'],
                'planned_actions' => ['label' => 'Wymagane działania lub uzasadnienie braku działań', 'type' => 'textarea'],
                'assessment_date' => ['label' => 'Data oceny', 'type' => 'date'],
            ],
        ],
        'context_to_risks' => [
            'title' => 'Powiązanie kontekstu z ryzykami i planowaniem',
            'sample' => 'Przykładowy rejestr przenosi istotne wnioski z analizy kontekstu do ryzyk, szans, celów energetycznych i planów działania. Pozwala prześledzić, jaka decyzja wynika z każdego czynnika.',
            'sample_data' => ['Wniosek' => 'Ryzyko przerw w dostawach', 'Klasyfikacja' => 'Ryzyko wysokie', 'Działanie' => 'Plan ciągłości i analiza źródeł rezerwowych', 'Miernik' => 'Liczba i czas przerw', 'Termin' => 'IV kwartał'],
            'fields' => [
                'significant_findings' => ['label' => 'Istotne wnioski z analizy kontekstu', 'type' => 'textarea'],
                'risks_opportunities' => ['label' => 'Powiązane ryzyka i szanse', 'type' => 'textarea'],
                'objectives_actions' => ['label' => 'Powiązane cele i działania energetyczne', 'type' => 'textarea'],
                'indicators' => ['label' => 'Mierniki skuteczności', 'type' => 'textarea'],
                'responsible_people' => ['label' => 'Odpowiedzialni', 'type' => 'textarea'],
                'review_date' => ['label' => 'Termin następnego przeglądu', 'type' => 'date'],
            ],
        ],
    ],
];
