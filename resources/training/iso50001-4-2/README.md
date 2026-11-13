# Prezentacja szkoleniowa 4.2

16 slajdów, 1280 × 720, przygotowanych 13.11.2026 w konwencji istniejącej prezentacji 4.1: granat, turkus, limonka, Calibri, logo ENESA i numeracja slajdów.

## Źródła

- Bronisław Pytel, „Energia pod kontrolą”, tom 2, wersja testowa 2026-10 v2, punkt 4.2, s. 21–25; przejście do 4.3, s. 25–26. Plik przekazany przez użytkownika: `Energia_pod_kontrola_Tom2_wersja_testowa_2026-10_v2.pdf`.
- Ten sam autor, tom 1, wersja testowa 2026-10, rozdział 5, s. 43–46: integracja z istniejącymi systemami. Plik: `Energia_pod_kontrola_Tom1_wersja_testowa_2026-10.pdf`.
- Przekazany materiał `ENESA_4.2_Strony_zainteresowane_platforma-6.html`: opis punktu, kwalifikacje, zakres karty wymagania, materiały szkoleniowe i wskazówki audytowe. Specyfikacja techniczna została przeanalizowana, ale nie jest treścią prezentacji.
- Istniejący obieg platformy w `resources/views/audits/stakeholders/show.blade.php` i zakładki z `IsoChapterFourPresentation`.

Treść jest opracowaniem dydaktycznym, nie cytatem z normy. Przykład na slajdzie 9 ma jawnie umowne dane dokumentu i termin. Roczny przegląd opisano jako zalecenie autora, odróżniając je od ustalonych odstępów wymaganych przez normę. Prezentacja nie zawiera progów ani terminów z projektów ustaw. Oryginalnych książek nie dołączamy do repozytorium ani do prezentacji.

## Edycja i odtworzenie slajdów

Edytuj `presentation.html`. To źródło dla autora, nie ankieta ani strona osadzana w aplikacji. Platforma pokazuje wyłącznie PNG przez istniejący kontroler z kontrolą dostępu. Nie dodano filmów, linków zewnętrznych, danych demonstracyjnych do ankiet ani dodatkowych zakładek.

Renderer wymaga Node.js, Playwright i przeglądarki Chromium. Na Windows należy mieć font Calibri, taki jak w materiale 4.1.

```powershell
$env:NODE_PATH = 'ścieżka/do/node_modules/z/playwright'
$env:PRESENTATION_BROWSER = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'
node resources/training/iso50001-4-2/render.cjs
```

Renderer czeka na fonty i logo, kontroluje przekroczenie obszaru treści i zapisuje `slide-1.png` do `slide-16.png`. Po zmianie trzeba obejrzeć wszystkie slajdy w pełnym rozmiarze. Node.js, Playwright i źródłowy HTML nie są potrzebne do oglądania prezentacji przez użytkownika.

Migracja dodaje osobną prezentację do biblioteki 4.2, zachowując materiały wgrane wcześniej. Nie dotyka odpowiedzi, zatwierdzeń, dokumentów ani konfiguracji ankiet. Powtórne wywołanie nie duplikuje tego samego materiału; rollback nie usuwa materiału zmienionego przez administratora.
