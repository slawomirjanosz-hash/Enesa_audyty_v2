<?php

return [
    'chapters' => [
        ['id' => 'intro', 'number' => '1', 'title' => 'Wstęp o ISO', 'description' => 'Podstawowe informacje o ISO 50001 i systemie zarządzania energią.'],
        ['id' => 'training', 'number' => '2', 'title' => 'Filmy szkoleniowe', 'description' => 'Materiały szkoleniowe wspierające wdrożenie i prowadzenie EnMS.'],
        ['id' => 'reserve', 'number' => '3', 'title' => 'Rezerwa', 'description' => 'Miejsce przeznaczone na kolejny uzgodniony obszar audytu.'],
        [
            'id' => 'context', 'number' => '4', 'title' => 'Kontekst organizacji',
            'description' => 'Kontekst organizacji oraz wymagania dotyczące zakresu i funkcjonowania systemu zarządzania energią.',
            'items' => [
                ['id' => '4-1', 'number' => '4.1 – zmiana 2024', 'title' => 'Wpływ zmian klimatu', 'description' => 'Czy organizacja oceniła, czy zmiany klimatu są istotne dla jej systemu energetycznego, np. temperatury zewnętrzne, zapotrzebowanie na chłód, ryzyko przerw w dostawach.'],
                ['id' => '4-2', 'number' => '4.2', 'title' => 'Potrzeby stron zainteresowanych', 'description' => 'Wymagania właścicieli, klientów, urzędów, operatorów sieci, pracowników, grupy kapitałowej, banków i ubezpieczycieli.'],
                ['id' => '4-3', 'number' => '4.3', 'title' => 'Zakres systemu zarządzania energią', 'description' => 'Jednoznaczne granice: zakład, budynki, instalacje, media, procesy i lokalizacje objęte ISO 50001. Nie można wygodnie wyłączyć istotnego zużycia pozostającego pod kontrolą organizacji.'],
                ['id' => '4-4', 'number' => '4.4', 'title' => 'System zarządzania energią – EnMS', 'description' => 'Czy system został wdrożony, działa i prowadzi do poprawy wyniku energetycznego.'],
            ],
        ],
    ],
];
