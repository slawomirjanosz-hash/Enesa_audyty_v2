# Prezentacja szkoleniowa 4.4

16 slajdów, 1280 × 720, przygotowanych 13.11.2026. Wykorzystano wzorzec prezentacji 4.3: Calibri, granat, turkus, limonka, logo ENESA, układ kart i wierszy, stopka oraz numeracja. Materiał omawia działanie systemu, procesy, przepływ informacji, odpowiedzialność, ciągłość i poprawę wyniku energetycznego.

## Źródła i zakres

- Bronisław Pytel, „Energia pod kontrolą”, tom 2, wersja testowa 2026-10 v2, s. 30–35: punkt 4.4 i podsumowanie rozdziału. Cztery czasowniki, dwa obszary doskonalenia, przykład sprężarkowni, rozróżnienie odchylenia i niezgodności, test ciągłości oraz dokumentowanie.
- Tom 1, wersja testowa 2026-10, s. 43–46: integracja z ISO 9001 i ISO 14001.
- `ENESA_4.4_System_zarzadzania_energia_platforma-5.html`, wersja z 29.10.2026. Potwierdzono zgodność bajtową z plikiem w najnowszej przekazanej paczce `20261111 ENESA_HTML_4_1-10_2_2026-11-11.zip`. Przeczytano specyfikację, bibliotekę i manifest, w tym katalog reguł. Techniczne treści prototypu nie są częścią prezentacji.
- Aktualne zakładki `IsoChapterFourPresentation`, pola, zależności i lista kontrolna `IsoSystemQuestionnaire`, obieg `audits/system/show.blade.php`.

To omówienie dydaktyczne, nie dosłowny tekst normy. Liczby 0,12 / 0,13 / 0,12 kWh/Nm³ są ilustracją z tomu 2, s. 31, a nie danymi klienta ani obowiązkowymi progami. Najnowszy HTML stosuje w swoim przykładzie inne liczby; slajd wskazuje źródło książkowe. Częstotliwość miesięczna jest oznaczona jako zalecenie autora. D-EnMS-SYS-01 opisano jako metodę ENESA, nie dokument narzucony pod tym symbolem przez normę. Nie publikujemy źródłowych książek w repozytorium.

Prezentacja odwołuje się do rzeczywistego obiegu aplikacji. Zatwierdzenie 4.4 wymaga aktualnych zatwierdzeń i dokumentów 4.1–4.3. Zapis roboczy jest możliwy wcześniej. Ocena poprawy wyniku pozostaje zadaniem audytora: obecny backend kontroluje kompletność danych i obecność cyfr, a nie pełną semantykę kierunku poprawy z prototypu. Slajdy nie obiecują takich automatycznych blokad. Szersza przebudowa ankiet pozostaje odrębnym, wstrzymanym zakresem.

## Odtworzenie i wdrożenie

`presentation.html` jest źródłem edytowalnym. Platforma udostępnia wyłącznie wyrenderowane PNG przez istniejący kontroler z kontrolą dostępu. Nie osadzamy całego źródłowego formularza HTML. Wykorzystujemy istniejące karty i przeglądarkę prezentacji w katalogu ISO, audycie oraz strefie klienta.

```powershell
$env:NODE_PATH = 'ścieżka/do/node_modules/z/playwright'
$env:PRESENTATION_BROWSER = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'
node resources/training/iso50001-4-4/render.cjs
```

Renderer wymaga Node.js, Playwright, Chromium i Calibri. Przed wdrożeniem sprawdź wizualnie wszystkie 16 slajdów, testy `IsoPresentationTest` oraz pełne strony platformy z wybranym punktem 4.4. Migracja jest addytywna i nie nadpisuje poprzednich prezentacji. Ponowne wywołanie zachowuje materiał, a rollback nie usuwa edycji administratora. Zmiana nie modyfikuje ankiet, odpowiedzi ani zatwierdzeń audytów.
