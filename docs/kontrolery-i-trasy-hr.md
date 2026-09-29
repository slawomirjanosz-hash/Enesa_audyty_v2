# Podział kontrolerów i wyliczanie tras HR

## Podział odpowiedzialności

Zachowano adresy, nazwy tras, middleware, kontrolę dostępu do rekordów i formaty odpowiedzi.
Podział nie wymaga migracji bazy danych.

- HrController: główny widok HR i ustawienia stawek.
- HrTripController: dodawanie, edycja, usuwanie, podgląd i PDF delegacji oraz wyliczenie rozliczenia.
- HrLeaveController: nieobecności i ich wydruki.
- HrAttendanceController: obecności.
- HrVehicleController: pojazdy.
- HrRouteController: walidacja zapytań o trasę i podpowiedzi adresów.
- GoogleMapsService: komunikacja z Google; HrAccess: wspólne reguły dostępu HR.
- ProjectController: lista, karta, tworzenie, kopiowanie, edycja i usuwanie projektu.
- ProjectScheduleController: harmonogram, zadania, import/eksport i publiczny Gantt.
- ProjectFinanceController: finanse, grupy i import.
- ProjectRequirementController: materiały/usługi i ich import/eksport.
- ProjectDocumentController: pliki projektu.

Pozostałych kontrolerów i dużych szablonów Blade jeszcze nie dzielono.
Refaktoryzacja nie zmienia sposobu pobierania danych karty projektu ani wydajności jej zapytań.

## Diagnoza Google Maps — 29.09.2026

Test z serwera produkcyjnego ENESA: klucz jest obecny, lecz Routes API zwraca HTTP 403 / PERMISSION_DENIED
(„The caller does not have permission”). Próbne zapytanie starszego Directions API również zwraca REQUEST_DENIED.
Nie ma dowodu pozwalającego odróżnić wyłączoną usługę, ograniczenia klucza i problem z rozliczeniami bez dostępu do Google Cloud.
Nie zmieniano kluczy, uprawnień ani rozliczeń Google.

Administrator Google Cloud powinien sprawdzić:

1. Czy właściwy projekt ma aktywne Routes API oraz wymagane rozliczenia.
2. Czy klucz używany przez GOOGLE_MAPS_API_KEY dopuszcza Routes API.
3. Czy ograniczenia aplikacyjne klucza odpowiadają zapytaniom z serwera Railway, a nie wyłącznie przeglądarki.
4. Osobno Places API (New), jeśli potrzebne są podpowiedzi adresów.

Nie należy usuwać wszystkich ograniczeń klucza ani publikować go w repozytorium lub przeglądarce.
Po zmianie zmiennej na Railway potrzebne jest wdrożenie/odświeżenie konfiguracji Laravel.

Dokumentacja Google:
- https://developers.google.com/maps/documentation/routes/get-api-key
- https://developers.google.com/maps/api-security-best-practices

## Zachowanie formularza

Usługa zwraca długość i czas w jedną stronę. Formularz, jak dotąd, przyjmuje dwukrotność kilometrów
oraz ten sam szacowany czas powrotu, zaokrąglony do kwadransa (minimum kwadrans).
Nie jest to osobna kalkulacja drogi powrotnej ani prognoza ruchu; użytkownik może poprawić dane.

Przy błędzie konfiguracji, limicie, awarii lub niepoprawnej odpowiedzi API formularz podaje komunikat
i zachowuje ręcznie wpisane wartości. Spóźniona odpowiedź nie nadpisuje nowej delegacji ani zmienionej trasy.
Żądania formularza oczekują JSON; wygaśnięcie sesji ma osobny komunikat.
Logi zawierają wyłącznie kod HTTP błędu dostawcy, bez klucza, adresów czy surowej odpowiedzi Google.
