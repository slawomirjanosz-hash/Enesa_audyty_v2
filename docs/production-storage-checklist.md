# Kontrola produkcji po przeglądzie spójności

Zmiany aplikacji nie potwierdzają konfiguracji infrastruktury Railway. Przed uznaniem kontroli produkcyjnej za zakończoną administrator powinien sprawdzić:

1. Czy `storage/app/private` i `storage/app/public` znajdują się na trwałym wolumenie, który pozostaje po ponownym wdrożeniu. Nie zmieniać punktu montowania bez przeniesienia i sprawdzenia dotychczasowych plików.
2. Czy kopie obejmują bazę oraz obydwa katalogi dokumentów. Sprawdzić odtworzenie na osobnym środowisku. Kopia base64 dokumentów generowanych ISO nie zastępuje kopii całego systemu.
3. Czy scheduler jest uruchamiany dokładnie przez jeden proces/usługę. Repozytorium definiuje termin przypomnień, ale sam serwer HTTP nie uruchamia schedulera.
4. Czy monitoring obejmuje wolne miejsce, błędy zapisu, błędy HTTP i czas odpowiedzi. Nie usuwać automatycznie plików na podstawie wieku: mogą być dokumentacją klienta.
5. Nowa migracja `2026_09_10_000003_create_document_version_counters` musi zostać wykonana przy wdrożeniu. Inicjalizuje liczniki istniejącymi wersjami, bez zmiany dokumentów. Kolejne numery nie są odzyskiwane po usunięciu pliku; luki numeracji są dopuszczalne.

Nie wprowadzono automatycznego czyszczenia dokumentów, kopii, logów ani katalogu `outputs/`. Retencję danych należy uzgodnić osobno.
