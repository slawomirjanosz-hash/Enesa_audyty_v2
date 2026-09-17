# ISO 50001 — biblioteka i obieg 4.1–4.2

## Uruchomienie

Uruchomić standardowe migracje (`php artisan migrate --force`) podczas wdrożenia.
Nowy ekran jest podlinkowany w punktach 4.1 i 4.2 audytu, także w strefie klienta.
Rodzaje audytów pokazują oddzielny, nieedytujący danych klienta podgląd biblioteki.
Nie usuwamy ani nie migrujemy automatycznie wcześniejszych odpowiedzi z generatora demonstracyjnego.

## Dostępne

- Niezmieniony JSON źródłowy 1.3: 44 pytania, 69 czynników, 28 stron, 21 pozycji mapowania.
- Parser bez eval: porównania, IN, AND przed OR; brak danych i „nie wiem” nie spełniają predykatów, również !=.
- Jawna normalizacja wartości opcji; roczna suma nośników podanych w TJ jest liczona na serwerze.
- Dane klienta i część konsultanta rozdzielone także po stronie serwera.
- Propozycje i ręczne oceny, zachowanie ręcznych wyborów po zmianie danych, przeliczanie AUTO.
- Strony zainteresowane z reguł własnych LUB powiązań z aktywnymi czynnikami; źródła wymagań zachowane.
- Obieg całego pakietu: roboczy → przekazany → w weryfikacji → zatwierdzone dane lub zwrot.
- Wycofanie przez klienta tylko przed rozpoczęciem weryfikacji; później prośba z uzasadnieniem.
- Ponowne otwarcie zatwierdzonych danych przez uprawnionego konsultanta z uzasadnieniem.
- Oddzielne lata, kopiowanie wcześniejszego roku jako nowej wersji roboczej, niezmienne migawki zapisów.
- Numer rewizji i blokada transakcyjna chronią przed nadpisaniem równoległego zapisu.
- PDF, podgląd PDF bez zapisu, prawdziwy DOCX; zapisywane eksporty oznaczone ROBOCZY w dokumentacji 4.1.
- Dotychczasowe limity przechowywania i historia pobrań obowiązują również tutaj.

## Świadome ograniczenia testowej implementacji

Nie deklarujemy ukończenia specyfikacji audytora ani publikacji dokumentów zgodnych z normą.

- Brak finalnego przypisania pytań do sekcji/osób: obecnie kolejność biblioteki, weryfikacja całego pakietu, nie sekcji.
- Wszystkie czynniki regulacyjne KTX-ZR-* i niejednoznaczny KTX-WO-03 są wstrzymane. Nie są generowane jako fakty ani nie uruchamiają mapowań.
- Brak uzasadnienia odrzucenia strony lub rozstrzygnięcia zgodności blokuje zatwierdzenie danych.
- Ocena zgodności jest obecnie zbiorcza dla strony; wymagania mapowane i ich oznaczenia źródłowe widoczne osobno. Finalny model oceny per wymaganie wymaga doprecyzowania.
- Finalne szablony greenfield/nadbudowa i publikacja KON + STR są wstrzymane; eksport jest zestawieniem roboczym danych, nie implementacją czterech finalnych szablonów.
- Dokumenty bazowe nadbudowy można wskazać opisowo; pliki nadal dodaje się przez dokumentację punktu. Nie ma automatycznego uznawania wdrożenia za zakończone.
- Nie ustawiono obiecanego klientowi SLA, nie dodano automatycznej wysyłki ani powiadomień.
- Stare adresy ekranów przekierowują do biblioteki 1.3. Dotychczasowe endpointy zapisu/eksportu pozostają dla zgodności ze starszymi, już otwartymi formularzami; dane historyczne nie są usuwane.

## Test ręczny

Poprawki stabilności: obsługa przecinka dziesiętnego, rozpoznawanie niepełnej sumy nośników,
nieedytowalne ustalenia AUTO także po stronie serwera, jawnie puste opisy zamiast przywracania
domyślnego tekstu, unieważnianie oceny zgodności po zmianie wymagań lub źródeł, kompletne dane
pomocnicze w eksportach. Formularz zachowuje dodatkowe zapisane wiersze oraz pierwotną rewizję
po błędzie walidacji. Historia na ekranie nie pobiera nieużywanych pełnych migawek odpowiedzi.

1. Utworzyć testowy audyt z przypisanym ISO 50001 i użytkownikiem klienta powiązanym z jego firmą.
2. W 4.1 lub 4.2 otworzyć ankietę, odpowiedzieć na pytania i zapisać; sprawdzić przeliczenie czynników.
3. Zmienić dane aktywujące wybrany ręcznie czynnik — sprawdzić zachowanie wyboru i ostrzeżenie.
4. Przekazać ankietę, rozpocząć weryfikację jako konsultant; klient nie może już edytować ani wycofać.
5. Uzupełnić SWOT/wnioski/strony, zapisać i zatwierdzić dane; przetestować zwrot i ponowne otwarcie.
6. Wygenerować PDF/DOCX, sprawdzić etykietę ROBOCZY oraz obecność w dokumentach klienta. Podgląd PDF nie tworzy pliku w rejestrze.
7. Otworzyć następny rok i skopiować poprzedni; poprzedni rok pozostaje niezmieniony.
8. Sprawdzić odmowę dostępu z konta innej firmy i konflikt zapisu z drugiego okna.
