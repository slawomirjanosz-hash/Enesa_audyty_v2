# Plan finansowy działu

Moduł obok Projektów. Plan obejmuje wybrany zakres miesięcy, także na przełomie lat, np. 11.2027–02.2028. Nagłówek, kolumny i sumy pokazują ten zakres. Zakres planu można zmienić bez ponownego dodawania źródeł. Daty faktur, dostaw i okresy wynagrodzeń pozostają zachowane. Plan jest bieżącą prognozą i nie wymaga zatwierdzania.

## Obsługa

1. Włącz moduł „Plan finansowy działu” w ustawieniach firmy.
2. Nadaj jawnie uprawnienie financial_plans.view lub financial_plans.manage i przypisz osobę do planowania działu. Skład działu nie daje dostępu do wynagrodzeń. Superadmin ma dostęp administracyjny; system.full_access nie daje dostępu do wynagrodzeń.
3. Utwórz dział i wybierz miesiąc początkowy i końcowy planu. Ponowne wybranie tych samych miesięcy otwiera istniejący plan.
4. Dodaj projekty, oferty i koszty zespołu. Nazwy osób, projektów i ofert są automatyczne; opis jest opcjonalny.

## Bieżące wyliczenia i czytelność

- Otwarta karta odświeża wyliczenia co 5 sekund oraz po powrocie do niej; przycisk „Odśwież teraz” uruchamia odczyt od razu. Nieaktywna karta nie wysyła cyklicznych zapytań.
- Endpoint za każdym razem przelicza źródła i kontroluje zakres działu, projektów oraz cen. Odpowiedzi mają prywatny zakaz buforowania. Niezmienione dane są rozpoznawane po skrócie zawartości.
- Aktualizowane są sumy, prognoza, wykonanie, harmonogram płatności, źródła i wykresy. Wpisywany formularz, sortowanie tabel, pozycje przewijania i rozwinięte szczegóły pozostają zachowane. Zmiana rewizji w innej karcie wymaga odświeżenia formularza przed zapisem, aby nie nadpisać cudzych zmian.
- Znaki zapytania i podkreślone kwoty pokazują chmurki: wzory oraz udział konkretnych projektów/osób w miesiącu. Kwoty źródłowe projektu pokazują faktury i dostawy wraz z datami i oznaczeniem pozycji spoza okresu. Chmurki działają po najechaniu, kliknięciu i uzyskaniu fokusu z klawiatury.
- Uwagi w źródłach są krótkim znacznikiem z liczbą. Pełne opisy są w chmurce ponad tabelą, bez rozciągania wierszy.
- Wykresy pod prognozą obejmują wynagrodzenia, samochody i pozostałe koszty utrzymania działu. Słupki pokazują miesiące i składniki, linia ich sumę narastająco. Koszty realizacji projektów są wyłączone. Wartości można sprawdzić w chmurkach i sortowalnej tabeli wykresu.

## Projekty

- Projekt nie wymaga okresu pozycji ani ręcznej kwoty przychodu/kosztu. Przychód to wyłącznie faktury planowane, wystawione i opłacone według daty dokumentu. Brakująca część wartości umowy nie jest rozkładana na miesiące; harmonogram uzupełnia się w finansach projektu.
- Koszty pochodzą z zapisów finansowych oraz planowanych i realizowanych materiałów/usług (dostaw), według daty dokumentu lub terminu dostawy. Koszt dostawy ujęty w finansach jest liczony tylko raz. Anulowane dostawy są pomijane.
- Dostawa bez terminu wymaga uzupełnienia daty w projekcie; jej koszt nie jest rozkładany automatycznie. Zrealizowana dostawa bez zapisu finansowego i terminu korzysta z daty ostatniej aktualizacji jako dostępnej daty realizacji.
- Brak wyceny dostawy jest oznaczany jako uwaga. Jawne zero w źródle jest dopuszczalne. Brak jakichkolwiek kosztów projektu wymaga uzupełnienia źródła.
- Zmiana kwot i dat w projekcie aktualizuje prognozę. Dawne ręczne korekty projektów i rezerwy nie są już używane w prognozie; zatwierdzone wcześniej bazowe plany pozostają zachowane.
- Wykonanie obejmuje faktury/koszty wystawione lub opłacone; planowane zapisy nie są wykonaniem.

## Wykres wyniku i ofert

Wykres rzeczywistego konta i formularz salda usunięto z widoku planu. Historyczne dane płatności zachowano. Wykres zysku i straty pokazuje wynik bez ofert, prognozę z ofertami ważoną scenariuszem oraz pełną wartość ujętych ofert (100%), wszystkie narastająco. Wartość ofert jest przychodem, nie zyskiem. Każdy wykres można niezależnie zwinąć, a wartości sprawdzić w chmurkach i sortowanej tabeli.

## Harmonogram płatności netto

Przepływy projektowe korzystają z terminu płatności. Jeśli go brakuje, przyjmują datę dokumentu + 30 dni kalendarzowych, zarówno dla faktur klienta, jak i kosztów. To założenie budżetowe: nie zapisuje terminu w projekcie. Płatność może wypaść poza wybranym okresem, nawet gdy dokument jest w tym okresie. Kwoty netto; widok nie obejmuje VAT, salda rachunku, kosztów wydziałowych ani dostaw bez zapisu finansowego. Oferty z harmonogramem dodają raty wpływów i kosztów według dat płatności, z uwzględnieniem scenariusza.

## Pozostałe pozycje

- Wynik działu = przychody − bezpośrednie koszty − wynagrodzenia − samochody − pozostałe koszty działu. Wszystkie kwoty w PLN; projekty netto, wynagrodzenia jako pełny koszt firmy.
- Koszty osób, samochodów i pozostałe koszty działu są miesięczne, za całe wskazane miesiące. Ich okresy pozostają potrzebne do zmian zatrudnienia, wynagrodzeń i samochodów. Brak pokrycia kosztu pracownika w części okresu jest sygnalizowany.
- Oferty mają harmonogram wpływów i kosztów netto: dowolna liczba rat z datami płatności. Wpływy muszą sumować się do aktualnej wartości oferty lub ręcznego przychodu, koszty do podanej kwoty (puste pole kosztu jest uzupełniane sumą harmonogramu). Koszt zerowy wymaga jawnego wpisu 0. Harmonogram jest szyfrowany. Daty poza okresem pozostają zapisane i wchodzą do sum po rozszerzeniu planu. Zmiana wartości źródłowej oferty sygnalizuje potrzebę aktualizacji harmonogramu. Starsze oferty bez harmonogramu i ręczna sprzedaż zachowują równy rozkład na okres realizacji; oferta bez harmonogramu nie jest liczona w przepływach i daje uwagę. Scenariusz ostrożny: potwierdzone oferty; bazowy: także realne ważone szansą; optymistyczny: także rezerwowe w pełnej wartości. Przegrane i wykluczone oferty są pomijane. Projekt zastępuje wskazaną ofertę.
- W tym samym planie źródło można przypisać raz. Nakładające się okresy różnych działów chronią przed podwójnym przypisaniem źródeł i kosztów tej samej osoby, także na przełomie lat. Różne plany tego samego działu są osobnymi wariantami i nie są sumowane ze sobą.

## Poufność i zgodność

Kwoty ręczne, wynagrodzenia, plan bazowy i historia są szyfrowane. Dostęp do projektów, ofert i cen zachowuje istniejące ograniczenia. Niepełny dostęp oznacza częściowe sumy. Historyczne decyzje zachowują ochronę źródeł po usunięciu pozycji. Dawne zaszyfrowane zatwierdzenia są zachowane w bazie, ale etap zatwierdzania i porównanie z planem bazowym usunięto z aplikacji. Rewizja chroni przed równoczesnym nadpisaniem. Dotychczasowe roczne plany są zachowane jako zakres styczeń–grudzień, bez zmiany zatwierdzonych wartości.

## Kafelki i aktywność pozycji

Kafelki pokazują sumy netto dla wybranego okresu: wystawione i opłacone faktury klienta oraz koszty projektowe (także planowane dostawy, bez ofert i kosztów działu). Ikona oka w źródłach włącza albo wyłącza pozycję ze wszystkich wyliczeń, zachowując dane. Wyłączony projekt nie zastępuje oferty w prognozie. Usunięcie pozycji nie wymaga przyczyny; historia zachowuje dane usuniętej pozycji. Zmiany aktywności podlegają uprawnieniom i kontroli wersji planu. Nowa kolumna enabled wymaga migracji 2026_10_28_020000_add_enabled_to_financial_plan_lines.
