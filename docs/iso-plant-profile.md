# Profil zakładu (Wstęp do ISO)

Wejście: audyt ISO 50001 → Wstęp → Otwórz profil zakładu. Moduł korzysta z istniejącego włącznika audytów. Nie modyfikuje ankiet 4.1–4.2 ani ich danych.

## Dane

`resources/iso50001/plant-profile-v1.json` zawiera wzór 1.0: osiem części i 65 pytań ze stałymi kluczami. Definicja zostaje skopiowana do profilu, więc zmiany wzoru nie zmieniają dawnych odpowiedzi i PDF. Zmiana znaczenia pytania wymaga nowej wersji wzoru/migracji; kluczy nie należy wykorzystywać ponownie dla innych znaczeń.

`iso_plant_sites` identyfikuje zakłady firmy. `iso_plant_profiles` przechowuje wersje profilu zakładu w audycie. `answers` to mapa kluczy (np. `energy.carriers`) do wartości, statusu niewiedzy, uzupełnienia, źródła, autora i czasu zmiany. Klucze z kropkami są dosłowne: odczyt `$profile->answers['energy.carriers']`, nie `data_get` z tą ścieżką. Rekordy budynków i energii mają UUID. Ilości energii nie są automatycznie sumowane ani przeliczane: zachowują nośnik, jednostkę, okres i granicę pomiaru.

Zatwierdzone dane można wykorzystać w następnych punktach przez odwołanie do konkretnego `profile_id` i `revision`. Nie należy nadpisywać wygenerowanych analiz przy zmianie profilu. Na razie nie ma automatycznego mapowania do 4.1. Przy tworzeniu profilu istniejącego zakładu w nowym audycie można jawnie skopiować ostatnią zatwierdzoną wersję; daty i okresy są zachowane, zatwierdzenia nie.

## Uprawnienia i obieg

- Członek firmy w strefie klienta (`client_user` lub `client_admin`) może wypełniać profil. Administrator klienta (`client_admin`) zatwierdza go w imieniu firmy.
- Pracownik musi mieć dostęp do audytu i `audits.manage`, aby edytować, zwracać lub zatwierdzać. Obowiązuje istniejące ograniczenie firm dla delegowanych audytorów; `audits.view` daje podgląd.
- Osoba zatwierdzająca jako audytor musi być inna niż osoba zatwierdzająca jako klient.
- `editing/returned` → zapis lub zatwierdzenie klienta → `submitted` → zatwierdzenie audytora (`approved`) albo zwrot do uzupełnienia. Wycofanie/zwrot usuwa bieżącą akceptację klienta, ale pozostawia ją w historii zdarzeń.
- Zatwierdzona treść jest nieedytowalna. „Utwórz nową wersję” tworzy kolejny rekord bez akceptacji i PDF. Poprzedni nie jest nadpisywany.
- Każda operacja ma blokadę transakcyjną i numer `lock_version`, chroniący przed zapisem starego formularza. Migawki kolejnych zapisów znajdują się w `iso_plant_events`, a ślad operacji także w istniejącej historii aktywności.

## PDF

Wymaga obu akceptacji. Podgląd nie zapisuje dokumentu. „Generuj i zapisz PDF” tworzy `IsoSectionDocument` w sekcji `intro`, scope `client`; ponowne kliknięcie nie tworzy duplikatu. Wykorzystuje zatwierdzoną definicję/odpowiedzi, zapamiętane nazwiska zatwierdzających oraz logo i nazwę operatora z chwili akceptacji audytora. Plik jest przechowywany w chronionej bazie (`content_base64`), co jest obsługiwane przez istniejące pobieranie dokumentów; nie powstaje publiczny plik. Obowiązuje limit miejsca użytkownika generującego dokument oraz historia pobrań. Usunięcie dokumentu przez istniejący uprawniony mechanizm nie usuwa profilu — można ponownie wygenerować PDF.

## Weryfikacja

`php artisan test --filter=IsoPlantProfileTest`

Sprawdzenia obejmują odseparowanie firm/audytów/zakładów, role zatwierdzających, konflikt wersji, walidację katalogu i pomiarów, obieg zatwierdzeń, blokadę przedwczesnego eksportu, historię, PDF oraz brak duplikatów. Formularz używa `public/js/table-sort.js` dla tabel historii i profili. Wzór jest autorską ankietą danych wejściowych, nie zastępuje pełnej oceny spełnienia normy.
