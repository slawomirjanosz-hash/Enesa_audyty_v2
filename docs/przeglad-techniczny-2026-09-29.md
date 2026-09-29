# Przegląd techniczny przy dodawaniu magazynu — 29.09.2026

## Wniosek

Aplikacja nadaje się do dalszego rozwoju. Dłuższy czas realizacji zmian ma dwa źródła: obowiązkowe testy i wdrożenia wielu instancji oraz narastający koszt utrzymania dużych plików. Nie ma podstaw, aby uznać całość za „zbyt skomplikowaną” ani przepisywać ją od zera.

Testy uruchamiane są lokalnie i w GitHub Actions. Railway buduje i uruchamia aplikacje; potem sprawdzany jest wynik wdrożenia. Wcześniejsze małe zmiany delegacji wymagały oczekiwania na ENESA, jej proces pomocniczy, Prinz, INTROL i TOEN, mimo że sam zakres kodu był niewielki.

## Konkretne obserwacje

- `ProjectController` ma około 1174 niepustych wierszy, `OfferController` około 1126. Obsługują liczne obiegi, więc niewielka zmiana wymaga sprawdzenia uprawnień, finansów, dokumentów i istniejących widoków.
- Widok projektu ma około 158 kB, karty firmy 138 kB, CRM 119 kB. Mieszają HTML, styl i skrypty. To utrudnia izolowanie błędów i powoduje większe odpowiedzi nawet dla prostych zmian zakładki.
- `ProjectController::show()` ładuje równocześnie zadania, finanse, materiały i dokumenty niezależnie od wybranej zakładki. Przy dużych projektach jest to kandydat do pomiarów i rozdzielenia na ładowanie sekcji na żądanie. Nie zmieniano tego obiegu w zadaniu magazynu.
- `SupplierController::index()` oprócz liczników pobiera relacje materiałów i finansów z projektami. Lista dostawców może używać agregatów zamiast ładowania całych relacji. Wymaga zachowania istniejącego zakresu dostępu i sortowania.
- Dashboard pobiera listy klientów z relacjami. Przed optymalizacją należy zmierzyć wielkość odpowiedzi i zapytania na reprezentatywnych danych, nie usuwać relacji w ciemno.
- `nixpacks.toml` instaluje LibreOffice, Poppler i fonty, buduje frontend oraz buforuje konfigurację, trasy i widoki. Konwerter prezentacji zwiększa rozmiar budowanego obrazu. Sama mała poprawka PHP nie eliminuje tych etapów wdrażania.
- Konfiguracja startowa używa `php artisan serve` z domyślnie dwoma procesami. Przy równoczesnych długich operacjach PDF/prezentacji może to ograniczać przepustowość. Docelowo warto osobno zaplanować serwer aplikacyjny i zadania konwersji w kolejce. To wymaga testów infrastruktury, nie zostało zmienione przy magazynie.
- Historia zmian, uprawnienia i rozdzielenie firm na wdrożenia są elementami do zachowania. Magazyn korzysta z istniejących mechanizmów zamiast tworzyć drugie CRM lub drugi system użytkowników.

## Co zastosowano w magazynie

- Osobne trasy, widoki, skrypt i styl; logika stanów w serwisie transakcyjnym.
- Katalog i historia po 30 pozycji na stronie, sortowanie przed paginacją, wyszukiwanie pozycji do dokumentu do 25 wyników, eksport porcjami po 500.
- Trwałe ruchy magazynowe, dane historyczne niezależne od sesji, blokady i ochrona powtórnego zapisu.
- Nowy moduł wyłączony domyślnie, bez zmieniania konfiguracji klientów i danych innych modułów.

## Zakres weryfikacji i ograniczenia

Przegląd objął kod najważniejszych punktów integracji, konfigurację budowania, pełny lokalny zestaw testów, analizę statyczną i test przeglądarkowy magazynu na komputerze i w wąskim widoku. Nie jest to pełny audyt bezpieczeństwa ani test obciążeniowy produkcji. Lokalny zestaw opiera się na SQLite; blokady transakcyjne są projektowane dla produkcyjnego MySQL, ale nie wykonano testu obciążeniowego z wieloma równoległymi operatorami na produkcji.

Nie zmieniano plików projektu ProximaLumine, konfiguracji serwerów ani danych klientów. Nie kopiowano danych magazynowych źródłowego projektu. Nie usuwano nieśledzonych katalogów roboczych `tmp/` i `outputs/`; nie są częścią tego commitu.

## Zalecana kolejność kolejnych prac

1. Zmierzyć zapytania i czas odpowiedzi karty projektu na dużym projekcie; wydzielić ładowanie zakładek.
2. Rozbić największe widoki i kontrolery etapami, bez zmiany zachowania, z testami regresji.
3. Przenieść ciężkie konwersje do kolejki i osobno przetestować konfigurację serwera HTTP.
4. Uporządkować wydania: łączyć kilka małych, niezależnych poprawek w jedno uzgodnione wdrożenie, jeśli użytkownik zmieni obecną zasadę wdrażania po każdej zmianie. Do tego czasu zachować bieżącą zasadę.
