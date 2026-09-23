# Profil zakładu — trwałe identyfikatory

Wersja formularza 2.0 zawiera wszystkie 85 pól audytorów z pliku
`ENESA_Profil_zakladu_ankieta-3.html` (biblioteka 1.4-projekt), w sześciu działach,
oraz pięć istniejących pól identyfikacji zakładu i kontaktu. Dwa pola są wyliczane,
więc nie wchodzą do licznika pytań wymagających odpowiedzi.

Źródło danych: `resources/iso50001/plant-profile-auditor-source-v1.4.json`.
Aktywna definicja: `resources/iso50001/plant-profile-v2.json`.
Każde `key` jest trwałą nazwą zmiennej; nie zmieniać go przy zmianie etykiety.
Kody `FAKT_*` i `ZUZYCIE_TJ`, wartości opcji oraz kolumny `c0`–`c3`
zachowują dokładnie identyfikatory źródła. Wiersze mają dodatkowy UUID `id`.

## Odpowiedzi i odwołania

Odpowiedź: `answers[KOD] = {value, unknown, detail, source, updated_by, updated_at}`.
`value`: tekst/liczba, lista wartości przy wielokrotnym wyborze albo lista wierszy.
`IsoPlantQuestionnaire::facts(definition, answers)` zwraca mapę kod → wartość
dla kolejnych ankiet. Braki, „nie wiem”, pola ukryte i „do potwierdzenia” zwracają
`null`, nigdy domyślne „nie”. Pola wyliczane są ponownie liczone na serwerze;
nie wolno ufać wartości przesłanej przez przeglądarkę.

Nie ma automatycznego nadpisywania istniejących odpowiedzi punktu 4.1.
Reguły czynników i propozycje ze źródła przechowujemy jako dane referencyjne,
nie uruchamiamy ich przy zapisie profilu ani nie wykonujemy kodu z HTML.

## Zmiana definicji istniejących profili

Najnowszy profil każdego zakładu otrzymuje definicję 2.0 bez nowego rekordu/rewizji.
Oryginalny rekord jest zapisany w zdarzeniu `schema_upgrade`. Dawne klucze kropkowe
i odpowiedzi pozostają w bazie; panel „Odpowiedzi zachowane z poprzedniego profilu”
umożliwia porównanie. Nie utożsamiamy np. zatrudnienia w zakładzie z zatrudnieniem
we wszystkich lokalizacjach ani ogólnego audytu z ustawowym audytem przedsiębiorstwa.
Zatwierdzenia są wycofane, ponieważ nowe pytania nie zostały jeszcze zaakceptowane.
PDF-y pozostają dokumentami historycznymi. Starsze rewizje nie są modyfikowane.

## Obliczenia

`ZUZYCIE_TJ`: suma ilość × GJ/jednostkę / 1000 ze wszystkich wierszy FAKT_NOSNIKI.
Brak przelicznika lub niekompletny wiersz oznacza brak pełnego wyniku, nie sumę częściową.
Współczynniki pochodzą z makiety audytorów, są orientacyjne, a nie oceną prawną.
`FAKT_CIEPLO_PALIWA`: obliczane z FAKT_KOTLOWNIA i FAKT_CIEPLO_PALIWO;
nieaktywne paliwa historyczne nie wpływają na wynik przy cieple tylko z sieci.
Zatwierdzanie i historia korekt klient–audytor pozostają bez zmian.
