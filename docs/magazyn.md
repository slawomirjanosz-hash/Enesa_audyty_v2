# Magazyn — obsługa i założenia

## Uruchomienie

Moduł jest domyślnie wyłączony, również gdy `enabled_modules` ma wartość null. Administrator włącza **Magazyn** w ustawieniach firmy/modułów. Każde wdrożenie ma własną bazę i własną konfigurację. Nie importujemy danych z ProximaLumine.

Administratorzy otrzymują uprawnienia magazynowe podczas migracji. Inne role należy skonfigurować w ustawieniach ról: `warehouse.view`, `warehouse.manage`, `warehouse.receive`, `warehouse.issue`, `warehouse.adjust`. Dostęp `warehouse.view` obejmuje cały magazyn danego wdrożenia, ceny i historię — nie jest to strefa klienta. Pozostałe uprawnienia wymagają również dostępu do podglądu. Strefa klienta nie ma tras magazynowych.

## Praca

1. Dodaj pozycję: niepowtarzalny kod, nazwa, jednostka, kategoria, lokalizacja, minimum i opis. Stan początkowy to zero.
2. Przyjmij towar dokumentem PZ. Tabela wyboru zawiera wszystkie aktywne towary i filtruje się podczas pisania kodu lub nazwy (również bez polskich znaków). Ustaw ilość, cenę i dostawcę przy towarze, następnie kliknij „Dodaj”. Wybrane pozycje można jeszcze poprawić lub usunąć przed zapisaniem całego dokumentu. Jeden dokument może mieć do 50 różnych pozycji; każda ma własnego dostawcę z dostępnego CRM. Numer faktury jest wspólny dla dokumentu.
3. Wydaj towar dokumentem WZ. Można przypisać dostępny projekt, a cel operacji jest obowiązkowy. To nie tworzy kosztu w finansach projektu ani zapotrzebowania — zapobiega podwójnemu księgowaniu.
4. Inwentaryzacja: wpisz faktycznie policzoną ilość, nie różnicę, i przyczynę. Dokument KOR zapisuje różnicę. Zmiana stanu przez inną osobę od otwarcia formularza blokuje korektę do ponownego sprawdzenia.
5. Katalog i dokumenty mają sortowanie wszystkich kolumn danych, filtry i stronicowanie po stronie serwera. Eksport CSV dla Excela obejmuje wszystkie pozycje spełniające filtry, nie tylko bieżącą stronę. Wyszukiwanie kodu obsługuje skaner działający jak klawiatura.
6. Archiwizować można tylko pozycję o stanie zero. Można ją przywrócić. Pozycje i ruchy nie są trwale usuwane przez interfejs.

## Bezpieczeństwo i historia

- Wszystkie pozycje dokumentu zapisują się w jednej transakcji. Brak towaru lub błąd jednej linii wycofuje całość, również historię zmian tej próby.
- Blokady wierszy `FOR UPDATE` oraz stała kolejność blokowania towarów chronią przed równoczesnym wydaniem tego samego stanu. Identyfikator formularza chroni przed podwójnym zapisem przez tego samego operatora.
- Ilości mają dokładność 0,001 jednostki i limit 1 000 000. Obliczenia zmian stanów wykorzystują całkowite tysięczne jednostki. Ceny są w PLN z dokładnością do grosza.
- Cena ewidencyjna jest średnią ważoną przyjęć, zaokrąglaną do grosza. To wycena operacyjna, nie pełna księgowość ani FIFO/partie.
- Formularz przyjęcia podpowiada cenę z ostatniego zapisanego PZ, a gdy go brak — cenę ewidencyjną. Podpowiada ostatniego dostawcę tylko wtedy, gdy użytkownik nadal ma do niego dostęp. Cena i dostawca mogą być zmienione przed dodaniem oraz na liście pozycji dokumentu.
- Tabela wyboru ładuje cały aktywny katalog i filtruje/sortuje go lokalnie bez dodatkowych zapytań podczas pisania. Pozostałe listy katalogu i historii nadal mają stronicowanie serwerowe.
- Ruchy stosowane są w kolejności zapisu. Wcześniejsza data dokumentu nie przelicza wstecz późniejszych stanów ani wycen.
- Dokumenty przechowują migawkę kodu, nazwy, jednostki, ceny, operatora, dostawcy i projektu. Późniejsza edycja katalogu nie przepisuje historii.
- Do korekty błędu służy nowy dokument z opisem i odwołaniem do dokumentu źródłowego. Nie ma edycji i usuwania zatwierdzonych dokumentów.
- Wybór projektu i dostawcy respektuje istniejące uprawnienia oraz aktywność modułów. Uprawnienie magazynowe nie daje dostępu do cudzych projektów w ich module.
- Zmiany są także widoczne w ogólnej liście zmian. Eksport zabezpiecza tekst przed interpretacją jako formuły arkusza.

## Zakres tej wersji

Jeden logiczny magazyn na wdrożenie, z lokalizacjami regał/półka. Nie przenoszono automatycznie zamówień, importu Excel, etykiet QR, skanowania aparatem, wielu magazynów, rezerwacji, partii/terminów ważności, księgowania kosztów ani generowania wydruków PZ/WZ z ProximaLumine. Są to osobne rozszerzenia, wymagające ustalenia obiegu.

Implementacja: `WarehouseController`, `WarehouseService`, trzy modele i tabele `warehouse_*`, osobny plik tras i widoków. Migracja jest addytywna; nie zmienia stanów ani danych istniejących modułów.
