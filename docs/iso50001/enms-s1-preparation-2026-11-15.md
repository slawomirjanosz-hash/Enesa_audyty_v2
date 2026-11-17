# EnMS S-1 i referencyjne 5.1 — stan integracji

Stan: po wyjaśnieniu Zadania F przygotowano pilotaż 0.4 na nowych danych, wyłącznie dla kopii QA (audyt 9). Nie jest to potwierdzenie migracji 0.3 → 0.4 ani zamknięcia wszystkich kryteriów odbioru S-1.

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
- Dowód dodany przez konsultanta po przekazaniu (także powiązany z ocenianym obowiązkiem, jak referencyjny DOW-18) nie unieważnia zatwierdzenia klienta. Zmiana dowodu wybranego w odpowiedzi klienta nadal je unieważnia. Jawny wybór zapisano osobno od ogólnych powiązań dowodu. Excel zachowuje oba rodzaje powiązań i nie przepisuje wersji dowodów podczas importu odpowiedzi.
- Adapter przyjmuje źródłowy priorytet „średni”, zachowując klucz platformy `sredni` i polską etykietę.
- Generator pobiera logo i nazwę wystawcy bezpośrednio z ustawień platformy. Nie polega na atrybucie żądania ustawianym dopiero przez composer widoku; samodzielne żądanie POST również zachowuje logo w migawce.

## Decyzje techniczne zgodne z obecną platformą

- D-1 / D-2: wspólna baza; firma, audyt i zakład wymuszane przez Workspace oraz istniejący dostęp audytora/klienta. Test izolacji używa dwóch organizacji mających własne zapisane rekordy.
- D-3: istniejąca dokumentacja punktu i niezmienne bajty wydania w bazie z SHA-256. Dowód może wskazywać istniejący załącznik albo miejsce przechowywania u organizacji. Nie zmieniono retencji produkcyjnej.
- D-4: istniejące konta platformy; reguła domenowa na serwerze, rzeczywisty użytkownik, dowód upoważnienia i zastępstwo. Bez dodawania SSO w tym etapie.
- D-5: osobne niezmienne wersje rekordów, wskaźnik aktualnej wersji i dziennik w tej samej transakcji. Odczyt historii z tabel wersji, bez odtwarzania zdarzeń.
- D-6: współistnienie modeli. Kontrolowana migracja wymaga osobnego mapowania; nie wykonano automatycznego przeniesienia.
- D-7: wywołania usług w istniejącym procesie Laravel, jawna wersja biblioteki i generatora. Moduły obliczeniowe pozostają poza tym etapem.

## Istniejące dane

Migracja tworzy wyłącznie nowe tabele. Nie aktualizuje profili, odpowiedzi, dokumentów ani zatwierdzeń starych ankiet. Nie wykonano żadnej operacji zmieniającej produkcję. Samo dodanie modułu nie wymaga ponownego zapisu profili.

Nowe 5.1 rozpoczyna osobny obieg. Wcześniejsze odpowiedzi pozostają dostępne. Nie ma automatycznej migracji 0.3 do 0.4, mapowania dawnego „zweryfikowane” na „wykazane”, parsowania opisowego dowodu na metadane ani przenoszenia starych decyzji jako nowych postanowień.

## Sprawdzenia

- Oryginalne testy referencji, bez zmian: test_51.py — 567 zaliczonych; test_51_dokumenty.py — 147 zaliczonych. Są testami prototypu, nie dowodem pełnej równoważności Laravel.
- Sondy źródłowe: 51/51 modelu i 34/34 dokumentów wykryły celowy błąd; łącznie 85/85, bez przerwanych prób. Zakończony raport modelu: tmp/enms-probes-report.json. Skrypt i asercje źródłowe niezmienione, próby na osobnych kopiach.
- Laravel: izolacja firm i dokumentów, odmowy, rollback dziennika, rewizje/historia, zachowanie starego obiegu, uprawnienia, wielostopniowe zgody i cofnięcie wcześniejszej zgody, niezależność/nadzór, wspólne zadanie 5.1/6.2, niezmienne PDF, Excel, prywatność podglądu, pełny obieg do raportu. Sprawdzono także zachowanie grafu UUID po przenumerowaniu symboli. Wynik: 53 testy, 439 asercji. Przebieg referencyjny obejmuje 15 deklaracji, 5 jawnych luk, przekazanie klienta, 18 dowodów, 52 pokrycia, 9 ustaleń, 31 zamknięć, wszystkie 13 ocen oraz raport i załącznik. Fixture zachowuje strukturę, stany, wybory i powiązania, zastępuje dane osób i opisowe dane organizacji treścią testową.
- Sondy natywnego kodu: `php tools/enms-mutation-probes.php` — 13/13 wykrytych mutacji, bazowo 47 testów ENMS. Testy uruchamiają zmienione kopie klas w osobnych procesach i izolowanej bazie. Błędy składni lub uruchomienia nie są zaliczane jako wykrycie mutacji. Sonda izolacji ujawniła i pozwoliła poprawić lukę w pierwotnym teście, który używał pustego drugiego workspace.
- Chromium na izolowanej bazie: 11 zakładek, pola warunkowe, zapis/ponowne otwarcie, dowód, sortowanie w obie strony, szerokość 390 px bez przepełnienia i brak błędów JavaScript.
- W natywnych formularzach przeprowadzono także cały powyższy scenariusz dowodowy: 17 dowodów klienta, 15 deklaracji, zatwierdzenie i blokada edycji klienta, dowód konsultanta, 18 weryfikacji, 52 pokrycia, 9 ustaleń, 31 zamknięć, 13 ocen i zakończenie oceny. Raport i załącznik wygenerowano i pobrano osobno z interfejsu. Log końcowego etapu: tmp/enms-browser-reference-assessment.log. Poprawki selektorów w pomocniczym skrypcie QA nie zmieniały testów źródłowych ani danych produkcyjnych.
- PDF: render Poppler i kontrola tekstu czterech rezultatów; brak technicznych zakładek, danych obcych scenariuszy i identyfikatorów pól. Sprawdzono układ i wszystkie strony załącznika.
- Dodatkowo sprawdzono raport z wypełnionego scenariusza (5 stron) i szczegółowy załącznik (25 stron), z logo pobranym z ustawień platformy; wszystkie strony wyrenderowano i sprawdzono układ.

## Uzgodnienia użytkownika — 16.11.2026

Użytkownik odpowiedział „1” na oba konkretne pytania o wybór pierwszego wariantu. Obie korekty źródła zostały zaakceptowane:

- **PRZ-00 / PRZ-14:** przy „jest” wymagane są szczegóły i dowód; przy „częściowo” i „jeszcze tego nie ma” — plan i termin. Ukryte szczegóły nie blokują przekazania odpowiedzi o braku wdrożenia. Pozostałe reguły spójności, w tym konieczność wyjaśnienia braku wskazania najwyższego kierownictwa przy zadeklarowanym rozwiązaniu, nadal obowiązują.
- **Lista sposobów weryfikacji:** pozostają „wgląd w dokument”, „oględziny”, „rozmowa”, „zapis w systemie” i „próbka”; dodano zaakceptowane „wgląd w zapisy”, „wgląd w zapis w systemie” i „wgląd w pozycję budżetu”. Zachowano brzmienia oraz identyfikatory ośmiu wartości.

Przygotowany kod już realizował obie propozycje. Po akceptacji dodano regresję sześciu kombinacji pozycji i odpowiedzi, blokowania niekompletnych danych oraz zapisu/odczytu wszystkich ośmiu sposobów weryfikacji. Pełna regresja 16.11.2026: **60 testów, 506 asercji — wszystkie zaliczone**. W Chromium sprawdzono pola warunkowe, zapis i ponowne otwarcie PRZ-00/PRZ-14 oraz każdej z ośmiu metod, bez błędów JavaScript. Logi: tmp/enms-approved-rules-tests-2026-11-16.log i tmp/enms-approved-rules-browser.log. Nie zmieniano odpowiedzi ani zatwierdzeń produkcyjnych.

## Otwarte przed pełnym odbiorem

1. **S1-12:** test grafu po przenumerowaniu nie jest pełnym testem migracji starych zapisów 0.3 → 0.4. W referencyjnym `51_model.js` jest wyłącznie hak do „Zadania F” i trzy zakazy migracji, bez pełnego mapowania. Zapytano o lokalizację tej specyfikacji. Automatycznego przenoszenia znaczeń nie przygotowano. Nie deklarować S1-12 jako zaliczonego. Współistnienie chroni wcześniejsze dane. Ponowne sprawdzenie dostępnych materiałów 16.11.2026 nie ujawniło pliku z mapowaniem.
2. **S1-1:** zielone testy źródła i osobne testy integracji nie oznaczają pełnej równoważności wszystkich scenariuszy prototypu i platformy. Scenariusz dowodowy odtworzono wraz z przekazaniem klienta i generowaniem dokumentów; macierz upoważnień, zadania, postanowienia i cykl Karty są sprawdzane osobnymi testami platformy. Nie deklarować automatycznej migracji ani odbioru całego S-1. Sondy natywnej warstwy wspólnej są już wykonane.
3. Retencja oraz operacyjny test odtworzenia kopii produkcji nie zostały tym zadaniem uzgodnione ani potwierdzone.

Odcisk wewnątrz PDF dotyczy zamrożonych danych. SHA-256 całego pliku znajduje się w rejestrze. To dwie różne sumy; plik nie zawiera własnej sumy obliczonej po jej dopisaniu.

## Pilotaż 0.4 — ustalenie z 16.11.2026

Użytkownik potwierdził brak kompletnego mapowania Zadania F i chce sprawdzić sam obieg 0.4 na nowych danych, bez przenoszenia odpowiedzi i zatwierdzeń 0.3. Dotychczasowy kandydat nie był na produkcji: odczyt Railway przed wdrożeniem wykazał web i worker SUCCESS dla `7664e896565b642625b6935d19f68ac9e5e415d7`, bez tabel EnMS. To była przyczyna braku wejścia do nowego modelu, a nie brak uprawnień w ankiecie.

Gałąź `codex/enms-04-pilot` integruje kandydata z tym aktualnym main i zachowuje zmiany innych czatów. Domyślna lista `enms.audit_ids` zawiera tylko audyt **9 (QA-51-20261116), profil 6**. Można ją jawnie zastąpić zmienną `ENMS_04_AUDIT_IDS` (lista numerów rozdzielonych przecinkiem); pusta lista wyłącza pilot bez usuwania zapisów. Sprawdzenie listy jest dodatkowe wobec istniejących uprawnień i przypisania firmy. Dotyczy wszystkich nowych endpointów, wejścia z ankiety, wspólnych zadań, postępu i powiadomień. Audyt 1 oraz pozostałe audyty zachowują dotychczasowy obieg.

- Konsultant: `/audits/9/enms/6`.
- Klient: `/client/audits/9/enms/6`.
- Menu: odpowiedni audyt → ISO 50001 → 5.1 → **Otwórz ankietę 5.1**. W pilocie karta pokazuje wyłącznie obieg 0.4 i jego postęp. Wcześniejsza wersja 0.3 pozostaje dostępna w zakładce **Historia**. Poza pilotem pojedynczy przycisk nadal prowadzi do dotychczasowego obiegu.
- Raport diagnostyczny i Załącznik szczegółowy: zakładka Raport (`?tab=raport`), wybór dokumentu, przygotowanie projektu, osobno wydanie kontrolowane. Wymagane aktualne zakończenie oceny konsultanta i właściwe upoważnienie do wydania.
- Karta przywództwa: zakładka Karta (`?tab=karta`); projekt, wydanie, zatwierdzenie i wejście w życie są osobnymi czynnościami wymagającymi odpowiednich upoważnień.

Nowe tabele powstają przez addytywną migrację schematu. GET nie tworzy rekordów; odpowiedzi, dowody, oceny i zatwierdzenia 0.4 zaczynają się puste. Nie uruchamia się kopiowania danych 0.3, czyszczenia starej ankiety ani nadawania upoważnień. Odczyty zależności z bieżących punktów audytu (np. 4.3) pozostają oznaczonymi cytatami źródłowymi, nie stanowią migracji deklaracji lub zgód 5.1. Niepotwierdzone źródła są ujawniane w ograniczeniach.

Dodatkowa regresja pilota obejmuje wejście obu ról, odmowę wszystkich nowych endpointów poza listą, pusty start mimo zatwierdzonej starej ankiety, zachowanie jej zapisów i dokumentów po zapisie 0.4 oraz ukrycie postępu i powiadomień po wyłączeniu pilota. Końcowy wynik: **64 testy, 559 asercji — wszystkie zaliczone** (tmp/enms-pilot-final-tests.log). W Chromium ponownie sprawdzono 11 zakładek, zapis i odczyt, pola warunkowe, sortowanie, widok mobilny oraz wejście z menu i zakładki dokumentów dla obu ról. Odczyt dużych snapshotów EnMS sortuje wersje w pamięci aplikacji, aby nie wymagać sortowania obszernych JSON w buforze MySQL.

Weryfikacja wdrożenia wymaga osobnego sprawdzenia commitów web i worker na Railway, uruchomionego schematu, wejść obu ról i zachowania skrótów starych zapisów audytów 1 i 9. Sam push nie oznacza dostępności pilota. Pliki tmp/, output/ i outputs/ nie należą do commita.

## Poprawka porównywania źródeł — 17.11.2026

Odczyt produkcji w transakcji READ ONLY potwierdził na audycie 9, profilu 6, rewizji 18: 13 deklaracji, 3 dowody, 1 projekt Karty i osiem fałszywych blokad przekazania w a), b), g), i), l). Wszystkie zapisane cytaty miały te same wartości i typy co aktualne źródła; zakres 4.3 miał wersję 1 po obu stronach. MySQL zmienił kolejność kluczy obiektów JSON, a ścisłe porównanie tablic PHP traktowało tę kolejność jako zmianę danych. Ponowny zapis nie mógł usunąć przyczyny.

`SnapshotComparison` pomija wyłącznie kolejność kluczy obiektów, rekurencyjnie. Zachowuje znaczenie kolejności list, typów, wartości, brakujących kluczy, wersji oraz stanu zatwierdzenia. Zastosowano go do cytatów, weryfikacji cytatów i podstaw zamknięcia kryteriów w ocenie, interfejsie oraz ograniczeniach dokumentów. Nie zmieniono algorytmów odcisków, danych, rewizji ani zatwierdzeń. Nie jest potrzebna migracja ani ponowny zapis istniejących odpowiedzi.

Źródłowe `51_formularz.js`, funkcje `brakiObowiazku` i `gotowoscPrzekazania`, rozdzielają braki formalne od luk wdrożeniowych. „Jeszcze tego nie ma” i „częściowo” wymagają planu i terminu; brak zatwierdzonego dokumentu źródłowego sam w sobie nie blokuje przekazania. Zatwierdzenie źródła nie oznacza merytorycznej weryfikacji cytatu. Rzeczywista zmiana źródła nadal wymaga przeglądu i ponownego zapisu odpowiedzi.

Regresja odtwarza zmianę kolejności kluczy przez bazę, osiem cytatów (w tym brakujące źródła), zapis i przekazanie 13 odpowiedzi, rzeczywistą zmianę wersji/treści, aktualność zamknięć i cały scenariusz referencyjny aż do raportu oraz załącznika. Przed poprawką nowe przypadki reprodukowały blokady; po poprawce ukierunkowany zestaw przeszedł: 16 testów, 86 asercji. W Chromium na osobnej bazie z danymi fabrycznymi sprawdzono przekazanie, ponowne otwarcie, stan konsultanta i wszystkie 11 jego zakładek, bez błędów JavaScript. Produkcyjnego zatwierdzenia nie wykonujemy w imieniu klienta.

Istniejące projekty i wydane dokumenty zachowują zamrożone dane. Poprawka nie przepisuje ich treści; projekt zapisany z błędnym ostrzeżeniem wymaga przygotowania nowego projektu, jeśli ma przedstawiać aktualny stan.

Pełna regresja EnMS i powiązanych ekranów po poprawce: **79 testów, 630 asercji — wszystkie zaliczone** (`tmp/enms-citations-regression.log`). Obejmuje dotychczasowe granice dostępu, izolację firm, pilot, wcześniejszy obieg, Excel, dokumenty i zatwierdzenia.
