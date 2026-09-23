# Ankieta czynników 4.1

Źródło: `ENESA_Ankieta_czynnikow_4.1.html`, schemat 1.4, 69 czynników / 7 działów. Oryginalne dane zapisano w `resources/iso50001/factors-v1.4.json`. Przykład ze źródła nie jest importowany jako odpowiedzi klienta.

## Stabilne identyfikatory

- Kody `KTX-*` pozostają bez zmian; odpowiedzi: `answers.factors[KTX-*].decyzja`, `.tresc`, `.powod`.
- Fakty `FAKT_*` i `ZUZYCIE_TJ` czytane są z aktualnego profilu danego zakładu przez `IsoPlantQuestionnaire::facts()`. Nie powstaje druga edytowalna kopia faktów w ankiecie 4.1.
- `FAKT_KLIMAT_ISTOTNY` nie występuje w przekazanym profilu. Jest lokalną oceną w 4.1, z obowiązkowym uzasadnieniem `climate_reason`; nie zmienia zatwierdzonego profilu.
- Własne czynniki mają stabilne UUID, niezależne od kolejności wierszy. Nie wolno odwoływać się do numeru wiersza.

## Zależności i obieg

Jedna ankieta na audyt i zakład (`audit_id`, `site_id`), również po korektach. Wymagany jest aktualny profil zatwierdzony przez klienta i audytora. Dla każdego czynnika przechowywany jest skrót wartości jego zależności. Zmiana profilu wymusza ponowny obieg zatwierdzeń, ale kasuje decyzje wyłącznie zależnych czynników. Pozostałe decyzje i własne treści zostają zachowane. Odczyt strony nie zapisuje danych.

Pierwszy zapis klienta nie oznacza korekt ani przekazania do audytora. Przekazanie wymaga zatwierdzenia przez administratora klienta. Zapis zmiany przez audytora cofa oba zatwierdzenia i oznacza zmienione pola. Poprawki klienta widzi audytor; niezmieniający danych zapis audytora zachowuje zatwierdzenie klienta. Ostatecznie potrzebne są dwa zatwierdzenia tej samej treści przez różne osoby. `lock_version` oraz skrót aktualnego profilu chronią przed nadpisaniem z nieaktualnego okna.

PDF powstaje wyłącznie przy aktualnych dwóch zatwierdzeniach i niezmienionym, zatwierdzonym profilu. Trafia do `iso_section_documents`, punkt `4-1`, dokumentacja klienta, nazwa `4.1 Kontekst organizacji Czynniki.pdf`. Poprzednie dokumenty pozostają historyczne. Zdarzenia i migawki trafiają do `iso_factor_events`, operacje także do ogólnej historii zmian.

## Jawne odstępstwa bezpieczeństwa merytorycznego

Warunki są interpretowane ograniczonym parserem, nigdy przez `eval` ani kod dostarczonego HTML. Nieznany fakt nie jest odpowiedzią „nie”.

Źródłowe AUTO dla `KTX-ZR-01`, `KTX-ZR-02`, `KTX-ZR-11` oraz `KTX-WO-03` są w interfejsie propozycjami do potwierdzenia/odrzucenia i przeglądu audytora: pojedyncza wartość zużycia nie przesądza obowiązku prawnego, a brak certyfikacji nie przesądza braku procesów zarządzania. Oryginalny schemat pozostaje nienaruszony. `KTX-ZK-02` wykorzystuje rzeczywiste uzasadnienie oceny klimatu zamiast gotowego, niepotwierdzonego uzasadnienia ze wzorca.

Stare dane oraz trasy połączonej ankiety 4.1–4.2 pozostają zachowane; nowy formularz nie migruje ich automatycznie według domniemanych znaczeń pól.
