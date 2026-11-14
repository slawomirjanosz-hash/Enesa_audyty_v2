# Prezentacje całego audytu ISO 50001 — 14.11.2026

Pakiet uzupełnia wszystkie sekcje `config/iso50001.php`: 44 nowe prezentacje i 319 slajdów. Wraz z zachowanymi prezentacjami 4.1–4.4 daje 48 prezentacji i 383 slajdy. Rozdziały mają po 5 slajdów, nowe punkty szczegółowe po 8. Istniejące materiały 4.1–4.4 mają po 16 slajdów i nie są tutaj zastępowane.

To materiały szkoleniowe: cel, powiązania, sposób pracy, przykład, dowody, typowe błędy i pytania kontrolne. Nie stanowią schematu ankiet ani nowych reguł walidacji. Wdrożenie nie zmienia odpowiedzi, zatwierdzeń, dokumentów audytowych ani decyzji o zachowaniu 9.1.1/9.1.2 w istniejących audytach. Prezentacje obejmują zarówno 9.1, jak i oba podpunkty.

## Źródła i zakres

- Bronisław Pytel, „Energia pod kontrolą”, tom 1, wersja testowa 2026-10 oraz tom 2, wersja testowa 2026-10_v2, przekazane przez użytkownika. Numery stron w stopkach odnoszą się do paginacji PDF.
- Paczka `20261111 ENESA_HTML_4_1-10_2_2026-11-11.zip`: merytoryczne opisy punktów i zależności oraz uwagi ze specyfikacji prototypów. Data paczki nie jest datą powstania każdego znajdującego się w niej prototypu.
- Istniejąca struktura platformy, jej przeglądarka prezentacji i styl materiałów 4.1–4.4.

`sources.json` identyfikuje pliki wejściowe sumami SHA-256. Książki, demonstracyjne ankiety i specyfikacje techniczne nie są publikowane z tym pakietem. Treść slajdów jest opracowaniem dydaktycznym. Przykłady i progi metodyczne nie są przedstawiane jako wymagania normy. Punkty przygotowania i certyfikacji są etapami platformy, nie dodatkowymi punktami normy.

Uwagi dla właściciela merytorycznego: [UWAGI_MERYTORYCZNE.md](UWAGI_MERYTORYCZNE.md). Przegląd na potrzeby prezentacji nie jest potwierdzeniem zgodności wszystkich pytań i reguł ankiet z paczką HTML; przebudowa ankiet pozostaje odrębnym, wstrzymanym zadaniem.

## Budowanie

`content.py` zawiera polską treść, `master.css` wspólny układ, a `build.py` generuje edytowalne HTML i katalog wdrożeniowy. Wymagane są Python 3.10+, PHP oraz Playwright dla Node.js z przeglądarką Chromium/Edge i krojem Calibri. W repozytorium uruchom:

```text
python resources/training/iso50001-complete/build.py
node resources/training/iso50001-complete/render.cjs
```

Opcjonalnie ustaw `PRESENTATION_BROWSER` na ścieżkę przeglądarki. Argumenty renderera ograniczają renderowanie, np. `render.cjs 6-2 6-6`. Po zmianie treści trzeba wygenerować HTML, katalog i PNG dla wszystkich zmienionych prezentacji. Budowanie używa LF na każdej platformie, aby kontrola SHA-256 nie zależała od Windows/Linux.

Renderer sprawdza wymiary 1280 × 720, dostępność logo i fontu oraz przepełnienie tekstu i kart. Materiały należy też obejrzeć w postaci slajdów i w istniejącej przeglądarce platformy.

## Publikacja i ochrona danych

Migracja `2026_11_14_120000_add_remaining_iso_training_presentations` przed zapisem sprawdza cały pakiet: sumy źródeł HTML, liczbę, format i rozmiary PNG. Następnie w transakcji dodaje prezentacje oraz obrazy do istniejących tabel. Ponowne uruchomienie zachowuje istniejące rekordy i zmiany administratora. `down()` celowo nie usuwa materiałów ani ich późniejszych edycji. Dalsze aktualizacje opublikowanych slajdów powinny używać nowej migracji lub istniejącego obiegu administracyjnego.

Slajdy są udostępniane przez dotychczasowe kontrolery z kontrolą dostępu do audytu. Widoki rozdziałów korzystają z tej samej karty i tego samego okna prezentacji co punkty szczegółowe. Źródłowe HTML są plikami do budowania slajdów, a nie osadzanymi ankietami.

## Sprawdzenie

```text
php artisan test --compact tests/Feature/IsoPresentationTest.php tests/Feature/IsoCompletePresentationsTest.php
```

Testy obejmują kompletność wszystkich 48 sekcji, dokładne bajty 319 nowych obrazów w bazie, widoczność we właściwych panelach w trzech widokach, uprawnienia, powtórzenie migracji oraz zachowanie edycji administratora. Test importu PowerPoint wymaga LibreOffice i Popplera; publikacja gotowych PNG nie wymaga tych programów.

Weryfikacja 14.11.2026: 25 testów zaliczonych, 2232 asercje; 1 test konwersji PowerPoint pominięty z powodu braku konwertera. Po końcowym doprecyzowaniu 6.2 i 6.6 ponownie przeszedł test bajtów całego pakietu (1091 asercji). Przejrzano arkusze kontaktowe wszystkich 44 nowych prezentacji oraz poprawione slajdy w pełnym rozmiarze. W lokalnej przeglądarce, po rzeczywistym logowaniu, sprawdzono wszystkie 48 sekcji: komplet 383 slajdów w katalogu; pierwsze dwa slajdy w oknie i ostatni przez chroniony endpoint dla każdej prezentacji w widokach audytora i klienta. Sprawdzono nawigację, małe/duże okno, Escape oraz widok mobilny; brak błędów JavaScript.
