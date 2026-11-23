# Pobieranie danych firmy po NIP

Przycisk „Pobierz po NIP” nie zapisuje danych automatycznie. Użytkownik sprawdza formularz i zapisuje go zwykłym przyciskiem. Dane wcześniej zapisanych firm nie są masowo nadpisywane.

## Źródła

- Gdy ustawiono `GUS_API_KEY`, aplikacja próbuje pobrać dane z produkcyjnego GUS BIR/REGON. Korzysta ze strukturalnych pól ulicy, numeru budynku i lokalu, kodu pocztowego oraz miejscowości. Akceptuje jeden podmiot główny zgodny z podanym NIP.
- Bez klucza lub po nieudanym odczycie GUS korzysta z wykazu podatników VAT Ministerstwa Finansów. Formularz pokazuje źródło MF i prośbę o sprawdzenie adresu. Rejestr MF może zawierać niepełne dane; aplikacja nie odgaduje brakującej ulicy ani miejscowości pocztowej.
- Kod pocztowy jest przechowywany osobno. Samodzielny myślnik w miejscu numeru budynku nie jest częścią adresu; myślniki w nazwach miejscowości i ulic pozostają.

## Uruchomienie GUS

Produkcja wymaga własnego klucza. Zgodnie z [instrukcją GUS](https://api.stat.gov.pl/Home/RegonApi) wniosek należy przesłać do `regon_bir@stat.gov.pl`, podając pełną nazwę i REGON firmy, dane osoby do kontaktu (imię, nazwisko, e-mail, telefon), przybliżoną liczbę równoczesnych użytkowników oraz informacje o adresach IP opisane w instrukcji. Dla aplikacji hostowanej należy opisać architekturę serwerową i faktyczny sposób przydzielania adresów wyjściowych; domena aplikacji nie jest adresem IP jej ruchu wychodzącego. Usługa jest bezpłatna.

Po otrzymaniu klucza administrator ustawia `GUS_API_KEY` w zmiennych odpowiednich usług aplikacji na Railway i wdraża je ponownie (konfiguracja jest buforowana). Klucza nie należy wpisywać do repozytorium ani do wiadomości na czacie. Bez klucza działa MF.

Integracja używa produkcyjnego punktu końcowego BIR wskazanego w oficjalnym WSDL. Testy lokalne symulują SOAP i MTOM; rzeczywistą odpowiedź produkcyjnego GUS należy sprawdzić po podłączeniu własnego klucza. Nie stosujemy klucza demonstracyjnego ani testowych danych GUS jako danych rzeczywistych.
