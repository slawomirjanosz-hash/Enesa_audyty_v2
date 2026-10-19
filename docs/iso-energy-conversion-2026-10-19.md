# Korekta przeliczania energii — 19.10.2026

Źródło: `ENESA_platforma_2026-10-18.zip`, profil 1.4 i specyfikacja dla Sławka.
Oryginalne dokumenty HTML pozostają niezmiennymi kopiami materiału. Korekta funkcji
obliczeniowych działa w adapterze, przy zachowaniu źródłowych zakładek i pól.

## Błędy i korekty

- LPG oraz część innych paliw nie obsługiwały kWh/MWh/GJ, mimo że formularz je oferował.
  Jednostki energii są teraz wspólne dla wszystkich znanych nośników. Nie dodano
  żadnych współczynników masy/objętości; dotychczasowe orientacyjne wartości pozostają.
- HTML sumował wyłącznie poprawne wiersze, a backend odrzucał sumę częściową.
  Wspólny kalkulator przeglądarkowy i PHP zwracają null przy niekompletnym lub
  nieobsługiwanym wierszu. Komunikat wskazuje numer, nośnik i jednostkę. Serwer blokuje
  zapis błędnej kombinacji. Całkowicie pusty wiersz jest pomijany, wiersz częściowy nie.
- HTML zaokrąglał wynik do 2 miejsc, backend do 6. Obie strony używają teraz 15 cyfr
  znaczących, aby np. 85,0000001 TJ nie stało się 85 TJ wskutek zaokrąglenia.
- Ilości z przecinkiem/spacjami są normalizowane w backendzie również dla tabel
  i Excela. Zero pozostaje odpowiedzią; puste, błędne i nieznane dane nie są zerem.
- Adapter przy ponownym otwarciu tabeli przekazuje liczby w formacie poprawnym dla
  input number, w tym zero. Dane demo nie zastępują faktycznego profilu w 4.1.
- Źródłowy HTML potrafił użyć ukrytego starego paliwa po przejściu na ciepło sieciowe.
  Teraz stosuje aktualne źródło ciepła tak samo jak backend; brak paliwa i dane
  oznaczone jako nieznane nie są potwierdzeniem braku paliw kopalnych.
- Excel eksportuje wynik obliczony z bieżących odpowiedzi, na kopii modelu, bez zapisu
  do bazy. Import zachowuje mapowanie i przelicza dane zwykłą normalizacją.
- Renderery 4.1 i 4.2 ponownie liczyły częściową sumę nośników do 2 miejsc,
  zastępując aktualny wynik profilu. Warunki w rzeczywistym audycie korzystają teraz
  z faktów backendu, również gdy wynik jest null. Podgląd używa wspólnego kalkulatora.
- 4.1 wskazuje, których czynników nie można ocenić wskutek brakujących zależności.
  Warunki prawne/merytoryczne biblioteki nie zostały zmienione. KTX-ZR-01: >85 TJ;
  KTX-ZR-02: >=10 i <=85 TJ; KTX-ZR-03–07 mają niezależne odpowiedzi profilu.

## Istniejące profile i dokumenty

Nie ma migracji danych, masowego zapisu, unieważnienia zatwierdzeń w bazie ani
regenerowania dokumentów. Odczyt faktów przelicza wartości już wcześniej; po poprawce
odczyt używa naprawionego kalkulatora. Może zatem pokazać poprawny wynik i wykryć
nieaktualny hash punktu zależnego bez modyfikacji jego rekordu.

`php artisan iso:check-energy` wykonuje wyłącznie SELECT i wypisuje JSON: identyfikatory
profilu/audytu/lokalizacji, wersję, czy profil jest najnowszy, wynik zapisany i obliczony,
błędy wierszy oraz obecność zatwierdzeń i dokumentów. Polecenie nie naprawia rekordów.

Gdy wartość zapisana różni się od obliczonej, formularz pokazuje komunikat. Najnowszy
profil wymaga sprawdzenia, jawnego zapisu i zwykłego obiegu zatwierdzeń. Zatwierdzenie
przez audytora oraz pobranie/generowanie aktualnego PDF z profilu są blokowane do
uzgodnienia wyniku z zatwierdzonym zapisem. Istniejący PDF pozostaje w dokumentacji
jako wcześniejszy dokument; nie jest podmieniany. Profile historyczne pozostają historią.
Po korekcie audytor sprawdza zależne ankiety i aktualność opracowanych dokumentów.

Audytor nadal potwierdza wartości opałowe dla masy/objętości i merytoryczną kwalifikację
obowiązków. Przelicznik jednostek energii nie rozstrzyga sam obowiązku prawnego ani
wymaganego okresu referencyjnego (tabela profilu opisuje jeden rok).
