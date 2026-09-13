# Prezentacje szkoleniowe ISO 50001

Pod filmem w punkcie 4.1 dostępna jest biblioteka prezentacji. Tak jak filmy, jej zawartość jest wspólnym materiałem wzorcowym widocznym klientom z przypisanym audytem ISO 50001. Nie wgrywać tutaj poufnej dokumentacji konkretnego klienta.

Zarządzanie wymaga tych samych uprawnień co filmy: `audits.types.manage` i pełnego dostępu do biblioteki. Można dodawać, edytować tytuł/opis, podmieniać PPTX i usuwać prezentację. Podmiana jest atomowa: nieudana konwersja nie usuwa dotychczasowych slajdów. Usunięcie usuwa podglądy z aktywnej bazy (kopie zapasowe podlegają osobnej retencji).

Odtwarzacz pokazuje statyczne obrazy, poprzedni/następny, licznik i małe/duże okno. Strzałki klawiatury zmieniają slajd, Escape zamyka okno. Nie ma pobierania PPTX/PDF ani publicznego adresu źródłowego pliku. Oryginał jest używany tylko do konwersji i usuwany z katalogu roboczego; należy zachować go lokalnie. Notatki prowadzącego i animacje nie są udostępniane. Ochrona nie blokuje zrzutów ekranu ani zapisania obrazu przez uprawnioną osobę.

Slajdy są przechowywane w bazie, więc nie giną przy wymianie kontenera Railway. Listy wczytują tylko metadane, odtwarzacz pobiera pojedynczy obraz. Każde żądanie sprawdza zalogowanie, firmę klienta i przypisanie ISO 50001 do wskazanego audytu. Prezentacja przykładowa (16 slajdów z dostarczonego PPTX) jest dodawana jednorazowo migracją. Jej usunięcie nie powoduje odtworzenia przy kolejnych wdrożeniach.

## Konwersja nowych plików

Nixpacks instaluje LibreOffice Impress, Poppler i podstawowe fonty (w tym Carlito zastępujący Calibri). Dla innego sposobu budowania serwera potrzebne są te same pakiety. Zmienne `PRESENTATION_OFFICE_BINARY` i `PRESENTATION_RASTER_BINARY` wskazują odpowiednio domyślnie `/usr/bin/libreoffice` i `/usr/bin/pdftoppm`. Bez konwertera aplikacja wyświetla komunikat, a nie fałszywe potwierdzenie wgrania. Pierwszy build może trwać dłużej ze względu na instalację pakietów.

Dozwolony jest PPTX do 20 MiB i 60 slajdów, do 100 MiB po rozpakowaniu. Brak obsługi PPT, PPTM, makr, obiektów OLE, zewnętrznych linków, filmów i aktywnej zawartości. Konwersja korzysta z izolowanego katalogu UUID i profilu, wyłączonych makr oraz limitów czasu. Tylko jedna konwersja jednocześnie (współdzielony cache database/redis), limit 3 operacji/minutę. Nie jest to pełny sandbox procesowy ani skaner antywirusowy: aktualizować pakiety systemowe, a dostęp do uploadu dawać wyłącznie zaufanym administratorom biblioteki. Dla większego ruchu przenieść konwersję do izolowanego workera zamiast żądania HTTP.

Podglądy wymagają miejsca w bazie i w backupach. Nie są dokumentami klienta i nie obciążają jego limitu 200 MB. Typowe slajdy zachowują układ, ale brak nietypowej czcionki na serwerze może zmienić wygląd. Warto sprawdzić wynik po każdym uploadzie.
