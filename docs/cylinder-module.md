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
- Edycja przeglądów przez użytkownika z `cylinders.manage`; autor pierwotnego wpisu pozostaje bez zmian. Licznik wersji zapobiega nadpisaniu równoległej edycji. Historia zmian przechowuje poprzednie i nowe wartości, w tym pełną treść uwag (do 20 tys. znaków), oraz osobę edytującą. Brak usuwania wpisów.
- Ostatni przegląd wybierany po dacie badania, nie dacie wprowadzenia. Terminy widoczne w rejestrze; kolory opisane poniżej.
- Klient i podgląd strefy klienta: tylko odczyt danych wybranej/przypisanej firmy.
- Zmiany trafiają do istniejącej historii zmian. Wyłączenie blokuje wszystkie trasy, nie usuwa danych.

## Filmy i statusy

Przycisk „+ Film” przy wpisie wybiera ten wpis w formularzu dodawania filmu. Powiązanie jest sprawdzane po stronie serwera; film nie może wskazywać przeglądu innej butli. Filmy ogólne dodane wcześniej pozostają bez przypisania, bez zgadywania którego wpisu dotyczą. Przycisk „Filmy” filtruje nagrania danego wpisu.

Zdjęcie butli można dodać lub zastąpić na karcie (JPG/PNG/WebP do 8 MB i 12 MP). Aplikacja normalizuje je do JPEG do 1600 px, usuwa metadane i tworzy osobną miniaturkę 96 px, wyświetlaną w rozmiarze 36 px. Oryginał uploadu nie jest przechowywany. Obie wersje są prywatne, wliczane do kwoty autora, a po poprawnym zastąpieniu poprzednie pliki są usuwane. Kliknięcie miniaturki otwiera podgląd; działa również w strefie klienta. Zdjęcia są identyfikacyjne, nie zastępują dokumentacji wad w oryginalnej rozdzielczości.

Na liście typ i producent oraz status są bez zawijania; przy małym ekranie tabela przewija się poziomo. Następny termin ma niezależny od statusu kolor: pomarańczowy w ciągu miesiąca, czerwony po terminie. Dane na karcie są skompresowane, aby historia wpisów była widoczna wyżej.

Formularz „Dodaj film” przyjmuje plik lub link HTTPS (wzajemnie wykluczające się źródła). YouTube (watch, youtu.be, shorts, live, embed) i Dysk Google (file/d, open?id, uc?id; także resourcekey) mają podgląd osadzony ładowany dopiero po kliknięciu. Pozostałe serwisy, np. Vimeo, OneDrive czy Dropbox, otwierają się w nowej karcie. Dla każdego linku jest awaryjny przycisk otwarcia u źródła. Serwer nie pobiera zewnętrznych adresów; nie przyjmujemy HTML iframe. Linki mają rozmiar 0 i nie obciążają kwoty. URL nie trafia do historii zmian, ponieważ może zawierać token dostępu.

Dostęp do filmu zewnętrznego zależy od dostawcy, logowania odbiorcy i ustawień właściciela. Uprawnienia aplikacji chronią kartę butli, ale nie zastępują ustawień udostępniania filmu. Nagrania poufne najlepiej wgrywać prywatnie do systemu; nie wymuszać ustawienia publicznego tylko po to, by działał iframe.

Inspektor z `cylinders.manage` może dodawać filmy MP4/WebM, po jednym do 100 MB, z odtwarzaniem na karcie butli. Pliki są prywatne, dostępne wyłącznie uprawnionym pracownikom i klientom właściciela butli. Każdy film obciąża limit dokumentów użytkownika, również gdy moduł zostanie wyłączony. Dla zgodności przeglądarek zalecany MP4 H.264/AAC; system nie transkoduje filmów. Nazwa modułu nie oznacza integracji ani afiliacji z urzędem.

Na Railway konieczny jest trwały wolumen obejmujący `storage/app/private` (patrz production-storage-checklist.md). Nie zmieniaj montowania istniejącego wolumenu bez migracji plików. Konfiguracja PHP w deploy/php podnosi limit pojedynczego uploadu do 100 MB; limity konkretnych formularzy i kwoty użytkowników nadal obowiązują.

Kolory według ostatniego przeglądu: czerwony dla `defects_found` lub `further_review` (pierwszeństwo), pomarańczowy dla daty przed dzisiaj, żółty od dziś do miesiąca kalendarzowego włącznie (`addMonthNoOverflow`), zielony tylko przy `no_findings` i późniejszym terminie. Brak oceny/terminu oraz archiwum są neutralne. To oznaczenie rejestru, nie formalne dopuszczenie do eksploatacji.

## Kolejne etapy — jeszcze nie zaimplementowane

Pełna dokumentacja fotograficzna wad, ustrukturyzowane pomiary, formularze właściwe dla rodzaju butli, harmonogram w kalendarzu i przypomnienia, protokoły PDF, zatwierdzanie, analiza AI i walidacja jej skuteczności. Obecne wpisy nie są urzędowymi protokołami ani automatycznym dopuszczeniem do eksploatacji. Nie ma połączenia z UDT, zewnętrznym modelem AI ani wysyłania danych poza aplikację.

Wspólny kod nie oznacza wspólnych danych: każde wdrożenie powinno mieć osobną bazę, magazyn plików, klucze i dostęp do kopii zapasowych.
