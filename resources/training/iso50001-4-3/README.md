# Prezentacja szkoleniowa 4.3

16 slajdów, 1280 × 720, przygotowanych 13.11.2026. Powielono wzorzec prezentacji 4.2: Calibri, granat, turkus, limonka, logo ENESA, stopka i numeracja. Schemat granicy oraz wykres udziałów energii są własnymi elementami dydaktycznymi w tej samej konwencji.

## Źródła i granice opracowania

- Bronisław Pytel, „Energia pod kontrolą”, tom 2, wersja testowa 2026-10 v2, punkt 4.3, s. 25–30. Podstawa definicji zakresu, granic i uprawnień, kompletności rodzajów energii, usług zleconych, dokumentowania i przykładów AutoParts oraz producenta opakowań.
- Tom 1, wersja testowa 2026-10, rozdział 5, s. 43–46: integracja systemów.
- `ENESA_4.3_Zakres_systemu_platforma-4.html`, wersja z 29.10.2026. Sprawdzono zgodność bajtową z plikiem z najnowszej przekazanej paczki `20261111 ENESA_HTML_4_1-10_2_2026-11-11.zip`. Przeczytano opis, specyfikację i manifest. Treści techniczne nie są slajdami.
- Aktualne zakładki z `IsoChapterFourPresentation`, warunki danych źródłowych z `IsoSystemQuestionnaire` oraz obieg z `audits/system/show.blade.php`.

To opracowanie dydaktyczne, nie cytat z normy. Udziały 70% / 29% / 1% oraz przykład dwóch lokalizacji są oznaczone jako ilustracje z książki. Schemat nie jest planem zakładu klienta. Prezentacja nie rozstrzyga aktualnych obowiązków prawnych ani progów. Źródłowe książki nie są publikowane w repozytorium.

Przy analizie wykryto starszą regułę w `resources/iso50001/scope-v1.json`, która z ISO 14001 wyprowadza jednakowe granice EnMS. Najnowszy HTML oraz materiały książkowe każą ustalać granice systemu energetycznego osobno, wykorzystując istniejące procesy. Prezentacja odzwierciedla nowsze materiały. Reguła ankiety pozostaje bez zmian, ponieważ użytkownik wstrzymał szerszą przebudowę ankiet; wymaga osobnego przeglądu w tamtym zakresie.

## Odtworzenie

Źródłem edytowalnym jest `presentation.html`. Nie osadzamy go w interfejsie: istniejący kontroler z kontrolą dostępu udostępnia obrazy PNG z bazy. Strony audytu, klienta i katalogu pokazują kartę w panelu 4.3 dzięki mechanizmowi widoczności wprowadzonemu dla 4.2.

Renderer wymaga Node.js, Playwright, Chromium i fontu Calibri:

```powershell
$env:NODE_PATH = 'ścieżka/do/node_modules/z/playwright'
$env:PRESENTATION_BROWSER = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'
node resources/training/iso50001-4-3/render.cjs
```

Przed wdrożeniem obejrzyj wszystkie 16 PNG i sprawdź pełne strony z wybranym punktem 4.3. Migracja dodaje osobną prezentację bez nadpisywania poprzednich. Jej ponowne wywołanie zachowuje istniejący materiał, a rollback nie usuwa zmian administratora. Odpowiedzi, zatwierdzenia, dokumenty audytów i konfiguracja ankiet nie są modyfikowane.
