# Wydanie materiałów ISO 50001 z 18 października 2026

Źródła: `ENESA_platforma_2026-10-18.zip` i opis `Untitled.docx`, przekazane 19.10.2026.
Archiwum zawiera 28 plików HTML (profil oraz 27 punktów 4.1–10.2) i jeden XLSX.
Brakuje wymienionych w opisie oddzielnych arkuszy biblioteki stron 4.2 i specyfikacji generatora.
Wymagania 4.2 pochodzą z danych zawartych w aktualnym HTML, a nie z nieobecnego arkusza.

## Import i zgodność

`tools/import_iso_platform.py` czyta JSON oraz ograniczoną gramatykę literałów danych.
Nie wykonuje skryptów HTML, poleceń ani instrukcji zawartych w załącznikach.
`tools/import_iso_execution.py` buduje formularze 7.4–7.5 i 8–10. Identyfikatory źródła,
sumy SHA-256 oraz dane odniesienia są w `resources/iso50001/platform-2026-10-18`.
Do ponownego importu używa się katalogu bezpiecznie rozpakowanego archiwum jako argumentu obu skryptów.
Importer platformy wymaga Pythona z openpyxl; importer formularzy korzysta z biblioteki standardowej.

XLSX i HTML mają te same 70 kodów czynników. Różnią się 21 polami warunków, rodzaju,
brzmienia i skutków. `factor-workbook.json` zachowuje arkusz oraz dokładne różnice.
Aktywne pozostają skorygowane brzmienia HTML opisane w specyfikacji 4.1 wersji 2.1,
w szczególności zastrzeżenia dotyczące projektu UC77. To treści historyczne materiału,
nie automatyczne ustalenie obowiązków prawnych zakładu. Decyzję podejmuje audytor.
KTX-WO-12 pozostaje propozycją wymagającą zatwierdzenia merytorycznego.

Źródło dodało wymaganie certyfikatu odbiorcy jako WYM-25, przesuwając dalsze numery.
Platforma zachowuje dawne WYM-25–WYM-30; nowa pozycja otrzymuje WYM-31.
`requirement-ids.json` oraz `source_id` opisują mapowanie. Import nie przepisuje odpowiedzi klientów.

## Zachowanie platformy

Profil pozostaje we Wstępie ISO zgodnie z ustaleniem użytkownika, mimo wskazania 3.6 w opisie.
Nowe punkty 7.4 i 7.5 korzystają ze wspólnego obiegu klient–audytor, blokady równoczesnej edycji,
historii zmian, kontroli źródeł, importu i eksportu XLSX oraz PDF zapisywanego przy właściwym punkcie.
Dane z 5.2, 5.3, 6.2 i 7.3 wspierają komunikację; 5.3, 7.2 i 7.4 wspierają nadzór dokumentacji.
Ich zatwierdzenia nie tworzą dodatkowej sztywnej blokady, aby można było organizować procesy równolegle.

Przycisk AutoParts wczytuje formularz przez odczyt, bez zapisu i zatwierdzenia. Jest dostępny
pracownikowi z `audits.manage` oraz w podglądzie biblioteki bez audytu klienta. Serwer odrzuca
żądanie przykładu w strefie klienta. Przykład nie zastępuje profilu ani kart źródłowych rzeczywistego audytu.
Zapis i zatwierdzenie nadal przechodzą zwykłą walidację, kontrolę dostępu oraz aktualności źródeł.
Przykłady wniosków są oddzielone od odpowiedzi klienta; analizę zapisuje się osobno po jego akceptacji.
SWOT w 4.1 jest opcjonalny, ale publikacja wymaga pełnego wniosku z czterema ogniwami.
Wewnętrzna analiza 4.1 nie jest renderowana w formularzu klienta; zatwierdzony dokument pozostaje osobnym rezultatem.

Przykład ma dwie lokalizacje, choć wskazany załącznik książki opisuje jeden zakład.
Wzorcowy pomiar S-2 nie występuje w przykładzie monitorowania, a kotłownia jest poza sezonem.
System wskazuje brak pomiaru zamiast wstawiać fikcyjną liczbę; przy braku wyniku wymaga opisu oceny.
Nie utożsamia to kompletnego opisu z udowodnioną poprawą wyniku energetycznego.
