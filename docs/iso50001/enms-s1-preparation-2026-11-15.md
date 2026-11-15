# EnMS S-1 i referencyjne 5.1 — stan integracji

Stan: kandydat lokalny, bez wdrożenia. Nie jest to potwierdzenie zamknięcia wszystkich kryteriów odbioru S-1.

## Materiały i zakres

Podstawą jest przeczytany w całości ENESA_S0_2s_projekt_scalony.md z katalogu materiałów 2026.11.15. Zastępuje S-0, S-0.1 i S-0.2; wcześniejszy brak S-0 został wyjaśniony. Źródłem 5.1 jest paczka 2026.11.15 ENESA_5.1_wydanie_referencyjne.zip. Zweryfikowano rozmiary i SHA-256 wszystkich 36 plików manifestu oraz przeczytano specyfikację i kod modelu, formularza, konsultanta i dokumentów. Techniczne zakładki nie są częścią interfejsu ani PDF.

S-1 obejmuje wspólny zapis, dowody, upoważnienia, dokumenty, zadania, terminy i skuteczność oraz adapter 5.1. Nie obejmuje nowego profilu, modułów obliczeniowych ani przebudowy pozostałych 25 ankiet. Panel zadań w dotychczasowym 6.2 odczytuje te same rekordy co nowe 5.1.

## Wykonane lokalnie

- Workspace: kontekst firmy, audytu i zakładu; UUID oddzielone od symboli; niezmienne wersje; zapis z dziennikiem w jednej transakcji; konflikt rewizji; historyczny odczyt bez odtwarzania zdarzeń. GET nie tworzy danych.
- Authorization: status wynikający z dowodu i weryfikacji; osobno aktualne prawo według daty, cofnięcia, użytkownika, zakresu, obszaru, wyłączeń, limitów, zastępstwa i stopni zgody. Macierze są danymi konfiguracji ENESA, nie domniemaniem wymagań ISO.
- Natywne 5.1: trzynaście obowiązków a–m, dwie pozycje metodyczne, procesy, zasoby, nawyki i kryteria ze źródła. Zachowano kody i etykiety; powiązania używają UUID. Dziesięć zakładek obiegu oraz historia wcześniejszego modelu.
- Rzeczywiste źródła, wersje i osobne informacje o odczycie, zatwierdzeniu dokumentu oraz merytorycznej weryfikacji cytatu. Braków nie zastępują dane demonstracyjne.
- Zatwierdzenie klienta, ocena konsultanta, pokrycia, ręczne zamknięcia kryteriów, ustalenia, pytania, postanowienia, zadania i terminy. Zmiana podstawy wymaga przeglądu; częściowe pokrycia są ujawnione w ograniczeniach raportu.
- Wykonanie zadania, zatwierdzenie rezultatu, rozstrzygnięcie zasady Karty i decyzje o dokumentach są osobnymi czynnościami kontrolowanymi na serwerze. Treść zasady jest propozycją, dopóki uprawnione kierownictwo jej nie rozstrzygnie.
- Skuteczność: kontrola właściciela, autora rekomendacji, wykonawcy i odpowiedzialnego za obszar. Samoocena wymaga osobnego nadzoru. Nie zmieniono starego 9.2.
- Projekt, wydanie, zatwierdzenie i wejście w życie dokumentu są odrębne. Wydane bajty są w dokumentacji punktu i niezmiennym archiwum enms_document_files. Pobranie sprawdza SHA-256, nie regeneruje pliku.
- Osobne PDF-y: ankieta, raport, szczegółowy załącznik i Karta. Brak automatycznego procentu zgodności.
- Excel: podpisany kontekst firmy/audytu/zakładu/rewizji i stałe mapowanie. Atomowy import odrzuca obcy lub nieaktualny plik i formuły; nie zatwierdza danych. Arkusz dowodów jest informacyjny; dowody i powiązania edytuje się na platformie.
- Podgląd bez klienta, powiadomienia obu stron, oznaczenie zmienionych pozycji i licznik nowego 5.1 w dashboardzie po rozpoczęciu wypełniania.

## Istniejące dane

Migracja tworzy wyłącznie nowe tabele. Nie aktualizuje profili, odpowiedzi, dokumentów ani zatwierdzeń starych ankiet. Nie wykonano żadnej operacji zmieniającej produkcję. Samo dodanie modułu nie wymaga ponownego zapisu profili.

Nowe 5.1 rozpoczyna osobny obieg. Wcześniejsze odpowiedzi pozostają dostępne. Nie ma automatycznej migracji 0.3 do 0.4, mapowania dawnego „zweryfikowane” na „wykazane”, parsowania opisowego dowodu na metadane ani przenoszenia starych decyzji jako nowych postanowień.

## Sprawdzenia

- Oryginalne testy referencji, bez zmian: test_51.py — 567 zaliczonych; test_51_dokumenty.py — 147 zaliczonych. Są testami prototypu, nie dowodem pełnej równoważności Laravel.
- Sondy źródłowe: 51/51 modelu i 34/34 dokumentów wykryły celowy błąd; łącznie 85/85, bez przerwanych prób. Zakończony raport modelu: tmp/enms-probes-report.json. Skrypt i asercje źródłowe niezmienione, próby na osobnych kopiach.
- Laravel: izolacja firm i dokumentów, odmowy, rollback dziennika, rewizje/historia, zachowanie starego obiegu, uprawnienia, wielostopniowe zgody i cofnięcie wcześniejszej zgody, niezależność/nadzór, wspólne zadanie 5.1/6.2, niezmienne PDF, Excel, prywatność podglądu, pełny obieg do raportu. Sprawdzono także zachowanie grafu UUID po przenumerowaniu symboli. Wynik: 52 testy, 409 asercji. Osobny przypadek odtwarza wszystkie 13 ocen z referencyjnego grafu 18 dowodów, 52 pokryć i 31 zamknięć; fixture nie zawiera danych osób ani opisów organizacji.
- Chromium na izolowanej bazie: 11 zakładek, pola warunkowe, zapis/ponowne otwarcie, dowód, sortowanie w obie strony, szerokość 390 px bez przepełnienia i brak błędów JavaScript.
- PDF: render Poppler i kontrola tekstu czterech rezultatów; brak technicznych zakładek, danych obcych scenariuszy i identyfikatorów pól. Sprawdzono układ i wszystkie strony załącznika.

## Otwarte przed odbiorem i wdrożeniem

1. **PRZ-00 / PRZ-14:** źródłowe polaWidoczne ukrywa szczegóły przy „jeszcze tego nie ma”, natomiast brakiFormalne wymaga ukrytych szczegółów dla każdej odpowiedzianej pozycji metodycznej. Zapytano użytkownika o zasadę: szczegóły przy „jest”, plan i termin przy „częściowo” / „jeszcze tego nie ma”. Lokalny kod realizuje tę propozycję, lecz korekta źródła nie została zatwierdzona.
2. **S1-12:** test grafu po przenumerowaniu nie jest pełnym testem migracji starych zapisów 0.3 → 0.4. Automatycznego przenoszenia znaczeń nie przygotowano. Nie deklarować S1-12 jako zaliczonego. Współistnienie chroni wcześniejsze dane.
3. **S1-1 / S1-2:** zielone testy źródła i osobne testy integracji nie oznaczają, że cały scenariusz referencyjny został odwzorowany w natywnej przeglądarce. Odtworzono graf dowodowy i wszystkie 13 ocen scenariusza BULTEN w usługach Laravel. Pozostają pełne przejście tego scenariusza przez natywny interfejs oraz sondy samej warstwy wspólnej.
4. **Lista sposobów weryfikacji:** scenariusz źródłowy używa dodatkowo „wgląd w zapisy”, „wgląd w zapis w systemie” i „wgląd w pozycję budżetu”, których nie ma w źródłowym select. Zapytano użytkownika o dodanie tych trzech wartości bez usuwania pięciu dotychczasowych. Lokalny kandydat zawiera tę propozycję; wymaga rozstrzygnięcia przed publikacją.
5. Retencja oraz operacyjny test odtworzenia kopii produkcji nie zostały tym zadaniem uzgodnione ani potwierdzone.

Odcisk wewnątrz PDF dotyczy zamrożonych danych. SHA-256 całego pliku znajduje się w rejestrze. To dwie różne sumy; plik nie zawiera własnej sumy obliczonej po jej dopisaniu.

## Wdrożenie

Kandydat jest przygotowany na osobnej gałęzi codex/enms-s1-native-51 do dalszego odbioru. Nie wykonano migracji ani wdrożenia produkcyjnego. Railway: web i worker miały SUCCESS dla 39944a8ff80c0169ebdbd61703f202dafbd49b3b. Nie jest to wdrożenie nowego modelu. Przed scaleniem do main i wdrożeniem trzeba rozstrzygnąć obie otwarte reguły i domknąć odbiór. Pliki tmp/, output/ i outputs/ nie należą do commita.
