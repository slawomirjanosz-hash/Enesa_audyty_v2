# Sortowanie tabel

Zasada projektu: wszystkie kolumny danych w interaktywnych tabelach mają sortowanie rosnące i malejące. Kolumny zaznaczania i akcji nie podlegają sortowaniu. Zasada jest zapisana również w AGENTS.md.

## Przegląd widoków

Przejrzano 42 pliki Blade zawierające tabele: CRM, dashboard, karty firm i dostawców, oferty i ich edytory, projekty, dokumenty, HR, użytkownicy i uprawnienia, archiwum, audyty i ISO, paszporty energetyczne, cennik, szablony ofert, wersje audytów oraz Inspektor UDT.

- Layouty aplikacji, klienta i podglądu strefy klienta oraz publiczne udostępnienia ładują wspólny moduł `public/js/table-sort.js`.
- Moduł automatycznie obejmuje nowe tabele, również tworzone później przez JavaScript (np. harmonogramy). Istniejące dedykowane przyciski sortowania zachowują swoją obsługę.
- Daty polskie i ISO, liczby, kwoty oraz rozmiary plików mają porównanie wartości. Wersje i identyfikatory korzystają z porządku naturalnego. Puste wartości w sortowaniu lokalnym pozostają na końcu.
- Istniejące wiersze są przenoszone, nie kopiowane: zachowane zostają wartości formularzy, zaznaczenia, obsługa zdarzeń i filtrowanie.
- Nagłówki sekcji i sumy z połączonymi komórkami wyznaczają granice sortowania. Nie zmienia się kolejność sekcji.
- Oferty, dokumenty, zakończone projekty, lista butli i historia przeglądów używają `TableSort` przed paginacją. Dostawcy mają własne sortowanie serwerowe. Zakresy dostępu, wyszukiwanie i filtry pozostają aktywne.

## Świadome wyjątki

Nie dodajemy interakcji do tabel w PDF-ach i mailach, tabel treści tworzonych przez użytkownika w edytorze tekstu, nagłówków wielopoziomowych oraz układów opis–wartość bez nagłówka kolumn. Nie są to interaktywne listy rekordów. Nagłówki, sumy i rozwijane szczegóły z colspan/rowspan pozostają w miejscu.

## Nowe tabele

Standardowa tabela z pojedynczym `thead` działa automatycznie. Nie należy samodzielnie dublować sortera.

- `data-sort-value` na komórce: jawna wartość dla złożonej zawartości.
- `data-sort-type="text"` na nagłówku: porządek naturalny, np. wersja 2.9 przed 2.10.
- `data-sort-fixed` na wierszu: nieruchoma granica grupy lub podsumowanie.
- `data-sortable="false"`: wyłącznie uzasadniony wyjątek, nie sposób pomijania sortowania list danych.
- `data-server-sort="name,date,..."`: klucze kolumn do serwera; kontroler musi mieć ich zamkniętą listę. Opcjonalnie `data-sort-prefix` i `data-page-parameter` dla wielu paginacji na ekranie.

Testy: `npm run test:tables` oraz `php artisan test --filter=TableSortingTest`. Testy JavaScript sprawdzają typy wartości, kierunki, stabilność kolejności, granice grup i zachowanie ukrytych wierszy. Testy Laravel sprawdzają kolumny serwerowe, paginację i ograniczenie dostępnych parametrów.
