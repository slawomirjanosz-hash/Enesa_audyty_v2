# Finanse i dokumenty audytów

Finanse audytu obsługują ręczne koszty i faktury, dostawców, grupy, terminy płatności, statusy, operacje zbiorcze oraz import XLSX/XLS/CSV z wykrywaniem duplikatów. Podsumowania rozdzielają pozycje planowane od wystawionych/opłaconych. Wykres cash flow oferuje te same tryby grupowania i zakresów co projekty. W audytach nie ma automatycznych kosztów materiałów projektu.

Zapis wymaga uprawnienia audits.manage oraz dostępu do firmy audytu. Powiązanie wpisu i grupy jest sprawdzane po stronie serwera. Klient nie otrzymuje danych finansowych.

## Dokumenty

Foldery audytu są widoczne wraz z plikami w strefie klienta. Zarządzający audytem może utworzyć lub usunąć pusty folder, przesuwać pliki i wysłać do 20 plików naraz, po maksymalnie 20 MB. Obowiązuje istniejący limit miejsca użytkownika. Stare dokumenty pozostają w sekcji bez folderu.

Zakładki Dokumenty audytów i projektów mają również nazwane linki do zewnętrznych dysków. Akceptowane są adresy HTTPS bez loginu i hasła. Aplikacja nie pobiera zawartości linku, nie przechowuje haseł i nie zastępuje logowania do Google/Microsoft/innego dostawcy. Link otwiera nową kartę bez udostępniania referera. Usunięcie linku nie usuwa plików u dostawcy.

## Harmonogram dla klienta

Przycisk „Link dla klienta” generuje trudny do odgadnięcia link wyłącznie do podglądu harmonogramu. Można go skopiować i wysłać dowolnym kanałem. Dostęp ma każdy posiadacz linku, bez logowania; widzi nazwę audytu, terminy, zadania i osoby, ale nie prywatne opisy zadań, dokumenty ani finanse. Przycisk „Wyłącz link” natychmiast unieważnia dostęp. Nowy link po wyłączeniu ma inny token. Usunięty audyt nie jest dostępny przez stary link.

## Weryfikacja

AuditExtendedWorkspaceTest pokrywa rozdzielenie audytów, grupy finansowe, import i duplikaty, foldery, przenoszenie plików, ograniczenia klientów, adresy dysków, link publiczny i unieważnienie. Tabele używają wspólnego table-sort.js; nie sortują kolumn akcji i zaznaczania.
