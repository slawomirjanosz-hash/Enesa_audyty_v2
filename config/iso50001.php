<?php

return [
    'chapters' => [
        ['id' => 'intro', 'number' => '1', 'title' => 'Wstęp o ISO', 'description' => 'ISO 50001:2018 określa ramy tworzenia, wdrażania, utrzymywania i ciągłego doskonalenia systemu zarządzania energią (EnMS). Pomaga organizacji zarządzać zużyciem energii na podstawie danych, wyznaczać cele i wykazywać poprawę wyniku energetycznego. Norma wykorzystuje cykl PDCA i może być zintegrowana z ISO 9001 oraz ISO 14001.', 'source_url' => 'https://www.iso.org/standard/69426.html', 'source_label' => 'ISO 50001:2018 – informacje o normie'],
        ['id' => 'training', 'number' => '2', 'title' => 'Filmy szkoleniowe', 'description' => 'Materiały szkoleniowe wspierające wdrożenie i prowadzenie EnMS.'],
        ['id' => 'reserve', 'number' => '3', 'title' => 'Rezerwa', 'description' => 'Miejsce przeznaczone na kolejny uzgodniony obszar audytu.'],
        [
            'id' => 'context', 'number' => '4', 'title' => 'Kontekst organizacji',
            'description' => 'Organizacja określa otoczenie, strony zainteresowane i granice systemu, aby EnMS odpowiadał jej rzeczywistym uwarunkowaniom oraz obejmował istotne wykorzystanie energii.',
            'items' => [
                ['id' => '4-1', 'number' => '4.1', 'title' => 'Zrozumienie organizacji i jej kontekstu', 'description' => 'Należy rozpoznać wewnętrzne i zewnętrzne czynniki wpływające na wynik energetyczny i zdolność EnMS do osiągania zamierzonych rezultatów. Analiza powinna obejmować m.in. profil działalności, technologie, infrastrukturę, ceny i dostępność energii, wymagania rynku, warunki pogodowe oraz ocenę istotności zmian klimatu zgodnie ze zmianą z 2024 r.'],
                ['id' => '4-2', 'number' => '4.2', 'title' => 'Potrzeby i oczekiwania stron zainteresowanych', 'description' => 'Trzeba wskazać strony mające wpływ na EnMS, np. właścicieli, pracowników, klientów, urzędy, operatorów sieci, banki i ubezpieczycieli, oraz określić ich wymagania. Należy ustalić, które wymagania prawne i inne organizacja będzie spełniać, w tym wymagania związane ze zmianami klimatu zgłaszane przez zainteresowane strony.'],
                ['id' => '4-3', 'number' => '4.3', 'title' => 'Zakres systemu zarządzania energią', 'description' => 'Zakres powinien jednoznacznie wskazywać lokalizacje, granice organizacyjne i fizyczne, budynki, instalacje, procesy oraz rodzaje energii objęte EnMS. W jego granicach organizacja musi mieć możliwość nadzorowania wykorzystania, zużycia i efektywności energetycznej oraz nie powinna wyłączać znaczących obszarów pozostających pod jej kontrolą.'],
                ['id' => '4-4', 'number' => '4.4', 'title' => 'System zarządzania energią', 'description' => 'Organizacja powinna ustanowić, wdrożyć, utrzymywać i doskonalić EnMS wraz z potrzebnymi procesami i ich wzajemnymi powiązaniami. System ma prowadzić do mierzalnej, ciągłej poprawy wyniku energetycznego.'],
            ],
        ],
        [
            'id' => 'leadership', 'number' => '5', 'title' => 'Przywództwo',
            'description' => 'Najwyższe kierownictwo bierze odpowiedzialność za skuteczność EnMS, zapewnia jego powiązanie ze strategią organizacji i tworzy warunki do poprawy wyniku energetycznego.',
            'items' => [
                ['id' => '5-1', 'number' => '5.1', 'title' => 'Przywództwo i zaangażowanie', 'description' => 'Kierownictwo powinno wykazać aktywne zaangażowanie: ustalać kierunek, zapewniać zasoby, wspierać osoby odpowiedzialne, włączać wymagania energetyczne do procesów biznesowych oraz dopilnować, aby EnMS osiągał zamierzone wyniki i poprawę wyniku energetycznego.'],
                ['id' => '5-2', 'number' => '5.2', 'title' => 'Polityka energetyczna', 'description' => 'Polityka powinna pasować do celu i kontekstu organizacji, stanowić ramy dla celów energetycznych oraz zawierać zobowiązania do zapewnienia informacji i zasobów, spełniania wymagań, uwzględniania wyniku energetycznego przy projektowaniu i zakupach oraz ciągłego doskonalenia EnMS i wyniku energetycznego. Powinna być udokumentowana, komunikowana i dostępna właściwym stronom.'],
                ['id' => '5-3', 'number' => '5.3', 'title' => 'Role, odpowiedzialności i uprawnienia', 'description' => 'Należy jasno przydzielić odpowiedzialności za utrzymanie EnMS, zgodność z normą, realizację planów, raportowanie wyników i poprawę wyniku energetycznego. Osoby odpowiedzialne muszą mieć wystarczające uprawnienia i bezpośredni dostęp do kierownictwa.'],
            ],
        ],
        [
            'id' => 'planning', 'number' => '6', 'title' => 'Planowanie',
            'description' => 'Planowanie przekłada kontekst organizacji i dane energetyczne na ryzyka, szanse, przegląd energetyczny, wskaźniki, linie bazowe, cele oraz konkretne plany działania.',
            'items' => [
                ['id' => '6-1', 'number' => '6.1', 'title' => 'Ryzyka i szanse', 'description' => 'Organizacja powinna ustalić ryzyka i szanse mogące wpływać na wyniki EnMS, ciągłość energetyczną, zgodność i poprawę wyniku energetycznego. Dla istotnych zagadnień należy zaplanować działania, włączyć je do procesów i oceniać ich skuteczność.'],
                ['id' => '6-2', 'number' => '6.2', 'title' => 'Cele, zadania energetyczne i plany działania', 'description' => 'Cele i zadania powinny być zgodne z polityką, mierzalne tam, gdzie to możliwe, uwzględniać znaczące wykorzystanie energii, wymagania oraz możliwości poprawy. Plan działania powinien wskazywać odpowiedzialnych, terminy, zasoby, sposób realizacji i metodę oceny rezultatów, w tym poprawy wyniku energetycznego.'],
                ['id' => '6-3', 'number' => '6.3', 'title' => 'Przegląd energetyczny', 'description' => 'Należy analizować wykorzystanie i zużycie energii na podstawie pomiarów i danych, wskazać znaczące wykorzystanie energii (SEU), zmienne istotne, osoby wpływające na wynik, bieżącą efektywność oraz przyszłe zużycie. Przegląd powinien identyfikować i priorytetyzować możliwości poprawy oraz być aktualizowany po istotnych zmianach.'],
                ['id' => '6-4', 'number' => '6.4', 'title' => 'Wskaźniki wyniku energetycznego', 'description' => 'Organizacja dobiera EnPI właściwe dla swoich procesów i znaczącego wykorzystania energii. Metoda ich wyznaczania i aktualizacji powinna pozwalać wiarygodnie mierzyć poprawę, uwzględniać odpowiednie zmienne i umożliwiać porównania w czasie.'],
                ['id' => '6-5', 'number' => '6.5', 'title' => 'Energetyczna linia bazowa', 'description' => 'EnB powinna wynikać z odpowiedniego okresu danych i stanowić punkt odniesienia dla EnPI. Trzeba określić zasady normalizacji i korekty linii bazowej, gdy zmienne istotne znacząco się zmienią, wystąpią duże zmiany obiektów lub procesów albo linia przestanie odzwierciedlać wynik energetyczny.'],
                ['id' => '6-6', 'number' => '6.6', 'title' => 'Planowanie zbierania danych energetycznych', 'description' => 'Plan pomiarów powinien określać, jakie dane są potrzebne, skąd pochodzą, z jaką częstotliwością są zbierane, kto za nie odpowiada oraz jak zapewnia się ich dokładność i powtarzalność. Powinien obejmować co najmniej zmienne istotne, SEU, zużycie, czynniki operacyjne i dane potrzebne do oceny planów działania.'],
            ],
        ],
        [
            'id' => 'support', 'number' => '7', 'title' => 'Wsparcie',
            'description' => 'Organizacja zapewnia zasoby, kompetencje, świadomość, komunikację i nadzorowaną dokumentację potrzebną do skutecznego funkcjonowania EnMS.',
            'items' => [
                ['id' => '7-1', 'number' => '7.1', 'title' => 'Zasoby', 'description' => 'Należy zapewnić odpowiednie zasoby ludzkie, finansowe, techniczne, pomiarowe i organizacyjne do wdrożenia, utrzymania i doskonalenia EnMS oraz poprawy wyniku energetycznego.'],
                ['id' => '7-2', 'number' => '7.2', 'title' => 'Kompetencje', 'description' => 'Osoby wpływające na wynik energetyczny i EnMS powinny posiadać kompetencje wynikające z wykształcenia, szkolenia lub doświadczenia. Organizacja musi oceniać potrzeby, uzupełniać braki, sprawdzać skuteczność działań i przechowywać dowody kompetencji.'],
                ['id' => '7-3', 'number' => '7.3', 'title' => 'Świadomość', 'description' => 'Pracownicy powinni znać politykę energetyczną, swój wpływ na zużycie i efektywność, korzyści z poprawy oraz konsekwencje niestosowania wymagań. Powinni rozumieć, jak ich działania wspierają cele i wynik energetyczny.'],
                ['id' => '7-4', 'number' => '7.4', 'title' => 'Komunikacja', 'description' => 'Trzeba określić, co, kiedy, komu, jak i przez kogo jest komunikowane wewnątrz i na zewnątrz organizacji. Należy również zapewnić pracownikom możliwość zgłaszania uwag i propozycji dotyczących EnMS i poprawy wyniku energetycznego.'],
                ['id' => '7-5', 'number' => '7.5', 'title' => 'Udokumentowane informacje', 'description' => 'Dokumentacja powinna obejmować informacje wymagane przez normę i uznane przez organizację za potrzebne. Dokumenty muszą być właściwie identyfikowane, zatwierdzane, dostępne, chronione, wersjonowane, przechowywane przez określony czas i usuwane w kontrolowany sposób; dotyczy to także dokumentów zewnętrznych.'],
            ],
        ],
        [
            'id' => 'operation', 'number' => '8', 'title' => 'Działania operacyjne',
            'description' => 'Wymagania energetyczne są przekładane na codzienne sterowanie procesami, projektowanie oraz zakupy mające istotny wpływ na wynik energetyczny.',
            'items' => [
                ['id' => '8-1', 'number' => '8.1', 'title' => 'Planowanie i nadzór operacyjny', 'description' => 'Dla SEU i procesów związanych z planami działania należy ustalić kryteria pracy i utrzymania, komunikować je właściwym osobom oraz nadzorować ich stosowanie. Organizacja powinna zarządzać planowanymi zmianami i ograniczać skutki zmian niezamierzonych, także w procesach zlecanych na zewnątrz.'],
                ['id' => '8-2', 'number' => '8.2', 'title' => 'Projektowanie', 'description' => 'Przy projektowaniu nowych, zmienianych lub remontowanych obiektów, urządzeń, systemów i procesów mających istotny wpływ na wynik energetyczny należy rozważyć możliwości poprawy w całym przewidywanym okresie użytkowania i zachować wyniki tej oceny.'],
                ['id' => '8-3', 'number' => '8.3', 'title' => 'Zakupy', 'description' => 'Przy zakupie produktów, urządzeń, energii i usług mających wpływ na SEU trzeba ustanowić kryteria oceny wyniku energetycznego oraz informować dostawców, że jest on elementem oceny. Przy zakupach należy uwzględniać oczekiwane wykorzystanie, zużycie i efektywność w planowanym okresie użytkowania.'],
            ],
        ],
        [
            'id' => 'performance', 'number' => '9', 'title' => 'Ocena efektów działania',
            'description' => 'Organizacja regularnie mierzy wynik energetyczny i skuteczność EnMS, ocenia zgodność, przeprowadza audyty wewnętrzne i poddaje system przeglądowi kierownictwa.',
            'items' => [
                ['id' => '9-1', 'number' => '9.1', 'title' => 'Monitorowanie, pomiary, analiza i ocena', 'description' => 'Należy określić, co i jak będzie monitorowane, metody zapewniające wiarygodne wyniki, częstotliwość pomiarów oraz terminy analizy. Ocenie podlegają m.in. realizacja planów, EnPI, działanie SEU, porównanie zużycia rzeczywistego z oczekiwanym i skuteczność EnMS. Istotne odchylenia trzeba badać i dokumentować.'],
                ['id' => '9-1-2', 'number' => '9.1.2', 'title' => 'Ocena zgodności', 'description' => 'W zaplanowanych odstępach organizacja powinna oceniać spełnienie wymagań prawnych i innych odnoszących się do efektywności, wykorzystania i zużycia energii, podejmować działania w razie niezgodności i zachowywać udokumentowane wyniki oceny.'],
                ['id' => '9-2', 'number' => '9.2', 'title' => 'Audyt wewnętrzny', 'description' => 'Program audytów powinien uwzględniać znaczenie procesów, zmiany i wyniki wcześniejszych audytów. Audytorzy muszą być obiektywni, a zakres, kryteria, metody, odpowiedzialności i raportowanie powinny być zaplanowane. Wyniki przekazuje się kierownictwu, a działania korygujące są realizowane bez zbędnej zwłoki.'],
                ['id' => '9-3', 'number' => '9.3', 'title' => 'Przegląd zarządzania', 'description' => 'Kierownictwo okresowo ocenia przydatność, adekwatność i skuteczność EnMS. Przegląd powinien uwzględniać wcześniejsze ustalenia, zmiany kontekstu, ryzyka i szanse, cele, EnPI, wyniki audytów, zgodność, niezgodności, zasoby i możliwości poprawy. Rezultatem są decyzje dotyczące zmian systemu, zasobów, działań oraz poprawy wyniku energetycznego.'],
            ],
        ],
        [
            'id' => 'improvement', 'number' => '10', 'title' => 'Doskonalenie',
            'description' => 'Organizacja reaguje na niezgodności, usuwa ich przyczyny i stale podnosi skuteczność EnMS oraz wynik energetyczny.',
            'items' => [
                ['id' => '10-1', 'number' => '10.1', 'title' => 'Niezgodności i działania korygujące', 'description' => 'Po wykryciu niezgodności należy ją opanować, usunąć skutki, ustalić przyczyny i ocenić, czy podobny problem może wystąpić gdzie indziej. Działania powinny być proporcjonalne do skutków, następnie ocenione pod kątem skuteczności, a dokumentacja powinna obejmować charakter niezgodności, podjęte działania i rezultaty.'],
                ['id' => '10-2', 'number' => '10.2', 'title' => 'Ciągłe doskonalenie', 'description' => 'Organizacja powinna stale doskonalić przydatność, adekwatność i skuteczność EnMS oraz wykazywać ciągłą poprawę wyniku energetycznego za pomocą EnPI, linii bazowych, wyników planów i innych wiarygodnych danych.'],
            ],
        ],
    ],
];
