# Inspektor UDT — moduł przeglądów butli

Moduł `cylinders` jest domyślnie wyłączony, również przy braku ustawień firmy lub `enabled_modules = null`. Migracja nie włącza go w żadnym wdrożeniu ani nie zmienia istniejących ról.

## Uruchomienie

1. Wykonaj standardowe migracje wdrożenia.
2. Ustawienia → firma → moduły aplikacji: zaznacz „Inspektor UDT”.
3. Administrator z pełnym dostępem i superadmin mają dostęp do włączonego modułu. Dla pozostałych utwórz rolę „Inspektor” w ustawieniach ról i nadaj `cylinders.view` oraz `cylinders.manage`. Samo `view` daje tylko odczyt. Uprawnienia dotyczą wszystkich firm w danym wdrożeniu — nie tylko firm przypisanych inspektorowi. Nie nadajemy ich automatycznie audytorom.
4. Klientów dodawaj w CRM. Włącz „Strefę klienta”, aby klient mógł oglądać butle firm, do których jest aktywnie przypisany.

## Dostępny zakres

- Rejestr butli z wyszukiwaniem i stronicowaniem; dane urządzenia, właściciel, edycja i archiwizacja bez usuwania historii.
- Historia ręcznie wprowadzonych przeglądów: autor ustalany z zalogowanego konta, data badania i zapisu, zakres, wyniki, następny termin.
- Brak nadpisywania/usuwania wpisów przeglądów. Sprostowanie jako kolejny wpis odnoszący się do numeru poprzedniego.
- Ostatni przegląd wybierany po dacie badania, nie dacie wprowadzenia. Terminy widoczne w rejestrze; kolory opisane poniżej.
- Klient i podgląd strefy klienta: tylko odczyt danych wybranej/przypisanej firmy.
- Zmiany trafiają do istniejącej historii zmian. Wyłączenie blokuje wszystkie trasy, nie usuwa danych.

## Filmy i statusy

Inspektor z `cylinders.manage` może dodawać filmy MP4/WebM, po jednym do 100 MB, z odtwarzaniem na karcie butli. Pliki są prywatne, dostępne wyłącznie uprawnionym pracownikom i klientom właściciela butli. Każdy film obciąża limit dokumentów użytkownika, również gdy moduł zostanie wyłączony. Dla zgodności przeglądarek zalecany MP4 H.264/AAC; system nie transkoduje filmów. Nazwa modułu nie oznacza integracji ani afiliacji z urzędem.

Na Railway konieczny jest trwały wolumen obejmujący `storage/app/private` (patrz production-storage-checklist.md). Nie zmieniaj montowania istniejącego wolumenu bez migracji plików. Konfiguracja PHP w deploy/php podnosi limit pojedynczego uploadu do 100 MB; limity konkretnych formularzy i kwoty użytkowników nadal obowiązują.

Kolory według ostatniego przeglądu: czerwony dla `defects_found` lub `further_review` (pierwszeństwo), pomarańczowy dla daty przed dzisiaj, żółty od dziś do miesiąca kalendarzowego włącznie (`addMonthNoOverflow`), zielony tylko przy `no_findings` i późniejszym terminie. Brak oceny/terminu oraz archiwum są neutralne. To oznaczenie rejestru, nie formalne dopuszczenie do eksploatacji.

## Kolejne etapy — jeszcze nie zaimplementowane

Zdjęcia, ustrukturyzowane pomiary, formularze właściwe dla rodzaju butli, harmonogram w kalendarzu i przypomnienia, protokoły PDF, zatwierdzanie, analiza AI i walidacja jej skuteczności. Obecne wpisy nie są urzędowymi protokołami ani automatycznym dopuszczeniem do eksploatacji. Nie ma połączenia z UDT, zewnętrznym modelem AI ani wysyłania danych poza aplikację.

Wspólny kod nie oznacza wspólnych danych: każde wdrożenie powinno mieć osobną bazę, magazyn plików, klucze i dostęp do kopii zapasowych.
