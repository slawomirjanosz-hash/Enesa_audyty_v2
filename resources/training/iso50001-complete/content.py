"""Original Polish training copy; book references use the PDF's printed page numbers.

This is educational content, not a questionnaire schema or a source of validation rules.
The existing presentations for 4.1–4.4 remain separate and unchanged.
"""

DECKS = {}


def deck(section, purpose, principles, steps, dependencies, example, evidence, pitfalls, questions, source):
    def pairs(value):
        return [dict(zip(('title', 'text'), part.strip().split('~', 1))) for part in value.split('|')]
    DECKS[section] = dict(purpose=purpose, principles=pairs(principles), steps=pairs(steps),
        dependencies=pairs(dependencies), example=pairs(example), evidence=pairs(evidence),
        pitfalls=pairs(pitfalls), questions=questions.split('|'), source=source, kind='clause')


deck('3-1', 'Podejmij decyzję o wdrożeniu na podstawie danych, korzyści i realnych zasobów.',
    'Powód biznesowy~Nazwij problem: niepotrzebne zużycie, niepewność kosztów, wymaganie klienta lub brak wiarygodnych danych.|Rachunek z założeniami~Pokaż nakłady, koszty utrzymania i ostrożnie oszacowane korzyści. Oddziel efekt energetyczny od zmiany ceny.|Decyzja kierownictwa~Ustal zakres startu, odpowiedzialność, czas na pracę i termin sprawdzenia postępu.',
    'Zbierz podstawę~Zestaw faktury, odczyty i dane o warunkach pracy. Zaznacz szacunki oraz luki.|Przygotuj warianty~Porównaj możliwe działania i koszty całego okresu analizy. Opisz, jak sprawdzisz efekt.|Zapisz ustalenia~Wskaż decydenta, przyjęty wariant, zasoby i warunki powrotu do decyzji.',
    'Dane startowe~Profil we Wstępie do ISO, koszty i potrzeby organizacji.|3.1 → plan startu~Decyzja wyznacza zadania analizy luk i harmonogramu 3.2.|Dalsze punkty~5.1 pokazuje zaangażowanie kierownictwa, a 7.1 zapewnienie zasobów.',
    'Sytuacja przykładowa~Firma chce kupić monitoring, lecz nie ustaliła, kto będzie analizował dane.|Rozwiązanie~Do decyzji inwestycyjnej dodaje właściciela analizy, czas pracy i sposób sprawdzenia korzyści. Sam zakup urządzeń nie jest efektem.',
    'Dane~Okres, wszystkie nośniki, koszty oraz źródła i jakość informacji.|Uzasadnienie~Założenia, warianty, ograniczenia i sposób późniejszego porównania.|Decyzja~Co przyjęto, kto odpowiada i jakie środki są dostępne.',
    'Gwarantowany procent oszczędności~Potencjał jest szacunkiem do zweryfikowania w zakładzie.|Koszt wdrożenia bez utrzymania~Uwzględnij także pracę ludzi, pomiary i bieżącą obsługę systemu.|Podpis bez zasobów~Sprawdź, czy z decyzji wynikają przydziały i działania.',
    'Jaki problem biznesowy ma rozwiązać wdrożenie?|Z jakich danych wynikają oczekiwane korzyści?|Kto zapewni zasoby i sprawdzi rezultat?',
    'Tom 1, s. 29–30, 50–52. Punkt 3.1 jest etapem platformy, nie numerem wymagania ISO.')

deck('3-2', 'Zamień rozpoznane braki w wykonalny plan wdrożenia.',
    'Stan rzeczywisty~Sprawdź procesy i zapisy, które już działają. Samo istnienie dokumentu nie oznacza wykonania pracy.|Luka~Opisz różnicę między potrzebnym stanem a dowodami dostępnymi dzisiaj.|Zadanie~Każda istotna luka potrzebuje działania, odpowiedzialnego, terminu i zasobów.',
    'Przejdź przez system~Porównaj obszary 4–10 z praktyką zakładu: dane, decyzje, ludzie, działania i ocena wyników.|Ułóż kolejność~Najpierw uzyskaj dane i ustal granice; dopiero na nich oprzyj przegląd, wskaźniki i cele.|Sprawdzaj postęp~Potwierdzaj wykonanie dowodem. Po zmianie warunków aktualizuj harmonogram.',
    '3.1 → 3.2~Decyzja o wdrożeniu daje kierunek i dostępne zasoby.|3.2 → 3.3 / 3.5~Plan określa potrzeby zespołu i kryteria gotowości do startu.|4–10~Szczegółowe ustalenia powstają w odpowiednich punktach audytu.',
    'Sytuacja przykładowa~Dostępne są faktury, lecz brakuje danych o pracy instalacji i osobie odpowiedzialnej za odczyt.|Rozwiązanie~Powstaje zadanie: ustalić źródło, właściciela i rytm odczytów oraz sprawdzić jakość danych przed budową wskaźnika.',
    'Rozpoznanie~Dowód stanu obecnego i opis braku, z przypisaniem do właściwego obszaru.|Plan~Kolejność, zależności, terminy i odpowiedzialni.|Postęp~Wynik sprawdzenia zadania, przeszkody i uzgodnione zmiany planu.',
    'Same odpowiedzi „tak/nie”~Zapisz, na czym oparto ocenę i co trzeba zrobić.|Wszystko równocześnie~Uwzględnij zależności danych i dostępność osób.|Termin bez właściciela~Przypisz zadanie osobie, która ma możliwość je wykonać.',
    'Który brak ogranicza dalszą pracę najbardziej?|Czy każde zadanie ma właściciela i zasoby?|Jaki dowód potwierdzi jego zakończenie?',
    'Tom 1, s. 50–52. Analiza luk i harmonogram są narzędziami przygotowania wdrożenia.')

deck('3-3', 'Zbuduj zespół, który ma zadania, uprawnienia i czas na ich wykonanie.',
    'Kierownictwo~Zapewnia kierunek, zasoby i decyzje, których zespół nie może podjąć sam.|Koordynacja~Ustal osobę pilnującą uzgodnień i przepływu danych. Nazwa stanowiska „Energy Manager” jest propozycją autora.|Właściciele procesów~Włącz produkcję, utrzymanie ruchu, zakupy i inne funkcje mające wpływ na energię.',
    'Spisz zadania~Uwzględnij wdrożenie i późniejszą pracę: odczyty, analizy, komunikację i przeglądy.|Przypisz odpowiedzialność~Ustal wykonawcę, decydenta oraz osoby do uzgodnień i informowania.|Sprawdź wykonalność~Porównaj obciążenie z dostępnym czasem. Zaplanuj zastępstwa i potrzebne kompetencje.',
    'Plan 3.2~Wyznacza zadania i potrzebne kompetencje zespołu.|5.3~Uszczegóławia role, odpowiedzialności i uprawnienia w systemie.|7.1 / 7.2~Potwierdzają dostępność zasobów oraz kompetencje do wykonania pracy.',
    'Sytuacja przykładowa~Kierownik utrzymania ruchu ma koordynować system obok pełnego dotychczasowego zakresu pracy.|Rozwiązanie~Kierownictwo rozdziela zadania i zabezpiecza czas. Potrzeby ocenia się z obciążenia, a nie z uniwersalnego procentu etatu.',
    'Podział pracy~Lista zadań, osoby i uprawnienia do decyzji.|Zasoby~Czas, narzędzia, dostęp do informacji i ewentualne wsparcie zewnętrzne.|Komunikacja~Potwierdzenie, że osoby znają swoje zadania i drogę eskalacji.',
    'Jedna osoba od wszystkiego~Zapewnij udział właścicieli procesów i zastępstwa.|Rola bez uprawnień~Przypisanie zadania wymaga możliwości działania.|Audyt własnej pracy~Organizację audytu oddziel od bezstronnego wykonania oceny.',
    'Kto podejmuje decyzje o zasobach?|Czy osoby mają czas i dostęp do danych?|Kto przejmie pracę podczas nieobecności?',
    'Tom 1, s. 36–38, 50–52. Skład zespołu dopasuj do skali i złożoności organizacji.')

deck('3-4', 'Wykorzystaj działające procesy firmy, zachowując treść właściwą zarządzaniu energią.',
    'Wspólne procesy~Możesz korzystać z istniejącego nadzoru nad dokumentami, szkoleń, audytów i przeglądów.|Treść energetyczna~Uzupełnij je o przegląd energetyczny, znaczące użycia, wskaźniki, linie bazowe i plan danych.|Własne granice~Zakres innego systemu jest punktem odniesienia. Granice EnMS wymagają osobnego ustalenia.',
    'Zmapuj rozwiązania~Wskaż, co już działa w ISO 9001, ISO 14001 lub innych procesach organizacji.|Uzgodnij zmiany~Przypisz właścicieli i uzupełnij brakujące kryteria energetyczne.|Sprawdź praktykę~Zobacz, czy wspólne procesy obejmują energię w decyzjach i zapisach.',
    'Istniejący system~Dostarcza procesy, role i doświadczenie organizacyjne.|4.3 / 4.4~Ustalają granice EnMS oraz powiązania jego procesów.|7.5 / 9.2 / 9.3~Wspólny obieg dokumentów, audyty i przeglądy muszą zachować rozpoznawalną część energetyczną.',
    'Sytuacja przykładowa~Firma ma procedurę zakupów, ale porównuje oferty wyłącznie według ceny zakupu.|Rozwiązanie~W istniejącym procesie dodaje ocenę wpływu na energię i właściwe kryteria ofert. Uzgadnia je z osobą odpowiedzialną za zakupy.',
    'Mapa integracji~Proces istniejący, potrzebna zmiana, właściciel i termin.|Dokumenty~Aktualne wersje z wymaganiami dotyczącymi energii.|Zapisy wykonania~Przykład zakupu, audytu lub przeglądu uwzględniającego EnMS.',
    'Skopiowany zakres ISO 14001~Zweryfikuj granice i możliwość wpływu na wynik energetyczny.|Drugi równoległy obieg~Ustal jedno miejsce aktualnych zapisów i odpowiedzialność.|Wspólny przegląd bez energii~Pokaż dane, ocenę i decyzje dotyczące EnMS.',
    'Co można wykorzystać bez zmian?|Które procesy wymagają treści energetycznej?|Czy granice i odpowiedzialności EnMS są jednoznaczne?',
    'Tom 1, s. 43–46. Integracja jest sposobem organizacji pracy, a nie automatyczną zgodnością zakresów.')

deck('3-5', 'Oceń gotowość do rozpoczęcia wdrożenia i nazwij brakujące warunki.',
    'Decyzja~Kierownictwo zna cel wdrożenia i zapewnia wsparcie.|Odpowiedzialność~Zespół ma przydzielone zadania, czas oraz uprawnienia.|Dane~Masz reprezentatywne informacje albo konkretny plan ich pozyskania.',
    'Sprawdź podstawy~Przejrzyj decyzję, budżet, zespół, dane, istniejące systemy i harmonogram.|Nazwij braki~Każde „nie” przełóż na zadanie. Przy „nie dotyczy” zapisz przyczynę.|Ustal start~Podejmij decyzję o rozpoczęciu prac i sposobie kontrolowania otwartych zadań.',
    '3.1–3.4~Dostarczają decyzję, plan, zespół i sposób integracji.|3.5~Porządkuje warunki startu i brakujące działania.|11.1~Późniejsza ocena gotowości do certyfikacji dotyczy już działającego systemu i jego wyników.',
    'Sytuacja przykładowa~Brakuje części danych o gazie, ale wiadomo, kto i do kiedy je pozyska.|Rozwiązanie~Rozpocznij zadania niezależne od tych danych. Przegląd i porównania wymagające pełnego zbioru zakończ dopiero po jego sprawdzeniu.',
    'Lista gotowości~Stan każdego obszaru i wskazany dowód.|Zadania otwarte~Odpowiedzialny, termin, potrzebna pomoc i wpływ braku.|Decyzja startowa~Co rozpoczynamy oraz kiedy wracamy do oceny postępu.',
    'Procent jako certyfikat gotowości~Wynik listy pomaga planować; nie dowodzi zgodności z normą.|Brak danych wpisany jako zero~Oznacz lukę i zadanie pozyskania informacji.|Sztywne miesiące danych~Okres dobierz do reprezentatywności i warunków działania zakładu.',
    'Czy kierownictwo zapewnia wsparcie?|Czy zespół ma czas na przydzielone zadania?|Czy każdy brak ma plan uzupełnienia?',
    'Tom 1, s. 50–52. Lista gotowości do startu jest pomocą organizacyjną, nie oceną certyfikacyjną.')

deck('5-1', 'Pokaż zaangażowanie kierownictwa w decyzjach, zasobach i codziennych procesach.',
    'Odpowiedzialność~Kierownictwo odpowiada za skuteczność systemu i kierunek działania.|Zasoby i wsparcie~Zapewnia ludziom informacje, czas, środki i możliwość realizacji planów.|Energia w decyzjach~Uwzględnia EnMS w zarządzaniu firmą i wspiera poprawę systemu oraz wyniku energetycznego.',
    'Nazwij zobowiązania~Porównaj zadania kierownictwa z tym, co rzeczywiście wykonano.|Wskaż zdarzenia~Przy deklaracji opisz konkretną decyzję, datę, uczestnika i miejsce zapisu.|Ustal dalszą pracę~Przy braku lub częściowym wykonaniu zapisz działanie i odpowiedzialność.',
    '4.3 / 4.4~Zakres i procesy wyznaczają odpowiedzialność kierownictwa.|5.2 / 5.3~Polityka oraz podział ról przekładają kierunek na pracę ludzi.|6.2 / 7.1 / 9.3~Cele, zasoby i przegląd zarządzania pokazują skutek zaangażowania.',
    'Sytuacja przykładowa~Cel poprawy jest zapisany, lecz jego wykonawca nie ma czasu ani budżetu.|Rozwiązanie~Kierownictwo rozstrzyga zasoby i priorytety, a następnie sprawdza realizację. Dowodem jest decyzja wraz z jej wykonaniem.',
    'Decyzje~Ustalenia z datą dotyczące priorytetów, zasobów, celów i usuwania przeszkód.|Procesy~Przykłady z zakupów, inwestycji, utrzymania ruchu lub planowania produkcji.|Reakcje~Co kierownictwo zrobiło po wynikach pomiarów, audytu i przeglądu.',
    'Sam podpis~Sprawdź, jakie działania wynikły z akceptacji.|Delegowanie całej odpowiedzialności~Koordynator wspiera kierownictwo, ale go nie zastępuje.|Ocena własna jako dowód~Deklarację porównaj z zapisami i praktyką.',
    'Która decyzja kierownictwa zmieniła sposób pracy?|Jak zapewniono zasoby do celów?|Jak kierownictwo sprawdza wynik i skuteczność systemu?',
    'Tom 2, s. 40–44; HTML 5.1 z paczki 11.11.2026. Rejestr ENESA jest metodą zebrania dowodów.')

deck('5-2', 'Przygotuj politykę energetyczną, która wyznacza kierunek i jest stosowana w organizacji.',
    'Kierunek~Polityka odpowiada charakterowi organizacji i stanowi podstawę celów energetycznych.|Zobowiązania~Obejmuje zasoby i informacje, mające zastosowanie wymagania oraz doskonalenie systemu i wyniku.|Decyzje techniczne~Wspiera zakupy i projektowanie uwzględniające poprawę wyniku energetycznego.',
    'Dopasuj treść~Oprzyj politykę na zakresie, kontekście i przyjętych zobowiązaniach organizacji.|Przyjmij i udostępnij~Ustal aktualną wersję, zatwierdzenie oraz sposób komunikowania i dostępności.|Przeglądaj~Oceń aktualność. Zapisz także uzasadnioną decyzję o utrzymaniu treści.',
    '4.1–4.3~Kontekst, wymagania i zakres wyznaczają treść polityki.|5.2 → 6.2~Zobowiązania polityki stanowią ramy dla celów i planów.|7.3 / 7.4 / 9.3~Świadomość, komunikacja i przegląd zapewniają jej znajomość i aktualność.',
    'Sytuacja przykładowa~Podpisana polityka znajduje się tylko w teczce koordynatora.|Rozwiązanie~Ustal, gdzie ludzie ją znajdą i jak poznają jej znaczenie dla własnej pracy. Sprawdź zrozumienie podczas rozmowy.',
    'Treść i wersja~Aktualna, zatwierdzona polityka z określonym zakresem stosowania.|Komunikowanie~Kto, komu, kiedy i jak przekazał jej sens.|Przegląd~Data, wynik oceny aktualności i ewentualna zmiana.',
    'Tekst z innej firmy~Powiąż politykę z rzeczywistym charakterem i skalą organizacji.|Obietnica bez miary~Dodatkowe zobowiązanie liczbowe potrzebuje sposobu weryfikacji.|Nowa wersja bez przekazania~Po zmianie sprawdź dostępność i komunikację.',
    'Czy polityka odpowiada organizacji i jej zakresowi?|Czy ludzie wiedzą, gdzie jest i co z niej wynika?|Czy cele oraz decyzje są z nią spójne?',
    'Tom 2, s. 45–49; HTML 5.2 z paczki 11.11.2026. Polityka jest udokumentowaną informacją.')

deck('5-3', 'Przypisz odpowiedzialności i uprawnienia tak, aby każde zadanie miało wykonawcę.',
    'Przydzielenie~Wskaż, kto odpowiada za działanie systemu, zgodność, plany i raportowanie wyników.|Uprawnienia~Odpowiedzialność połącz z dostępem do danych, decyzji i potrzebnych zasobów.|Przekazanie ustaleń~Osoby muszą znać swoje role, współpracowników i drogę zgłoszenia problemu.',
    'Zidentyfikuj pracę~Uwzględnij zadania zespołu, właścicieli procesów i znaczących użyć energii.|Ustal podział~Określ wykonawcę i decydenta. Przy kilku wykonawcach rozgranicz zakresy.|Sprawdź działanie~Prześledź przykład: od wykrycia problemu do decyzji i potwierdzenia wykonania.',
    '5.1 / 4.4~Kierownictwo i procesy stanowią podstawę przypisania ról.|6.3 → 5.3~Lista znaczących użyć pozwala doprecyzować odpowiedzialność za obszary.|7.1 / 7.2 / 9.2~Zadania wymagają zasobów i kompetencji; audyt wymaga bezstronności.',
    'Sytuacja przykładowa~Dwie osoby są wpisane jako odpowiedzialne za odczyty, a każda oczekuje działania drugiej.|Rozwiązanie~Ustal, kto wykonuje odczyt, kto go sprawdza i kto zastępuje nieobecnego. Przekaż ten podział obu osobom.',
    'Podział ról~Zadania, odpowiedzialni, decydenci i zakres współpracy.|Uprawnienia~Dostęp do informacji, prawo decyzji i zasady eskalacji.|Przekazanie~Dowód, że ustalenia zostały zakomunikowane i są rozumiane.',
    'Odpowiedzialność zbiorowa bez granic~Rozdziel czynności i wynik, za który odpowiada każda osoba.|Rola tylko na schemacie~Sprawdź realne możliwości wykonania zadania.|Organizator jako jedyny audytor~Zapewnij obiektywność oceny własnego procesu.',
    'Czy każde zadanie ma odpowiedzialnego?|Czy odpowiedzialny ma potrzebne uprawnienia?|Czy podział jest znany i działa podczas nieobecności?',
    'Tom 2, s. 49–58; HTML 5.3 z paczki 11.11.2026. Forma matrycy ról jest rozwiązaniem metodycznym.')

deck('6-1', 'Rozpoznaj ryzyka i szanse, zaplanuj reakcje i sprawdź ich skuteczność.',
    'Źródła~Uwzględnij kontekst 4.1, wymagania 4.2 oraz własne działania i procesy.|Ocena~Ustal znaczenie ryzyka lub szansy według przyjętej metody. Skale i progi nie są uniwersalnymi wartościami normy.|Reakcja~Działanie potrzebuje zasobów, odpowiedzialnego oraz sposobu oceny skuteczności.',
    'Połącz z ustaleniem~Wskaż, z jakiego czynnika, wymagania lub procesu wynika pozycja.|Zdecyduj o działaniu~Zapisz ocenę i uzasadnienie. Przy szansie rozważ prawdopodobieństwo uzyskania korzyści.|Sprawdź rezultat~Oddziel wykonanie działania od potwierdzenia, że ograniczyło ryzyko lub wykorzystało szansę.',
    '4.1–4.3 / 5.2~Kontekst, zobowiązania, granice i polityka kierują planowaniem.|6.1 → 6.2 / 7.1~Wybrane działania wpływają na cele i zapotrzebowanie na zasoby.|9.3~Przegląd zarządzania ocenia zmiany ryzyk, szans i potrzebne decyzje.',
    'Sytuacja przykładowa~Tylko jedna osoba potrafi przygotować dane do wskaźnika.|Rozwiązanie~Przydziel zastępstwo, opisz metodę i przeprowadź próbę podczas nieobecności. Samo dopisanie nazwiska nie potwierdza skuteczności.',
    'Podstawa~Powiązanie pozycji z konkretnym ustaleniem i opis jej znaczenia.|Działanie~Właściciel, zasoby, termin i włączenie w pracę organizacji.|Ocena~Metoda, termin i wynik sprawdzenia skuteczności.',
    'Rejestr bez szans~Rozważ również możliwości poprawy, a nie tylko zagrożenia.|Odznaczenie czynnika bez decyzji~Pokaż jego skutek dla planowania lub uzasadnij brak działania.|Mniejszy zakup energii = mniejsze zużycie~Rozróżnij bilans zakupu od poprawy wyniku energetycznego.',
    'Z jakiego ustalenia wynika każda pozycja?|Czy reakcja odpowiada jej znaczeniu?|Kiedy i czym sprawdzicie skuteczność?',
    'Tom 2, s. 60–66; HTML 6.1 z paczki 11.11.2026. Macierz i progi oceny są metodą organizacji.')

deck('6-2', 'Przełóż kierunek poprawy na cele, zadania energetyczne i plany działań.',
    'Cel~Określa rezultat, do którego dąży organizacja, na odpowiedniej funkcji i szczeblu.|Zadanie energetyczne~Precyzuje ilościowo oczekiwany wynik tam, gdzie dotyczy to poprawy energetycznej.|Plan działania~Określa pracę, odpowiedzialność, środki, termin i sposób weryfikacji rezultatu.',
    'Ustal podstawę~Wykorzystaj politykę, ryzyka, znaczące użycia i możliwości z przeglądu.|Dobierz porównanie~Wskaż EnPI, odpowiadającą mu linię bazową i warunki oceny. Metodę ustal przed działaniem.|Monitoruj i komunikuj~Sprawdzaj postęp oraz przyczyny odchyleń. Wynik potwierdź danymi po wykonaniu.',
    '6.1 / 6.3 / 5.2~Dostarczają przesłanki wyboru celów.|6.4 / 6.5 / 6.6~Wskaźnik, baza i plan danych umożliwiają ocenę zadania.|7.1 / 9.1 / 9.3~Zasoby, monitorowanie i decyzje kierownictwa podtrzymują realizację.',
    'Przykład dydaktyczny~Cel: poprawić pracę sprężarkowni. Zadanie: obniżyć EnPI sprężarkowni o 5% do końca roku wobec uzasadnionej bazy.|Plan i sprawdzenie~Określ działania techniczne, właściciela i warunki pomiaru. Sam termin modernizacji nie jest metodą wykazania poprawy.',
    'Cele i zadania~Rodzaj, zakres, termin, odpowiedzialny i sposób monitorowania.|Plan~Czynności, środki oraz powiązanie z celem.|Weryfikacja~Wskaźnik, baza, okres, źródła, warunki porównania i osoba oceniająca.',
    'Cel bez planu~Także cel systemowy potrzebuje działań i odpowiedzialności.|Pomiar uznany za poprawę~Instalacja licznika umożliwia ocenę; nie dowodzi oszczędności.|Porównanie wymyślone po wykonaniu~Ustal metodę przed rozpoczęciem zadania.',
    'Czy wiadomo, jaki rezultat ma zostać osiągnięty?|Czy plan ma środki i odpowiedzialnego?|Czy sposób sprawdzenia rozdziela efekt działania od zmiany warunków?',
    'Tom 2, s. 78–82; HTML 6.2 z paczki 11.11.2026. Szczegółowy układ formularza jest metodyką ENESA.')

deck('6-3', 'Ustal, gdzie i jak organizacja używa energii oraz gdzie może poprawić wynik.',
    'Analiza energii~Zestaw rodzaje energii, obecne i wcześniejsze zużycie oraz wiarygodność danych.|Znaczące użycia — SEU~Wyznacz je na podstawie przyjętych metod i kryteriów; opisz wpływające zmienne i osoby.|Możliwości poprawy~Zidentyfikuj je, uszereguj i oszacuj przyszłe zużycie z opisem założeń.',
    'Zamknij zakres~Użyj granic z 4.3. Przed sumowaniem zastosuj jednolite okresy i jednostki.|Przeanalizuj obszary~Oddziel pomiar, obliczenie i szacunek. Sprawdź bilans oraz kwalifikację SEU.|Utrzymuj aktualność~Zapisz metody, kryteria i wyniki; aktualizuj przegląd w ustalonych odstępach i po istotnych zmianach.',
    'Profil / 4.3~Dane zakładu i granice wskazują, co obejmuje analiza.|6.3 → 6.4–6.6~SEU, zmienne i wyniki są podstawą wskaźników, baz i zbierania danych.|6.2 / 5.3 / 8.1~Możliwości stają się planami, a obszary otrzymują właścicieli i kryteria pracy.',
    'Sytuacja przykładowa~Suma odczytów podliczników jest mniejsza niż zużycie z licznika głównego.|Rozwiązanie~Wyjaśnij zakres, okresy i różnicę bilansową. Nie prezentuj niepełnej sumy jako całego zużycia ani szacunku jako pomiaru.',
    'Energia i obszary~Wartości, jednostki, okresy oraz źródła i metoda ustalenia.|SEU~Kryterium, uzasadnienie, zmienne istotne, czynniki statyczne, wynik i osoby.|Możliwości i prognoza~Potencjał, założenia, priorytet oraz zasady aktualizacji.',
    'Stały próg bez uzasadnienia~Progi kwalifikacji są decyzją metodyczną organizacji.|Przegląd bez nowych instalacji~Aktualizuj analizę po zmianach w zakresie i procesach.|Fotowoltaika jako automatyczna oszczędność~Oddziel zmianę źródła i zakupu od zmiany zużycia.',
    'Czy bilans obejmuje cały przyjęty zakres?|Czy każde SEU ma uzasadnienie i odpowiedzialnych?|Czy możliwości poprawy wynikają z danych?',
    'Tom 2, s. 68–76; HTML 6.3 z paczki 11.11.2026. Progi liczbowe prototypu są metodyką, nie normą.')

deck('6-4', 'Dobierz wskaźniki, które pozwolą ocenić wynik energetyczny i wykazać jego zmianę.',
    'Dobór postaci~Rozważ wartość bezwzględną, iloraz lub model — zależnie od procesu i zmiennych.|Kierunek poprawy~Zapisz, czy poprawę oznacza spadek czy wzrost. Sam znak zmiany nie wystarcza.|Wiarygodne wartości~Ustal wzór, jednostkę, dane, częstotliwość, odpowiedzialnego i zasady aktualizacji.',
    'Rozpoznaj zależności~Sprawdź, co wpływa na zużycie w organizacji i przy SEU.|Uzasadnij wskaźnik~Oceń, czy wybrana postać nadaje się do porównania. Sam iloraz nie gwarantuje porównywalności.|Zachowuj wyniki~Utrzymuj metodę i zapisuj wartości z okresem oraz źródłem danych.',
    '6.3 → 6.4~Przegląd dostarcza SEU, zmienne i bieżący wynik.|6.5 / 6.6~Każdy potrzebny wskaźnik wymaga odpowiedniego odniesienia i danych.|6.2 / 9.1 / 10.2~EnPI służy planowaniu, monitorowaniu i wykazaniu poprawy.',
    'Sytuacja przykładowa~Zużycie energii spadło, bo zakład produkował mniej. Koszt energii wzrósł z powodu ceny.|Rozwiązanie~Oceń wynik wskaźnikiem uwzględniającym istotne warunki. Koszt i zużycie nie opisują samodzielnie tego samego zjawiska.',
    'Karta wskaźnika~Zakres, postać, wzór, jednostka, kierunek i uzasadnienie.|Metoda~Źródła, rytm obliczeń, odpowiedzialny i zasady zmiany wskaźnika.|Wartości~Okres, liczba, podstawa ustalenia i uwagi o porównywalności.',
    'Jeden iloraz na każdy proces~Dobierz postać do rzeczywistej zależności zużycia.|Model oceniony tylko przez R²~Sprawdź również sens techniczny, dane, błędy i zakres stosowania.|Sumowanie różnych EnPI~Nie dodawaj wskaźników o różnych jednostkach i znaczeniach.',
    'Czy wskaźnik odpowiada ocenianemu procesowi?|Czy kierunek poprawy jest jednoznaczny?|Czy wartość da się odtworzyć ze źródła?',
    'Tom 2, s. 82–85; HTML 6.4 z paczki 11.11.2026. Dobór i jakość modelu ocenia człowiek.')

deck('6-5', 'Ustal wiarygodny punkt odniesienia i zachowaj historię jego zmian.',
    'Odpowiedni okres~Wybierz okres reprezentatywny dla ocenianego wyniku i uzasadnij wybór.|Ta sama miara~Powiąż bazę z konkretnym EnPI, jego jednostką, zakresem i warunkami.|Kontrolowana rewizja~Oceń przesłanki zmiany i zachowaj poprzednią wartość, powód oraz zapis decyzji.',
    'Wybierz podstawę~Wykorzystaj dane przeglądu energetycznego i metodę wskaźnika.|Zapewnij porównywalność~Uwzględnij istotne zmienne i czynniki statyczne. Opisz potrzebną normalizację.|Pilnuj zmian~Rewiduj bazę według ustalonej metody, gdy istnieją przesłanki, i zachowaj historię.',
    '6.3 / 6.4~Dane i karta wskaźnika określają, czego dotyczy baza.|6.5 → 6.2 / 9.1~Cele i bieżące porównania korzystają z tego samego odniesienia.|9.3 / 10.2~Zmiany bazy wymagają przejrzystego wyjaśnienia przy ocenie wyników.',
    'Sytuacja przykładowa~Po modernizacji wskaźnik jest lepszy, więc ktoś chce ustawić niższą bazę bez wyjaśnienia.|Rozwiązanie~Nie usuwaj śladu osiągniętej poprawy. Najpierw oceń rzeczywiste przesłanki rewizji, a każdą zmianę udokumentuj.',
    'Baza~Wskaźnik, okres, wartość, jednostka i źródło danych.|Porównywalność~Zmienne, czynniki statyczne i metoda normalizacji.|Historia~Data, wcześniejsza i nowa wartość, przyczyna oraz zatwierdzona zmiana.',
    'Przesuwanie bazy po poprawie~Sama poprawa nie uzasadnia ukrycia wcześniejszego odniesienia.|Liczba przepisana do kilku ankiet~Sprawdzaj zgodność z aktualną kartą i historią zmian.|Dowolny rok kalendarzowy~Uzasadnij reprezentatywność okresu dla danego wskaźnika.',
    'Dlaczego ten okres jest odpowiedni?|Czy EnPI i EnB dotyczą tych samych warunków?|Czy każdą zmianę bazy można prześledzić?',
    'Tom 2, s. 86–88; HTML 6.5 z paczki 11.11.2026. Rewizja i korekta danych wymagają rozróżnienia.')

deck('6-6', 'Zaplanuj zbieranie danych, które rzeczywiście umożliwią pomiar i ocenę wyników.',
    'Co zbieramy~Zmienne istotne dla SEU, zużycie SEU i organizacji, kryteria operacyjne, czynniki statyczne i dane planów działań.|Jak zbieramy~Źródło, jednostka, częstotliwość, odpowiedzialny i miejsce zachowania danych.|Jakość pomiaru~Zapewnij dokładność i powtarzalność pomiaru kluczowych charakterystyk oraz zachowaj odpowiednie dowody.',
    'Wyprowadź potrzebę~Powiąż każdą pozycję z SEU, wskaźnikiem, kryterium lub sposobem weryfikacji celu.|Sprawdź możliwość odczytu~Dostosuj częstotliwość do źródła i rytmu analizy. Jedna pozycja może służyć kilku potrzebom.|Ustal obsługę luk~Wskaż zastępstwa, kontrolę jakości i postępowanie przy braku lub błędzie danych.',
    '6.2–6.5~Cele, SEU, wskaźniki i bazy określają zapotrzebowanie na informacje.|8.1 ↔ 6.6~Kryteria pracy wskazują parametry, które trzeba obserwować.|9.1~Sprawdza dane zebrane zgodnie z planem i zamienia je w ocenę wyniku.',
    'Sytuacja przykładowa~Plan obiecuje analizę godzinową, a jedynym źródłem jest faktura miesięczna.|Rozwiązanie~Zapewnij źródło danych o potrzebnej rozdzielczości albo zmień plan i sposób analizy. Zapisz właściciela odczytu.',
    'Pozycja planu~Parametr, jednostka, cel wykorzystania, źródło i rytm.|Odpowiedzialność~Kto zbiera, sprawdza i przechowuje dane; kto zastępuje przy pracy ręcznej.|Jakość~Dowody dokładności i powtarzalności oraz sposób obsługi braków.',
    'Lista życzeń pomiarowych~Sprawdź, czy źródło faktycznie daje zaplanowane dane.|Same liczniki główne~Oceń potrzeby SEU, zmiennych i planów działań.|Plan jako dowód realizacji~Dopiero odczyty i ich analiza pokazują wykonanie.',
    'Czy każda potrzebna dana ma źródło?|Czy częstotliwość jest wykonalna i przydatna?|Co stanie się po braku odczytu lub nieobecności osoby?',
    'Tom 2, s. 89–95; HTML 6.6 z paczki 11.11.2026. Plan i rzeczywista realizacja to różne informacje.')

deck('7-1', 'Zapewnij ludzi, czas, środki i narzędzia potrzebne do działania systemu.',
    'Potrzeby~Wyprowadź zasoby z konkretnych zadań, planów działań i zbierania danych.|Dostępność~Sprawdź przydział czasu poszczególnym osobom, budżet, infrastrukturę i wsparcie zewnętrzne.|Decyzja~Ustal, jak organizacja zapewni zasoby i co zrobi z rozpoznanym deficytem.',
    'Spisz pracę~Oddziel zadania stałe, wdrożeniowe i wynikające z planów poprawy.|Porównaj z przydziałem~Oceń obciążenie każdej osoby. Nadwyżka czasu innej osoby nie usuwa automatycznie jej deficytu.|Przedstaw potrzeby~Zapisz decyzje o czasie, środkach, narzędziach lub zmianie priorytetów.',
    '5.3 / 6.1 / 6.2 / 6.6~Role, działania i plan odczytów pokazują zapotrzebowanie.|7.1 → wykonanie~Dostępne zasoby umożliwiają realizację celów i bieżącej pracy.|9.3~Kierownictwo ocenia wystarczalność zasobów i podejmuje dalsze decyzje.',
    'Sytuacja przykładowa~Zespół ma łącznie wolny czas, ale wszystkie odczyty i analizy przypisano jednej przeciążonej osobie.|Rozwiązanie~Rozdziel pracę według kompetencji i dostępności. Zapisz, które zadania zdjęto lub komu je przekazano.',
    'Zadania~Rodzaj pracy, potrzebny czas, osoba i okres planowania.|Przydziały~Dostępny czas, budżet, narzędzia i podstawa przydziału.|Decyzje~Co zapewniono, kto zdecydował i jakie potrzeby pozostały otwarte.',
    'Budżet tylko do certyfikacji~Uwzględnij późniejsze analizy, odczyty, audyty i aktualizacje.|Suma maskująca przeciążenie~Oceniaj również obciążenie konkretnej osoby.|Brak godzin jako zero~Nieznane zapotrzebowanie wymaga ustalenia.',
    'Z jakich zadań wynikają potrzeby?|Czy osoby mają realnie dostępny czas?|Kto rozstrzygnie brak zasobów?',
    'Tom 2, s. 97–100; HTML 7.1 z paczki 11.11.2026. Rejestr zasobów i progi obciążenia są metodyką ENESA.')

deck('7-2', 'Zapewnij kompetencje do wykonania pracy i zachowaj dowody ich potwierdzenia.',
    'Wymagane umiejętności~Ustal, co muszą umieć osoby, których praca wpływa na wynik lub system.|Stan obecny~Oceń kompetencje na podstawie wykształcenia, szkolenia, umiejętności lub doświadczenia.|Skuteczność działań~Po uzupełnieniu luki sprawdź, czy osoba potrafi wykonać zadanie.',
    'Zacznij od zadania~Wskaż potrzebną umiejętność i sposób jej sprawdzenia.|Uzupełnij lukę~Dobierz instruktaż, mentoring, szkolenie, zmianę przydziału lub kompetentne wsparcie.|Zachowaj dowód~Zapisz wynik oceny, a po działaniu także jego skuteczność.',
    '5.3 / 6.3~Role i osoby wpływające na SEU wyznaczają potrzeby kompetencyjne.|7.1 / 7.3~Działania wymagają czasu; świadomość celu uzupełnia umiejętność wykonania.|8.1 / 8.3 / 9.2~Obsługa, zakupy i audyt potrzebują kompetentnych wykonawców.',
    'Sytuacja przykładowa~Dwie osoby uczestniczyły w szkoleniu. Tylko jedna rozpoznaje nieprawidłową pracę urządzenia.|Rozwiązanie~Przeprowadź sprawdzenie na zadaniu i uzupełnij konkretną lukę. Lista obecności potwierdza udział, a nie umiejętność.',
    'Wymaganie~Rola, zadanie, potrzebna kompetencja i kryterium oceny.|Działanie~Luka, wybrana forma wsparcia, osoba i termin.|Dowód kompetencji~Wynik sprawdzenia i miejsce zapisu, z odpowiednim dostępem do danych osób.',
    'Certyfikat szkolenia jako cała ocena~Sprawdź zdolność wykonania zadania.|Pominięty wykonawca~Uwzględnij także osoby pracujące pod nadzorem organizacji spoza jej załogi.|Zamknięcie luki bez wyniku~Oddziel wykonanie szkolenia od jego skuteczności.',
    'Co osoba ma umieć zrobić?|Jak sprawdzono jej umiejętność?|Gdzie zachowano wynik oceny?',
    'Tom 2, s. 100–103; HTML 7.2 z paczki 11.11.2026. Skala matrycy jest narzędziem, nie progiem normy.')

deck('7-3', 'Sprawdź, czy ludzie rozumieją wpływ swojej pracy na energię i sens ustalonych zasad.',
    'Polityka i wkład~Ludzie znają sens polityki oraz swój wkład w system, cele i korzyści z poprawy.|Wpływ działania~Potrafią wyjaśnić, jak ich zachowanie wpływa na wynik energetyczny.|Konsekwencje~Rozumieją skutki niestosowania wymagań i wiedzą, jak zgłosić przeszkodę.',
    'Dopasuj do grupy~Inaczej wyjaśnij wpływ operatorowi, magazynierowi, zakupom i wykonawcy.|Przekaż i sprawdź~Wykorzystaj instruktaż i rozmowę o rzeczywistej pracy, a nie recytowanie polityki.|Usuń przeszkody~Ustal reakcję na problem organizacyjny i przekaż odpowiedź zgłaszającym.',
    '5.2 / 6.2 / 6.3~Polityka, cele i SEU nadają treść komunikatom.|7.2 / 7.4~Umiejętności oraz obustronna komunikacja wspierają świadome działanie.|10.1 / 10.2~Wykryte luki i przeszkody są materiałem do oceny i doskonalenia.',
    'Sytuacja przykładowa~Magazynier umie zamknąć drzwi chłodni, ale zostawia je otwarte podczas rozładunku.|Rozwiązanie~Wyjaśnij wpływ na energię i sprawdź przeszkody w organizacji pracy. Sama informacja nie naprawi zbyt trudnej obsługi drzwi.',
    'Treść dla grup~Co dana grupa ma rozumieć i jak wiąże się to z jej zadaniami.|Przekazanie~Kanał, prowadzący, odbiorcy i właściwy moment.|Sprawdzenie~Wyniki rozmów, stwierdzone luki oraz podjęte działania.',
    'Plakat zamiast zrozumienia~Porozmawiaj z ludźmi o ich pracy.|Jeden tekst dla wszystkich~Odnieś wpływ do zadań konkretnej grupy.|Sama załoga etatowa~Uwzględnij osoby zewnętrzne pracujące pod nadzorem.',
    'Czy ludzie wiedzą, co w ich pracy wpływa na energię?|Czy rozumieją cel zasad i skutki ich pomijania?|Czy mogą zgłosić przeszkodę i uzyskać odpowiedź?',
    'Tom 2, s. 103–105; HTML 7.3 z paczki 11.11.2026. O świadomości świadczy rozmowa i praktyka.')

deck('7-4', 'Zapewnij wiarygodną komunikację i działającą drogę zgłaszania uwag oraz propozycji.',
    'Plan przekazywania~Ustal co, kiedy, komu, jak i przez kogo ma być komunikowane.|Wiarygodność~Komunikaty o wyniku muszą być spójne z danymi systemu.|Głos pracowników~Osoby pod nadzorem organizacji powinny móc przekazywać uwagi i propozycje poprawy.',
    'Rozpoznaj odbiorców~Uwzględnij organizację i strony zewnętrzne oraz ich wymagania.|Ustal obieg~Zapewnij kanał, przyjęcie sprawy, rozpatrzenie i informację zwrotną.|Sprawdź działanie~Prześledź ostatnie zgłoszenia i podstawę komunikatów liczbowych.',
    '4.2 / 5.2~Wymagania stron i polityka wskazują, jakie informacje przekazywać.|6.4 / 6.5 / 9.1~Wskaźniki, baza i wyniki stanowią podstawę komunikatów o energii.|7.3 / 9.3 / 10.2~Uwagi i propozycje wspierają świadomość, decyzje i doskonalenie.',
    'Sytuacja przykładowa~Operator zgłasza wyciek, lecz przez wiele tygodni nie otrzymuje żadnej informacji.|Rozwiązanie~Potwierdź zgłoszenie, wskaż rozstrzygnięcie i powód. Także odmowa wymaga zrozumiałej odpowiedzi, aby kanał pozostał wiarygodny.',
    'Plan~Treść, odbiorcy, termin, kanał i odpowiedzialny.|Podstawa danych~Wskaźnik, okres, baza oraz źródło publikowanej informacji.|Obieg sprawy~Zgłoszenie, decyzja, odpowiedź i rezultat przyjętego działania.',
    'Ogłoszona oszczędność bez podstawy~Sprawdź liczby i warunki porównania.|Skrzynka bez obsługi~Przypisz osobę i wykonalny sposób odpowiedzi.|Zmiana bazy bez sprawdzenia komunikatów~Oceń aktualność wcześniejszych twierdzeń o wyniku.',
    'Czy wiadomo, kto przekazuje każdą informację?|Czy liczby w komunikacie mają sprawdzalne źródło?|Czy zgłaszający dowiaduje się, co zrobiono ze sprawą?',
    'Tom 2, s. 105–108; HTML 7.4 z paczki 11.11.2026. Szczegółowy rejestr odpowiedzi jest metodą ENESA.')

deck('7-5', 'Zapewnij właściwą informację w miejscu pracy i zachowaj wiarygodne zapisy wykonania.',
    'Dokument~Opisuje obowiązujące zasady pracy. Potrzebuje identyfikacji, przeglądu, zatwierdzenia i aktualnej wersji.|Zapis~Pokazuje, co wykonano i z jakim wynikiem. Zachowuje ślad zdarzenia.|Nadzór~Obejmuje dostęp, ochronę, zmiany, przechowywanie oraz potrzebne informacje zewnętrzne.',
    'Ustal potrzebny zakres~Uwzględnij informacje wymagane oraz te, które organizacja uznała za potrzebne.|Zorganizuj dostęp~Wskaż miejsce, właściciela i aktualną wersję. Sprawdź dostępność przy wykonywaniu pracy.|Kontroluj cykl życia~Ustal aktualizacje, ochronę, okresy przechowywania i sposób wycofania.',
    'Wszystkie punkty 4–10~Wytwarzają zasady, decyzje i dowody pracy systemu.|7.5 → użytkownicy~Nadzór ma zapewnić użycie właściwej informacji przez osoby wykonujące zadania.|9.2 / 10.1~Audyt i działania korygujące sprawdzają oraz poprawiają obieg dokumentacji.',
    'Sytuacja przykładowa~Przy urządzeniu wiszą dwie instrukcje z różnymi nastawami.|Rozwiązanie~Ustal obowiązującą wersję, wycofaj nieaktualną i sprawdź przekazanie zmiany. Sam poprawny plik w systemie nie usuwa starej kartki.',
    'Wykaz~Tytuł, identyfikator, wersja, właściciel i miejsce informacji.|Zasady~Zatwierdzanie, dostęp, zabezpieczenie, zmiany i przechowywanie.|Sprawdzenie praktyki~Aktualne instrukcje i dostępne zapisy, także od dostawców i wykonawców.',
    'Wykaz jako dowód nadzoru~Sprawdź faktyczne dokumenty i miejsca użycia.|Jeden okres dla wszystkiego~Dobierz okres do potrzeb i mających zastosowanie wymagań.|Nadpisanie historii wyniku~Zachowaj zapis zdarzenia i czytelny ślad korekty.',
    'Czy wiadomo, która wersja obowiązuje?|Czy użytkownik ma dostęp do niej w potrzebnym miejscu?|Czy można odtworzyć wynik i historię zmian?',
    'Tom 2, s. 108–113; HTML 7.5 z paczki 11.11.2026. Sam wykaz jest narzędziem nadzoru.')

deck('8-1', 'Ustal i stosuj kryteria pracy oraz utrzymania procesów mających wpływ na energię.',
    'Potrzeba procesu~Kryterium powinno wynikać z wymaganej funkcji i warunków pracy.|Sterowanie~Ustal zasady eksploatacji, utrzymania, reagowania na odchylenia i zmian.|Przekazanie~Kryteria muszą być znane obsłudze oraz wykonawcom procesów zlecanych na zewnątrz.',
    'Wybierz procesy~Uwzględnij SEU i działania z planów, gdzie brak kryteriów może prowadzić do istotnych odchyleń.|Opisz działanie~Określ parametry, tryby pracy, utrzymanie, odpowiedzialność i reakcję na sygnał.|Sprawdź stosowanie~Porównaj instrukcję z rzeczywistą pracą i zapisem ostatniej zmiany lub usługi.',
    '6.3 / 6.2~SEU i plany działań wskazują obszary nadzoru.|6.6 / 7.2 / 7.5~Pomiary, kompetencje i aktualne instrukcje umożliwiają stosowanie kryteriów.|8.2 / 8.3 / 9.1~Zmiany projektu i zakupu wpływają na kryteria, a monitoring pokazuje ich stosowanie.',
    'Sytuacja przykładowa~Po serwisie urządzenie wraca do dawnej nastawy, choć proces potrzebuje już innej.|Rozwiązanie~Przekaż kryterium w zleceniu, zapisz nastawy przed i po oraz sprawdź odbiór. Zmianą musi zarządzać wskazana osoba.',
    'Kryteria~Parametr, uzasadniona potrzeba procesu, sposób kontroli i właściwe tryby pracy.|Zmiany i utrzymanie~Kto zmienia, jak ocenia skutki i gdzie zachowuje zapis.|Przekazanie~Aktualna instrukcja, informacje dla wykonawcy i sprawdzenie ich stosowania.',
    'Przepisana nastawa fabryczna~Sprawdź jej związek z potrzebą procesu i bezpieczeństwem.|Serwis poza systemem~Ustal i przekaż wykonawcy wymagania energetyczne.|Odchylenie bez drogi reakcji~Ustal kolejność sprawdzeń oraz osobę odpowiedzialną.',
    'Z czego wynika kryterium pracy?|Kto może zmienić nastawę i jak to zapisuje?|Czy ostatni serwis zastosował aktualne wymagania?',
    'Tom 2, s. 114–117; HTML 8.1 z paczki 11.11.2026. Konkretne nastawy ustala się dla danego procesu.')

deck('8-2', 'Uwzględnij energię na etapie projektu, kiedy można jeszcze zmienić rozwiązanie.',
    'Rozpoznanie wpływu~Oceń nowe, zmieniane i odnawiane obiekty, urządzenia, systemy oraz procesy w przyjętym zakresie.|Możliwości i obsługa~Rozważ poprawę wyniku oraz sposób późniejszego sterowania w przewidywanym okresie eksploatacji.|Przeniesienie ustaleń~Wnioski powinny trafić do specyfikacji, projektu i zakupów, gdzie jest to właściwe.',
    'Zapytaj odpowiednio wcześnie~Oceń wpływ na SEU, warunki pracy, sterowanie i zużycie przed utrwaleniem koncepcji.|Porównaj rozwiązania~Opisz rozważone warianty, decyzje i przyczyny wyboru.|Przygotuj eksploatację~Przekaż kryteria, dane i sposób sprawdzenia wyniku po uruchomieniu.',
    '6.2 / 6.3~Plany i SEU pomagają rozpoznać znaczenie projektu.|6.4 / 6.6 / 8.1~Wskaźniki, pomiary i kryteria pracy są częścią rozwiązania.|8.3 / 9.1~Ustalenia trafiają do zakupów, a później są sprawdzane na rzeczywistych danych.',
    'Sytuacja przykładowa~Energetyka zaproszono do rozmowy po zamówieniu urządzenia, gdy nie można już zmienić sterowania.|Rozwiązanie~Przenieś ocenę do etapu koncepcji. Wniosek o pomiarze i sterowaniu wpisz w wymagania projektu oraz zapytanie ofertowe.',
    'Ocena projektu~Zakres, etap analizy, wpływ energetyczny i uzasadnienie decyzji.|Wnioski~Co przyjęto lub odrzucono oraz gdzie wprowadzono ustalenie.|Działania projektowe~Zachowane wyniki analizy i sposób weryfikacji po uruchomieniu.',
    'Istotność tylko według ceny~Mała zmiana sterowania może mieć duży wpływ energetyczny.|Lista technologii bez wyboru~Pokaż analizę i decyzję odnoszącą się do procesu.|Wniosek pozostawiony w notatce~Sprawdź jego obecność w projekcie i zakupie.',
    'Kiedy oceniono energię w projekcie?|Gdzie trafiły przyjęte wnioski?|Jak wynik będzie sprawdzony po uruchomieniu?',
    'Tom 2, s. 118–121; HTML 8.2 z paczki 11.11.2026. Próg kwotowy prototypu nie jest progiem normy.')

deck('8-3', 'Oceniaj energetyczne skutki zakupów w przewidywanym okresie użytkowania.',
    'Kryteria~Ustal sposób oceny produktów, wyposażenia i usług mogących istotnie wpływać na wynik energetyczny.|Dostawcy~Przekaż wymagania i informację, że wynik energetyczny jest kryterium oceny przy właściwych zakupach.|Energia i odbiór~Ustal odpowiednie specyfikacje zakupu energii oraz sprawdzenie tego, co dostarczono.',
    'Rozpoznaj wpływ~Uwzględnij urządzenia, usługi, energię i ustalenia projektowe.|Porównaj na wspólnej podstawie~Najpierw sprawdź wymagania minimalne, potem oceń oferty przy tych samych założeniach.|Sprawdź po zakupie~Zweryfikuj deklaracje, przekaż kryteria obsługi i zaplanuj ocenę wyniku.',
    '8.2 → 8.3~Przyjęte wnioski projektu stają się wymaganiami zakupowymi.|6.3 / 6.4~Znaczące użycia i wskaźniki określają, co warto oceniać.|8.1 / 6.6 / 9.1~Odbiór prowadzi do kryteriów eksploatacji, pomiarów i sprawdzenia rezultatu.',
    'Przykład dydaktyczny~Dwie oferty spełniają wymagania. Tańsza w zakupie ma większe zużycie przy porównywalnym profilu pracy.|Sposób oceny~Porównaj cenę, energię i istotne koszty w tym samym okresie. Zapisz ceny, jednostki, profil oraz uproszczenia obliczenia.',
    'Kryteria i wymagania~Kategorie zakupów, minimum techniczne i źródło wymagań.|Porównanie~Dane dostawców, założenia, wynik i uzasadnienie wyboru.|Odbiór~Potwierdzenie deklaracji, przekazane kryteria i sposób sprawdzenia pracy.',
    'Moc znamionowa jako zużycie~Potrzebny jest rzeczywisty profil obciążenia i czas pracy.|Najtańsza oferta mimo niespełnienia minimum~Najpierw oceń spełnienie wymagań.|Pochodzenie energii jako poprawa zużycia~Rozróżnij rodzaj źródła od wyniku energetycznego.',
    'Czy dostawca zna kryteria energetyczne?|Czy oferty porównano przy wspólnych założeniach?|Co zostanie sprawdzone przy odbiorze i podczas pracy?',
    'Tom 2, s. 122–130; HTML 8.3 z paczki 11.11.2026. Pełne LCC jest metodą, nie jedyną dopuszczalną formą oceny.')

deck('9-1', 'Połącz ocenę wyniku energetycznego, skuteczności systemu i spełnienia zobowiązań.',
    'Monitoring~Ustal co, jak, kiedy i przez kogo jest mierzone oraz analizowane.|Porównanie~Oceń EnPI wobec właściwego EnB, działanie SEU i skuteczność planów.|Zgodność~Sprawdź wykonanie mających zastosowanie wymagań, z dowodem i reakcją na brak.',
    'Przygotuj dane~Wykorzystaj plan zbierania danych oraz aktualne wskaźniki i bazy.|Wyciągnij wnioski~Uwzględnij zmienne, zbadaj znaczące odchylenia i oceń działanie systemu.|Przekaż do decyzji~Zachowaj wyniki, działania i kwestie wymagające audytu lub przeglądu zarządzania.',
    '4.2 / 6.2–6.6 / 8.1~Rejestr wymagań, cele, dane, wskaźniki, bazy i kryteria są wejściem.|9.1.1 + 9.1.2~Dwa obszary merytoryczne: ocena wyników i ocena zgodności.|9.2 / 9.3 / 10.1 / 10.2~Wyniki służą audytowi, decyzjom, korekcjom i wykazaniu poprawy.',
    'Sytuacja przykładowa~Zużycie spadło w miesiącu o mniejszej produkcji, a raport dla klienta wysłano po terminie.|Rozwiązanie~Osobno oceń zmianę wyniku przy porównywalnych warunkach i wykonanie zobowiązania umownego. Jedna pozytywna liczba nie zamyka obu ocen.',
    'Ustalenia~Charakterystyki, źródła, metody, odpowiedzialni i częstotliwości.|Wyniki~Dane, porównania, badanie odchyleń i reakcje.|Zgodność~Wymaganie, sposób sprawdzenia, dowód, wynik i podjęte działania.',
    'Zbieranie bez analizy~Wskaż osobę i termin oceny danych.|Rejestr zamiast sprawdzenia~Przy każdym obowiązku potrzebna jest ocena wykonania.|Nowy układ jako zmiana historii~W starszym audycie pracuj w zachowanym podziale 9.1.1/9.1.2.',
    'Czy dane prowadzą do oceny i działania?|Czy wynik jest porównany w odpowiednich warunkach?|Czy zobowiązania oceniono na dowodach?',
    'Tom 2, s. 132–142; HTML 9.1 z paczki 11.11.2026. Omówienie monitorowania wyniku oraz oceny zgodności.')

deck('9-1-1', 'Zamień pomiary w ocenę wyniku energetycznego i skuteczności systemu.',
    'Kluczowe charakterystyki~Obejmij skuteczność planów, EnPI, działanie SEU oraz rzeczywiste i oczekiwane zużycie.|Porównywalność~Uwzględnij zmienne, okresy, granice i właściwy kierunek poprawy.|Znaczące odchylenia~Ustal kryteria rozpoznania, zbadaj przyczynę i zachowaj zapis reakcji.',
    'Ustal monitorowanie~Wskaż co, źródło, metodę, rytm pomiaru i analizy oraz odpowiedzialnych.|Oceń wynik~Porównaj wartości z odpowiednim odniesieniem i wyjaśnij zmiany warunków.|Podejmij reakcję~Zbadaj odchylenie. Oceń, czy ujawnia niespełnienie wymagania i potrzebę ścieżki 10.1.',
    '6.2–6.6 / 8.1~Cele, wskaźniki, baza, dane i kryteria pracy.|9.1.1 → ocena~Wynik energetyczny oraz skuteczność systemu wymagają odrębnego wyjaśnienia.|9.2 / 9.3 / 10.1 / 10.2~Wyniki i odchylenia stają się materiałem do dalszych ocen i decyzji.',
    'Przykład dydaktyczny~Załóżmy porównywalne warunki: model przewiduje 1 000 MWh, a odczyt wynosi 970 MWh.|Interpretacja~Rzeczywiste / oczekiwane = 0,970, czyli 3% poniżej odniesienia. Sprawdź poprawność modelu i danych przed uznaniem poprawy.',
    'Dane i metoda~Źródło, okres, jednostki oraz sposób wyznaczenia wartości oczekiwanej.|Porównanie~EnPI, odpowiadający EnB, kierunek i ocena warunków.|Reakcja~Odchylenie, przyczyna, podjęte działanie i wynik sprawdzenia.',
    'Niższy rachunek jako poprawa~Oddziel wpływ ceny, produkcji i pogody.|Każde wahanie jako alarm~Stosuj uzasadnione kryteria znaczących odchyleń.|Powrót wskaźnika bez oceny przyczyny~Sprawdź także, czy wymagania były stosowane.',
    'Kto analizuje zebrane dane?|Czy porównanie uwzględnia istotne zmienne?|Jak zbadano ostatnie znaczące odchylenie?',
    'Tom 2, s. 132–136; część monitorowania HTML 9.1. Przykład liczbowy jest dydaktyczny, nie danymi klienta.')

deck('9-1-2', 'Sprawdź spełnienie mających zastosowanie wymagań prawnych i innych zobowiązań.',
    'Zakres oceny~Wyprowadź go z rejestru 4.2 i ustalonego zastosowania wymagań.|Sprawdzenie~Dla każdego obowiązku ustal, co wykonano, kiedy i na jakiej podstawie.|Reakcja~Zachowaj wynik oceny oraz podjęte działania; aktualizuj ocenę zgodnie z ustalonym rytmem i zmianami.',
    'Przygotuj rejestr~Zweryfikuj aktualność wymagań, kwalifikację, właścicieli i terminy.|Sprawdź wykonanie~Oceń treść oraz datę dowodu. Samo wskazanie przepisu lub umowy nie wystarcza.|Rozstrzygnij braki~Przypisz działania, odpowiedzialnych i sposób sprawdzenia. Ustal potrzebę postępowania z niezgodnością.',
    '4.2 → 9.1.2~Rejestr określa wymagania i przyjęte zobowiązania do oceny.|7.5~Zapewnia dostęp do aktualnych źródeł i zachowanie wyników.|9.3 / 10.1~Wyniki oceny i braki trafiają do decyzji oraz odpowiednich działań.',
    'Sytuacja przykładowa~Raport wymagany umową istnieje, lecz wysłano go po uzgodnionym terminie.|Rozwiązanie~Porównaj dowód wysyłki z wymaganiem terminu. Zapisz rzeczywisty wynik, przyczynę i reakcję, zamiast oznaczać zgodność na podstawie samego raportu.',
    'Wymaganie~Treść, źródło, zastosowanie i termin obowiązku.|Ocena~Data, oceniający, metoda, dowód i wynik.|Działanie~Reakcja na brak zgodności, odpowiedzialny i termin sprawdzenia.',
    'Ocena wyłącznie przepisów~Uwzględnij także umowy i przyjęte zobowiązania.|Brak dowodu jako zgodność~Oznacz niepewność i uzupełnij ocenę.|Stały rytm bez reakcji na zmianę~Oceń potrzebę dodatkowego sprawdzenia po nowym obowiązku.',
    'Czy zakres obejmuje wszystkie obowiązujące wymagania?|Czy każdy wynik ma konkretny dowód?|Czy sprawdzono terminy i reakcje na braki?',
    'Tom 2, s. 137–142; część zgodności HTML 9.1. Aktualne obowiązki ustala się indywidualnie w rejestrze 4.2.')

deck('9-2', 'Przeprowadź bezstronną ocenę opartą na dowodach i wykorzystaj ustalenia do poprawy.',
    'Program~Ustal audyty w zaplanowanych odstępach, biorąc pod uwagę znaczenie procesów i wcześniejsze wyniki.|Zakres i kryteria~Określ, co oceniasz i względem jakiego wymagania.|Obiektywność~Zapewnij kompetentnych audytorów oraz bezstronność procesu oceny.',
    'Przygotuj audyt~Wskaż obszary, kryteria, metodę, termin i audytora. Zaplanuj sprawdzenie wcześniejszych działań.|Zbierz dowody~Łącz dokumenty, rozmowy, obserwacje i pomiary w ścieżkę dotyczącą konkretnego procesu.|Przekaż wyniki~Poinformuj właściwe kierownictwo, zachowaj zapisy i skieruj ustalenia do dalszego postępowania.',
    'Cały system 4–10~Dostarcza wymagania, procesy i kryteria.|9.1 / wcześniejsze audyty~Wyniki i odchylenia pomagają wybrać zakres oraz priorytety.|9.3 / 10.1 / 10.2~Ustalenia prowadzą do decyzji, działań korygujących i możliwości doskonalenia.',
    'Sytuacja przykładowa~Lista kontrolna zawiera same „OK”, lecz nie wskazuje sprawdzonych zapisów.|Rozwiązanie~Prześledź zlecenie serwisu, wymagane kryteria, protokół i rzeczywistą nastawę. Wniosek oprzyj na ustalonym wymaganiu i dowodzie.',
    'Program i plan~Zakres, kryteria, uzasadnienie terminu, audytor i odpowiedzialność.|Ustalenie~Fakt, dowód, wymaganie oraz rzetelny wniosek.|Raport i dalsza praca~Przekazanie wyników, reakcja i sprawdzenie skuteczności wcześniejszych działań.',
    'Ocena własnej pracy~Dobierz audytora tak, aby zapewnić obiektywność.|Liczba niezgodności jako jakość~Oceniaj zakres, dowody i rzetelność wniosków.|Możliwość poprawy nazwana niezgodnością~Niezgodność wymaga wskazania niespełnionego wymagania.',
    'Dlaczego ten obszar audytujemy teraz?|Czy audytor jest kompetentny i bezstronny?|Czy wniosek wynika z dowodu i określonego wymagania?',
    'Tom 2, s. 143–146; HTML 9.2 z paczki 11.11.2026. Terminy 14 dni w prototypie są zaleceniem metodyki.')

deck('9-3', 'Przygotuj dane do przeglądu, który kończy się decyzjami najwyższego kierownictwa.',
    'Wejście~Uwzględnij wcześniejsze działania, zmiany kontekstu, działanie systemu, politykę i możliwości doskonalenia.|Wynik energetyczny~Oceń cele, zadania, plany, wskaźniki i wykazaną poprawę.|Decyzje~Rozstrzygnij potrzeby zmian, zasobów oraz dalszych działań, oceniając przydatność, adekwatność i skuteczność systemu.',
    'Przygotuj materiał~Połącz aktualne dane i rekomendacje z konkretnymi źródłami.|Przeprowadź przegląd~Zapewnij udział właściwego najwyższego kierownictwa i ocenę wymaganych obszarów.|Utrwal decyzje~Zapisz uzasadnienie, a przy działaniu właściciela i termin. Sprawdzaj wykonanie przed następnym przeglądem.',
    '4.1 / 5.2 / 6 / 7 / 9.1 / 9.2~Kontekst, polityka, plany, kompetencje i wyniki dostarczają danych.|9.3 → decyzja~Kierownictwo ocenia system i jego zgodność z kierunkiem organizacji.|5.2 / 6.2 / 7.1 / 10~Decyzje zmieniają politykę, cele, zasoby i działania doskonalące.',
    'Sytuacja przykładowa~Zespół przedstawia wykresy, lecz nie formułuje spraw do rozstrzygnięcia.|Rozwiązanie~Do danych dodaje rekomendację i potrzebną decyzję. Utrzymanie rozwiązania też zapisuje z uzasadnieniem; działaniu przypisuje osobę i termin.',
    'Przebieg~Data, uczestnicy, przygotowane materiały i ocenione obszary.|Ocena~Wnioski o wyniku oraz przydatności, adekwatności i skuteczności systemu.|Decyzje~Treść, podstawa, odpowiedzialność, termin i późniejszy status.',
    'Referat zamiast decyzji~Nazwij sprawy wymagające rozstrzygnięcia.|Przegląd bez właściwego kierownictwa~Koordynator przygotowuje dane, lecz nie zastępuje roli kierownictwa.|Decyzje czekające cały rok~Ustal sposób sprawdzania realizacji między przeglądami.',
    'Jakie decyzje podjęło kierownictwo?|Czy dane wejściowe były aktualne i kompletne?|Kto sprawdzi wykonanie ustaleń?',
    'Tom 2, s. 147–154; HTML 9.3 z paczki 11.11.2026. Rytm przeglądów ustala organizacja.')

deck('10-1', 'Usuń przyczynę niezgodności i potwierdź skuteczność podjętej reakcji.',
    'Rozpoznanie~Niezgodność oznacza niespełnienie konkretnego wymagania poparte dowodem.|Korekcja i przyczyna~Opanuj sytuację oraz skutki, a następnie oceń potrzebę usunięcia przyczyny.|Skuteczność~Sprawdź rezultat w terminie, w którym dowód działania mógł rzeczywiście powstać.',
    'Opanuj i zbadaj~Zapisz fakt, wymaganie, dowód, reakcję i przyczynę. Sprawdź podobne miejsca.|Wdróż adekwatne działanie~Dobierz zakres do skutków i ryzyka powtórzenia. Zmień system, jeśli jest to potrzebne.|Oceń efekt~Nie zamykaj sprawy tylko na podstawie wykonania działania. Zachowaj wyniki weryfikacji.',
    '9.1 / 9.2 / praca bieżąca~Odchylenia, audyty i praktyka mogą ujawnić niezgodność.|10.1 → 8.1 / 7.2 / 7.5~Reakcja może zmienić kryteria, kompetencje lub dokumenty.|9.3 / 10.2~Wyniki działań i powracające problemy są materiałem do decyzji i doskonalenia.',
    'Sytuacja przykładowa~Poprawiono zlecenie serwisu i od razu zamknięto kartę niezgodności.|Rozwiązanie~Sprawdź przy kolejnym serwisie, czy nowe zasady zastosowano i odebrano usługę poprawnie. Wykonanie zmiany dokumentu nie potwierdza jeszcze jej skutku.',
    'Identyfikacja~Charakter niezgodności, niespełnione wymaganie i dowód.|Reakcja~Korekcja, przyczyna, podobne przypadki i uzasadnione działanie.|Weryfikacja~Planowany dowód, termin, wynik sprawdzenia i decyzja o dalszym postępowaniu.',
    'Przyczyna: „zapomniał”~Sprawdź, jakie warunki systemu umożliwiły błąd.|Nieosiągnięty cel jako automatyczna niezgodność~Najpierw ustal przyczyny i ewentualnie niespełnione wymaganie.|Zamknięcie przed dowodem~Termin sprawdzenia dobierz do charakteru działania.',
    'Jakie wymaganie nie zostało spełnione?|Czy usunięto przyczynę i sprawdzono podobne przypadki?|Co dowodzi skuteczności działania?',
    'Tom 2, s. 156–159; HTML 10.1 z paczki 11.11.2026. „5 × Dlaczego” jest narzędziem, nie obowiązkową metodą.')

deck('10-2', 'Doskonal sposób działania systemu i wykazuj poprawę wyniku energetycznego.',
    'System~Poprawiaj przydatność, adekwatność i skuteczność zasad, danych oraz decyzji.|Wynik energetyczny~Pokaż osiągniętą zmianę na odpowiednich wskaźnikach i bazach, w porównywalnych warunkach.|Ciągłość~Wykorzystuj kolejne źródła pomysłów, sprawdzaj efekty i utrwalaj działające rozwiązania.',
    'Wybierz możliwość~Skorzystaj z przeglądu, monitoringu, audytów, decyzji i zgłoszeń.|Zaplanuj i wykonaj~Ustal efekt, zasoby, odpowiedzialność oraz metodę sprawdzenia przed działaniem.|Sprawdź i utrwal~Oceń rezultat, wyjaśnij różnice i wprowadź sprawdzone zasady do codziennej pracy.',
    '6.3 / 7.4 / 9.1–9.3 / 10.1~Dostarczają możliwości oraz doświadczenia.|6.2 / 6.4–6.6~Plan, wskaźnik, baza i dane umożliwiają wykonanie oraz ocenę.|8.1 / 9.3 → kolejny cykl~Skuteczne rozwiązanie staje się zasadą pracy i podstawą następnych decyzji.',
    'Sytuacja przykładowa~Zainstalowano nowe oświetlenie, a roczne zużycie spadło przy mniejszej liczbie godzin pracy.|Rozwiązanie~Porównaj wynik po uwzględnieniu warunków. Oddziel wykonanie inwestycji, prognozowany potencjał i efekt potwierdzony danymi.',
    'Źródło i wybór~Skąd wzięto możliwość i dlaczego ją przyjęto.|Realizacja~Plan, właściciel, zasoby i wykonane zmiany.|Efekt~EnPI wobec EnB, warunki porównania i dowód poprawy sposobu działania systemu.',
    'Plan nazwany oszczędnością~Potencjał i zamiar wymagają potwierdzenia po realizacji.|Każdy miesiąc musi być lepszy~Oceń odpowiedni okres oraz porównywalne warunki.|Zmiana dokumentu jako cały sukces~Sprawdź także jej zastosowanie i wpływ na wynik.',
    'Jaką poprawę wyniku można wykazać danymi?|Co zmieniono w sposobie działania systemu?|Czy sprawdzone rozwiązanie utrwalono w praktyce?',
    'Tom 2, s. 159–162; HTML 10.2 z paczki 11.11.2026. Plan doskonalenia jest narzędziem metodycznym.')

deck('11-1', 'Oceń gotowość działającego systemu do zewnętrznej oceny.',
    'Praktyka~Sprawdź, czy ludzie stosują ustalenia, a system funkcjonuje w procesach organizacji.|Wynik~Przygotuj dowód osiągniętej poprawy w porównywalnych warunkach.|Cykl oceny~Wykorzystaj wyniki audytu wewnętrznego, przeglądu zarządzania i działań korygujących.',
    'Przejdź ścieżkę dowodu~Od granic i danych do wskaźnika, decyzji oraz pracy przy urządzeniu.|Oceń otwarte kwestie~Sprawdź przyczyny, działania i dostępne dowody skuteczności.|Uzgodnij przygotowanie~Z jednostką ustal zakres oceny i potrzebny okres zapisów.',
    '4–10~Wyniki całego systemu stanowią podstawę oceny gotowości.|11.1 → 11.2 / 11.3~Rozpoznane braki wpływają na wybór terminu i przygotowanie certyfikacji.|9.3 / 10.1~Brak gotowości wymaga decyzji i właściwego działania.',
    'Sytuacja przykładowa~Dokumenty są kompletne, ale nikt nie potrafi odtworzyć liczby wskazanej jako oszczędność.|Rozwiązanie~Sprawdź dane, wskaźnik, bazę i warunki. Ustal braki przed zewnętrzną oceną, zamiast potwierdzać gotowość samą liczbą dokumentów.',
    'Poprawa~Dane źródłowe, porównywalne okresy, wskaźnik i linia bazowa.|Działanie systemu~Zapisy z procesów, audytu i przeglądu oraz rozmowy z ludźmi.|Kwestie otwarte~Rzeczywisty status, przyczyny, działania i terminy sprawdzeń.',
    'Plan jako wykazany efekt~Oddziel przyszłe działania od osiągniętego wyniku.|Zamykanie kart na termin audytu~Zachowaj rzeczywisty stan i dowody.|Wyuczone odpowiedzi~Przygotuj ludzi do wyjaśnienia własnej pracy.',
    'Czy potrafimy odtworzyć wykazaną poprawę?|Czy system jest stosowany na co dzień?|Czy otwarte kwestie mają uzasadnione dalsze działania?',
    'Tom 2, s. 168–171. Punkt 11.1 jest etapem platformy; szczegóły oceny uzgadnia się z jednostką.')

deck('11-2', 'Wybierz jednostkę na podstawie zakresu oceny, kompetencji i przejrzystej oferty.',
    'Zakres~Przekaż lokalizacje, procesy, nośniki, SEU i właściwe granice systemu.|Kompetencje~Sprawdź akredytację i jej zakres oraz doświadczenie zespołu w odpowiednich procesach.|Warunki współpracy~Porównaj cały zakres usługi, terminy, koszty i zasady postępowania z ustaleniami.',
    'Przygotuj dane~Zbierz informacje o organizacji i oczekiwanym zakresie certyfikacji.|Zweryfikuj oferty~Sprawdź aktualne dokumenty jednostki we właściwym rejestrze i wymagania odbiorców certyfikatu.|Uzgodnij warunki~Potwierdź etapy, harmonogram, skład zespołu, koszty i sposób przekazywania danych.',
    '4.2 / 4.3~Wymagania odbiorców oraz zakres systemu określają potrzebę i granice certyfikacji.|11.1 → wybór~Rzeczywista gotowość pomaga ustalić wykonalny termin.|11.3 / 11.4~Uzgodnienia obejmują pierwszą ocenę i późniejsze utrzymanie certyfikacji.',
    'Sytuacja przykładowa~Dwie oferty mają różną cenę, ale jedna nie obejmuje wszystkich lokalizacji i kosztów dalszych audytów.|Rozwiązanie~Najpierw uzgodnij porównywalny zakres. Zweryfikuj warunki i kompetencje przed wyborem.',
    'Dane do oferty~Zakres, lokalizacje, procesy oraz informacje o energii.|Weryfikacja~Aktualna akredytacja, jej zakres i wymagania odbiorców.|Umowa~Zakres usługi, terminy, zasady oceny, koszty i obowiązki stron.',
    'Wybór wyłącznie po cenie~Porównaj zakres i całe uzgodnione zobowiązanie.|Logo jako dowód akredytacji~Sprawdź aktualny wpis i właściwy zakres.|Zbyt wczesny termin~Uwzględnij czas potrzebny na rzeczywiste zapisy i ocenę wyników.',
    'Czy oferta obejmuje właściwy zakres i lokalizacje?|Czy kompetencje i akredytacja zostały sprawdzone?|Czy warunki i harmonogram są jednoznaczne?',
    'Tom 2, s. 167–170, 173. Aktualny status jednostki i wymagania umowy trzeba zweryfikować przy wyborze.')

deck('11-3', 'Przygotuj organizację do oceny, pokaż rzeczywiste dowody i obsłuż ustalenia.',
    'Przygotowanie~Uzgodnij plan, osoby i dostęp do danych oraz miejsc pracy.|Ocena~Wyjaśniaj praktykę i pokazuj dowody stosowania systemu oraz wyniku energetycznego.|Dalsze postępowanie~Ustalenia wymagają właściwej reakcji; decyzję certyfikacyjną podejmuje jednostka.',
    'Etap przygotowawczy~Przedstaw zakres, system i gotowość zgodnie z uzgodnionym planem oceny.|Ocena wdrożenia~Zapewnij rozmowy, obserwację procesów i możliwość odtworzenia wyników z danych.|Reakcja na ustalenia~Zapisz korekcję, analizę przyczyny, działania i sposób wykazania skuteczności w uzgodnionych terminach.',
    '11.1 / 11.2~Gotowość i warunki współpracy wyznaczają przygotowanie.|4–10~Procesy, dane i zapisy systemu są przedmiotem oceny.|10.1 / 11.4~Ustalenia prowadzą do działań i dalszego utrzymania systemu.',
    'Sytuacja przykładowa~Podczas audytu stwierdzono nieaktualną instrukcję przy urządzeniu.|Rozwiązanie~Pokaż stan rzeczywisty, zapisz korekcję z prawdziwą datą i przeanalizuj przyczynę. Sama wymiana kartki nie usuwa historii wcześniejszego braku.',
    'Plan i dostęp~Uzgodniony harmonogram, osoby i dostępne źródła danych.|Dowody~Rzeczywiste zapisy, wyniki, rozmowy i stosowane kryteria.|Ustalenia i reakcje~Raport jednostki, odpowiedź organizacji i dowody wykonania oraz skuteczności.',
    'Dokumenty tworzone wstecz~Zachowuj rzeczywiste daty i stan pracy.|Audytor jako gwarant certyfikatu~Rozróżnij ustalenia audytu od decyzji jednostki.|Jednakowa reakcja na każdy brak~Dostosuj działania do ustalenia i uzgodnionych wymagań.',
    'Czy potrzebne osoby i zapisy są dostępne?|Czy każde twierdzenie o wyniku ma dowód?|Czy uzgodniono działania po ustaleniach?',
    'Tom 2, s. 167–172. Formę etapów, czas i terminy reakcji potwierdza jednostka certyfikująca.')

deck('11-4', 'Utrzymuj działający system między audytami i przygotowuj kolejne oceny na bieżąco.',
    'Codzienna praca~Zbieraj dane, analizuj wyniki i stosuj kryteria także po uzyskaniu certyfikatu.|Zmiany~Aktualizuj system po zmianach ludzi, urządzeń, procesów, zakresu i wymagań.|Kolejna ocena~Planuj nadzór oraz recertyfikację według aktualnych uzgodnień z jednostką.',
    'Utrzymuj rytm~Realizuj plan danych, cele, audyty, przeglądy i ocenę zobowiązań.|Reaguj na zmianę~Sprawdź wpływ na przegląd energetyczny, wskaźniki, bazy, role i instrukcje.|Przygotuj dowody~Pokaż ciągłość działania oraz osiągnięte rezultaty w odpowiednim okresie.',
    '9.1 / 9.2 / 9.3~Monitorowanie, audyt i przegląd utrzymują regularną ocenę.|10.1 / 10.2~Reakcje i doskonalenie zapewniają dalszą pracę nad systemem i wynikiem.|11.4 → następny cykl~Uzgodnione terminy zewnętrzne łączą się z codziennym planem organizacji.',
    'Sytuacja przykładowa~Po certyfikacji odchodzi koordynator, a odczyty i przeglądy przestają być wykonywane.|Rozwiązanie~Przekaż zadania i dostęp do zapisów, uruchom zastępstwo oraz sprawdź wykonanie. Ciągłość ma wynikać z procesu, nie pamięci jednej osoby.',
    'Ciągłość~Dane i analizy z kolejnych okresów oraz wykonane przeglądy.|Aktualność~Zmiany zakresu, danych, ról, kryteriów i dokumentów.|Harmonogram~Terminy ocen zewnętrznych, wymagane przygotowanie i odpowiedzialni.',
    'System uruchamiany przed wizytą~Prowadź pracę i zapisy przez cały okres.|Stare wskaźniki po zmianie procesu~Oceń ich przydatność i przesłanki rewizji bazy.|Brak przekazania po rotacji~Ustal zastępstwa oraz sprawdzenie przejętych zadań.',
    'Czy system działa między audytami?|Czy zmiany zakładu są uwzględnione w danych i zasadach?|Czy kolejne terminy oraz odpowiedzialności są uzgodnione?',
    'Tom 2, s. 167–173. Harmonogram nadzoru i recertyfikacji należy potwierdzić z właściwą jednostką.')


def overview(section, purpose, areas, route, exercise, questions, source):
    def pairs(value):
        return [dict(zip(('title', 'text'), part.strip().split('~', 1))) for part in value.split('|')]
    DECKS[section] = dict(purpose=purpose, principles=pairs(areas), dependencies=pairs(route),
        steps=pairs(exercise), questions=questions.split('|'), source=source, kind='overview')


overview('intro', 'Poznaj drogę od danych zakładu do decyzji i wykazanej poprawy wyniku energetycznego.',
    'Zakład i granice~Profil znajduje się we Wstępie do ISO. Ustal organizację, lokalizację, procesy i podstawowe dane o energii.|System zarządzania~Łączy ludzi, decyzje, procesy i dane w powtarzalny sposób pracy.|Wynik energetyczny~Oceniaj efektywność, wykorzystanie i zużycie energii z uwzględnieniem warunków działania.',
    'Poznaj i zaplanuj~Kontekst → granice → przegląd energetyczny → wskaźniki, baza i cele.|Wykonaj i sprawdź~Zasoby → działania → pomiary → analiza → audyt i przegląd.|Popraw i utrwal~Usuń przyczyny problemów, wykazuj efekty i wykorzystaj je w następnym cyklu.',
    'Sprawdź profil~Dane powinny dotyczyć właściwego zakładu, okresu i wszystkich istotnych nośników.|Oddziel brak od zera~Przy luce wskaż, czego nie wiadomo i jak pozyskać dane. Podaj źródło wartości i jednostkę.|Pracuj w obiegu platformy~Odpowiedzi, zatwierdzenia i dokumenty sprawdzaj w odpowiedniej ankiecie. Prezentacja jest pomocą szkoleniową.',
    'Czy dane opisują właściwy zakład i okres?|Kto odpowiada za decyzje i działania?|Jak porównacie osiągnięty wynik z punktem odniesienia?',
    'Tom 1, s. 19–20, 50–52; tom 2, s. 9–10. Numeracja 1–3 i 11 opisuje etapy platformy.')

overview('training', 'Wykorzystaj slajdy i filmy do zrozumienia zadań, a potem sprawdź wiedzę w praktyce.',
    'Zrozum temat~Prezentacja danego punktu wyjaśnia cel, zależności i potrzebne dowody.|Odnieś do zakładu~Przykład służy nauce. W ankiecie podawaj rzeczywiste dane i ustalenia własnej organizacji.|Sprawdź wykonanie~Obejrzenie materiału nie zastępuje oceny kompetencji ani dowodu działania systemu.',
    'Przed ankietą~Otwórz materiał odpowiedniego punktu i zbierz wskazane źródła.|W czasie pracy~Prześledź zależności z wcześniejszymi ustaleniami oraz zapisz niewiadome.|Po szkoleniu~Poproś osobę o wyjaśnienie zadania lub jego wykonanie; wynik oceny zachowaj tam, gdzie jest potrzebny.',
    'Otwórz materiał~Przy punkcie wybierz „Otwórz prezentację” albo „Duże okno”.|Przejdź slajdy~Korzystaj z przycisków poprzedni i następny. Wróć do pytań kontrolnych.|Zastosuj wiedzę~Wybierz jeden rzeczywisty proces i wskaż zadanie, odpowiedzialnego oraz dowód wykonania.',
    'Co w tym punkcie trzeba wykonać?|Które dane pochodzą z innych punktów?|Jak sprawdzimy umiejętność zastosowania wiedzy?',
    'Tom 2, s. 100–105; istniejąca przeglądarka prezentacji ENESA. Materiały nie są testem zgodności.')

overview('preparation', 'Przygotuj decyzję, zespół, dane i plan potrzebny do rozpoczęcia wdrożenia.',
    '3.1–3.2: kierunek i plan~Uzasadnij wdrożenie, rozpoznaj braki i ułóż zadania z zależnościami.|3.3–3.4: organizacja pracy~Przydziel role i czas, wykorzystaj procesy istniejących systemów.|3.5: gotowość startowa~Sprawdź warunki rozpoczęcia; każdy brak zamień w zadanie.',
    'Potrzeba biznesowa~Wymagania organizacji i dane o energii uzasadniają decyzję.|Zadania i zasoby~Zespół, harmonogram i dostęp do informacji umożliwiają start.|Praca w 4–10~Szczegółowe ustalenia powstają w punktach normy, a później są oceniane.',
    'Jedna decyzja~Wskaż, co kierownictwo już rozstrzygnęło i gdzie znajduje się zapis.|Jedna luka~Przypisz właściciela, termin i potrzebną pomoc do najważniejszego braku.|Jedna zależność~Sprawdź, które zadanie czeka na dane i co można wykonać wcześniej.',
    'Czy jest decyzja i wsparcie kierownictwa?|Czy osoby mają zadania, uprawnienia i czas?|Czy plan obejmuje pozyskanie brakujących danych?',
    'Tom 1, s. 29–30, 36–38, 43–46, 50–52. Rozdział 3 platformy jest etapem przygotowania.')

overview('context', 'Ustal, dla jakiej organizacji i w jakich granicach będzie działał system.',
    '4.1: kontekst~Rozpoznaj istotne czynniki wewnętrzne i zewnętrzne.|4.2: strony~Określ istotne strony oraz mające zastosowanie wymagania i przyjęte zobowiązania.|4.3–4.4: system~Ustal zakres, granice, procesy i sposób ich współdziałania.',
    'Profil zakładu~Fakty organizacji dostarczają podstaw do analizy kontekstu i stron.|Kontekst → zakres~Rozpoznane czynniki i wymagania wpływają na granice oraz organizację systemu.|Dalsze planowanie~Ustalenia zasilają ryzyka, przegląd energetyczny i kolejne decyzje.',
    'Wybierz czynnik~Wskaż rzeczywiste ustalenie 4.1 i jego podstawę.|Znajdź wymaganie~Pokaż, jaka strona oczekuje działania i czy zobowiązanie dotyczy organizacji.|Prześledź skutek~Wyjaśnij, jak ustalenie wpływa na granice albo proces systemu.',
    'Czy kontekst wynika z faktów zakładu?|Czy wiadomo, które wymagania obowiązują?|Czy granice i procesy są jednoznaczne?',
    'Tom 2, s. 13–35; prezentacje i materiały 4.1–4.4 ENESA. Profil pozostaje we Wstępie do ISO.')

overview('leadership', 'Połącz decyzje kierownictwa, politykę i odpowiedzialność za wykonanie pracy.',
    '5.1: przywództwo~Zaangażowanie widać w decyzjach, zasobach i codziennych procesach.|5.2: polityka~Wyznacza kierunek, zobowiązania i ramy celów energetycznych.|5.3: role~Przypisuje zadania i uprawnienia oraz zapewnia ich zakomunikowanie.',
    'Kontekst i zakres~Kierownictwo działa w ustalonych granicach i przy właściwych zobowiązaniach.|Polityka → praca~Kierunek przekłada się na role, cele i dostępne zasoby.|Ocena → decyzje~Monitorowanie oraz przegląd zarządzania pozwalają zmieniać priorytety.',
    'Wybierz zobowiązanie~Znajdź jeden zapis polityki dotyczący codziennej decyzji.|Pokaż wykonanie~Wskaż osobę, zasoby i działanie wynikające z tego zobowiązania.|Sprawdź informację zwrotną~Ustal, jak kierownictwo dowie się o wyniku lub przeszkodzie.',
    'Co kierownictwo rzeczywiście zdecydowało?|Czy polityka wpływa na cele i działania?|Czy odpowiedzialności są znane i wykonalne?',
    'Tom 2, s. 40–58; HTML 5.1–5.3 z paczki 11.11.2026. Koordynacja nie zastępuje odpowiedzialności kierownictwa.')

overview('planning', 'Zbuduj spójną podstawę do wyboru działań i późniejszego wykazania ich skutku.',
    '6.1 i 6.3: rozpoznanie~Ryzyka, szanse i przegląd energetyczny wskazują problemy oraz możliwości.|6.4–6.6: porównanie~Wskaźniki, linie bazowe i plan danych pozwalają wiarygodnie oceniać wynik.|6.2: zobowiązanie do działania~Cele, zadania i plany określają, co wykonać oraz jak sprawdzić rezultat.',
    'Najpierw podstawa~Granice i przegląd → SEU, zmienne, możliwości.|Potem miara~EnPI → EnB → dane potrzebne do porównania.|Plan i powrót~Cele korzystają z tej podstawy; wyniki realizacji prowadzą do aktualizacji planowania.',
    'Wybierz SEU~Pokaż, dlaczego ten obszar uznano za znaczący.|Połącz dane~Wskaż jego wskaźnik, bazę i źródło pomiaru.|Oceń plan~Sprawdź, jak konkretny cel wykorzystuje tę miarę oraz kto potwierdzi efekt.',
    'Czy znaczące użycia wynikają z przeglądu?|Czy wskaźnik, baza i dane pasują do siebie?|Czy metodę oceny ustalono przed działaniem?',
    'Tom 2, s. 60–95; HTML 6.1–6.6 z paczki 11.11.2026. Kolejność pracy wynika z zależności danych.')

overview('support', 'Zapewnij warunki, w których ludzie potrafią wykonać zadania systemu.',
    '7.1–7.2: możliwość wykonania~Ludzie potrzebują czasu, narzędzi i odpowiednich kompetencji.|7.3–7.4: zrozumienie i obieg~Muszą znać sens działania oraz mieć drogę przekazania informacji i propozycji.|7.5: właściwe informacje~Aktualne zasady i wiarygodne zapisy powinny być dostępne tam, gdzie są potrzebne.',
    'Zadania z planów~Role, SEU i cele określają, czego organizacja potrzebuje.|Warunki pracy~Zasoby, umiejętności, świadomość, komunikacja i dokumenty wspierają wykonanie.|Praktyka → poprawa~Rozmowy i obserwacje ujawniają, który warunek wymaga poprawy.',
    'Wybierz zadanie~Na przykład kontrolę parametru pracy urządzenia.|Zapytaj wykonawcę~Czy ma czas, narzędzie, umiejętność i właściwą instrukcję?|Sprawdź obieg~Czy rozumie wpływ zadania i wie, komu zgłosić przeszkodę?',
    'Czy zasoby odpowiadają zadaniom?|Czy ludzie potrafią i rozumieją, co robić?|Czy mają aktualne informacje i działającą drogę zgłoszenia?',
    'Tom 2, s. 97–113; HTML 7.1–7.5 z paczki 11.11.2026. Warunki pracy sprawdzaj w praktyce.')

overview('operation', 'Przenieś ustalenia energetyczne do pracy urządzeń, projektów i zakupów.',
    '8.1: eksploatacja~Kryteria pracy, utrzymania i zmian mają być stosowane przez obsługę i wykonawców.|8.2: projektowanie~Rozważ energię, kiedy rozwiązanie można jeszcze zmienić.|8.3: zakupy~Przekaż wymagania dostawcom i oceń wpływ ofert w przewidywanym okresie użytkowania.',
    'Przegląd i plany~SEU, cele i wskaźniki wskazują, co wymaga uwagi.|Projekt → zakup → praca~Przyjęty wniosek powinien trafić do specyfikacji, odbioru i kryteriów eksploatacji.|Monitorowanie~Sprawdza, czy rozwiązanie jest stosowane i daje oczekiwany rezultat.',
    'Wybierz zmianę~Prześledź jeden projekt lub zakup wpływający na energię.|Znajdź wymaganie~Pokaż drogę od analizy do zapytania i zamówienia.|Sprawdź odbiór~Czy kryteria trafiły do obsługi i czy zaplanowano pomiar efektu?',
    'Czy kryteria wynikają z potrzeb procesu?|Czy energia była oceniona przed wyborem rozwiązania?|Czy zakup i eksploatacja zachowują ustalone wymagania?',
    'Tom 2, s. 114–130; HTML 8.1–8.3 z paczki 11.11.2026. Parametry techniczne ustala się dla danego zastosowania.')

overview('performance', 'Sprawdź wynik, zgodność i działanie systemu, a następnie podejmij decyzje.',
    '9.1: dane i ocena~Analizuj wynik energetyczny, system oraz wykonanie zobowiązań.|9.2: audyt~Sprawdź wymagania i praktykę bezstronnie, na podstawie dowodów.|9.3: kierownictwo~Przełóż informacje z ocen na decyzje o dalszym działaniu.',
    'Dane i wymagania~Plan pomiarów, wskaźniki, bazy oraz rejestr 4.2.|Ocena → audyt~Monitoring pokazuje zjawiska; audyt sprawdza również sposób stosowania wymagań.|Przegląd → działanie~Kierownictwo rozstrzyga zmiany, cele, zasoby i priorytety.',
    'Wybierz wynik~Znajdź jedną zmianę wskaźnika i odtwórz ją z danych.|Sprawdź reakcję~Ustal, czy odchylenie zbadano i czy dotyczyło niespełnienia wymagania.|Znajdź decyzję~Pokaż, jak ustalenie wykorzystano w audycie, przeglądzie lub działaniu.',
    'Czy pomiary są analizowane?|Czy zgodność sprawdzono na dowodach?|Czy wyniki ocen prowadzą do decyzji?',
    'Tom 2, s. 132–154. Materiał 9.1 obejmuje monitoring i zgodność; zachowano też slajdy dla 9.1.1/9.1.2.')

overview('improvement', 'Wykorzystaj problemy i możliwości do trwałej poprawy systemu oraz wyniku.',
    '10.1: niezgodność~Rozpoznaj niespełnione wymaganie, usuń przyczynę i oceń skuteczność reakcji.|10.2: doskonalenie~Wybieraj kolejne możliwości i wykazuj osiągniętą poprawę wyniku.|Dwa rezultaty~Lepszy sposób pracy i lepszy wynik energetyczny wymagają odrębnych dowodów.',
    'Źródła wiedzy~Przegląd, monitoring, audyty, decyzje i zgłoszenia.|Działanie~Przyczyna lub możliwość → decyzja → właściciel → wykonanie.|Dowód → następny cykl~Sprawdź efekt, utrwal rozwiązanie i wróć do planowania.',
    'Wybierz zakończoną sprawę~Otwórz ostatnie działanie korygujące lub doskonalące.|Oddziel wykonanie od skutku~Znajdź dowód tego, co zrobiono, i osobno dowód rezultatu.|Sprawdź trwałość~Czy zmiana działa również przy kolejnym zdarzeniu lub innej osobie?',
    'Czy przyczyna została usunięta?|Czy wynik poprawy jest wykazany w danych?|Czy rozwiązanie utrwalono w codziennej pracy?',
    'Tom 2, s. 156–162; HTML 10.1–10.2 z paczki 11.11.2026. Plan oraz wykonanie nie zastępują dowodu efektu.')

overview('certification', 'Przygotuj zewnętrzną ocenę i utrzymuj system także po uzyskaniu certyfikatu.',
    '11.1: gotowość~Sprawdź praktykę, dane, poprawę oraz wyniki pełnego cyklu oceny.|11.2–11.3: zewnętrzna ocena~Zweryfikuj jednostkę, uzgodnij warunki i przygotuj rzeczywiste dowody.|11.4: ciągłość~Utrzymuj system, reaguj na zmiany i planuj kolejne oceny.',
    'System 4–10~Codzienna praca i wykazane rezultaty są podstawą oceny.|Jednostka certyfikująca~Uzgadnia plan, prowadzi ocenę i podejmuje decyzję certyfikacyjną.|Kolejne okresy~Działania, monitoring i doskonalenie trwają między audytami.',
    'Przygotuj krótkie wyjaśnienie~Co rzeczywiście poprawiono i na jakich danych to widać?|Sprawdź kwestie otwarte~Czy każda ma odpowiedzialnego, działanie i sposób oceny skuteczności?|Potwierdź ustalenia~Zweryfikuj zakres, harmonogram i wymagane zapisy z jednostką.',
    'Czy potrafimy wykazać działanie i poprawę?|Czy zakres oraz warunki oceny są uzgodnione?|Czy plan obejmuje pracę po certyfikacji?',
    'Tom 2, s. 166–173. Rozdział 11 jest etapem platformy, a nie dodatkowym rozdziałem wymagań ISO 50001.')
