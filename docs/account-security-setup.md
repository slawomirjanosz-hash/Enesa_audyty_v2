# Uruchomienie zabezpieczeń kont i dokumentów

## Wdrożenie

Wykonać migracje. Istniejące konta dostają początek pomiaru nieaktywności w chwili migracji, bez natychmiastowej blokady na podstawie starych danych. Codzienny scheduler uruchamia `accounts:block-inactive`; ta sama kontrola działa przy logowaniu i żądaniach zalogowanego użytkownika. Wymagany działający scheduler w Railway.

Konto blokuje brak aktywności przez dwa miesiące kalendarzowe. Blokada prób haseł: 10 błędów w ciągu 15 minut, również z różnych IP; trwa 15 minut. Admin/superadmin odblokowuje w Ustawienia → Użytkownicy. Admin nie może odblokować superadmina. Gdy brak innego superadmina, uprawniony operator hostingu może użyć `php artisan accounts:unlock EMAIL --force` (zapis w historii). Nie wyłączać zabezpieczeń przez edycję hasła w bazie.

Limit wynosi 200 MiB (w UI MB) na użytkownika: pliki Document i IsoSectionDocument, wraz z ich wersjami, liczone po rozmiarze logicznym, nie po rozmiarze kopii base64. Pliki historyczne powyżej limitu pozostają dostępne. Starsze pliki bez autora są wspólne; nie zgadujemy ich właściciela. Publiczne nowe uploady obciążają twórcę linku. Limit zmienia wyłącznie admin/superadmin. Baza i prywatny dysk nadal wymagają osobnych backupów.

## SMS superadmina — wymaga uruchomienia przez operatora

Integracja przygotowana dla SMSAPI, bez zakładania konta ani zakupu usługi. W Railway wpisać tajne zmienne (nie w repozytorium):

- `SMSAPI_TOKEN`: token konta z prawem wysyłania SMS.
- `SMSAPI_SENDER`: zaakceptowana nazwa nadawcy, jeżeli konto jej wymaga.
- `SUPERADMIN_SMS_PHONE`: numer wskazany przez właściciela aplikacji, w formacie międzynarodowym z prefiksem 48. Nie pobieramy numeru z edytowalnego profilu.
- `SUPERADMIN_SMS_ENABLED=true`: włączyć dopiero po sprawdzeniu integracji na środowisku testowym i przygotowaniu dostępu awaryjnego operatora.

Domyślnie integracja wyłączona, aby wdrożenie bez kluczy nie odcięło dostępu. Po włączeniu bez potwierdzenia SMS nie można korzystać z aplikacji jako superadmin, również przez stare sesje i „zapamiętaj mnie”. Brak SMS lub awaria dostawcy nie omija weryfikacji. Kody ważne 5 minut, jednorazowe, maks. 5 prób; wysyłka maks. 1/min i 5/h na konto. Wymagany współdzielony cache z blokadami (database/redis), nie cache array na produkcji. Wysyłka kosztuje według umowy z dostawcą. Jedna skonfigurowana destynacja dotyczy wszystkich superadminów tego wdrożenia.

## Turnstile — konfiguracja zewnętrzna

- Utworzyć widget Managed dla właściwej domeny.
- `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, `TURNSTILE_HOSTNAME` (dokładna domena aplikacji bez protokołu).
- Dopiero po konfiguracji ustawić `TURNSTILE_ENABLED=true`.

Widget pojawia się po zwiększonej liczbie prób z adresu IP, nie przy każdym zwykłym logowaniu. Serwer weryfikuje token, domenę i action=login. W razie awarii weryfikacja wymagana przez serwer nie jest pomijana. Współdzielone sieci mogą powodować wyświetlenie wyzwania innym osobom za tym samym IP. Bez konfiguracji działa ograniczanie prób haseł, ale nie zewnętrzna weryfikacja antybotowa.

## Historia i prywatność

Udane odpowiedzi pobierania/podglądu dokumentów są zapisywane w Liście zmian. Jest to zapis udostępnienia odpowiedzi, nie dowód zakończenia transferu. Dla linków anonimowych zapisujemy identyfikator udostępnienia i IP, nie deklarujemy zweryfikowanej tożsamości odbiorcy. Nowe logi przechowują wzorzec trasy zamiast pełnego URL z podpisem i tokenami; istniejące stare wpisy nie są automatycznie modyfikowane.

Nie przeniesiono danych do zewnętrznych usług skanowania. Nie skonfigurowano ani nie potwierdzono infrastrukturalnych kopii zapasowych, szyfrowania dysków czy antywirusa.
