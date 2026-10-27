# Plan finansowy działu

Moduł obok Projektów. Plan obejmuje wybrany zakres miesięcy, także na przełomie lat, np. 11.2027–02.2028. Nagłówek, kolumny i sumy pokazują ten zakres. Zakres planu roboczego można zmienić bez ponownego dodawania źródeł. Daty faktur, dostaw i okresy wynagrodzeń pozostają zachowane. Zatwierdzony okres i bazowy plan są niezmienne.

## Obsługa

1. Włącz moduł „Plan finansowy działu” w ustawieniach firmy.
2. Nadaj jawnie uprawnienie financial_plans.view lub financial_plans.manage i przypisz osobę do planowania działu. Skład działu nie daje dostępu do wynagrodzeń. Superadmin ma dostęp administracyjny; system.full_access nie daje dostępu do wynagrodzeń.
3. Utwórz dział i wybierz miesiąc początkowy i końcowy planu. Ponowne wybranie tych samych miesięcy otwiera istniejący plan.
4. Dodaj projekty, oferty i koszty zespołu. Nazwy osób, projektów i ofert są automatyczne; opis jest opcjonalny.

## Projekty

- Projekt nie wymaga okresu pozycji ani ręcznej kwoty przychodu/kosztu. Przychód to wyłącznie faktury planowane, wystawione i opłacone według daty dokumentu. Brakująca część wartości umowy nie jest rozkładana na miesiące; harmonogram uzupełnia się w finansach projektu.
- Koszty pochodzą z zapisów finansowych oraz planowanych i realizowanych materiałów/usług (dostaw), według daty dokumentu lub terminu dostawy. Koszt dostawy ujęty w finansach jest liczony tylko raz. Anulowane dostawy są pomijane.
- Dostawa bez terminu wymaga uzupełnienia daty w projekcie; jej koszt nie jest rozkładany automatycznie. Zrealizowana dostawa bez zapisu finansowego i terminu korzysta z daty ostatniej aktualizacji jako dostępnej daty realizacji.
- Brak wyceny dostawy jest oznaczany i blokuje zatwierdzenie. Jawne zero w źródle jest dopuszczalne. Brak jakichkolwiek kosztów projektu wymaga uzupełnienia źródła.
- Zmiana kwot i dat w projekcie aktualizuje prognozę. Dawne ręczne korekty projektów i rezerwy nie są już używane w prognozie; zatwierdzone wcześniej bazowe plany pozostają zachowane.
- Wykonanie obejmuje faktury/koszty wystawione lub opłacone; planowane zapisy nie są wykonaniem.

## Płatności

Przepływy projektowe korzystają z terminu płatności. Jeśli go brakuje, przyjmują datę dokumentu + 30 dni kalendarzowych, zarówno dla faktur klienta, jak i kosztów. To założenie budżetowe: nie zapisuje terminu w projekcie. Płatność może wypaść poza wybranym okresem, nawet gdy dokument jest w tym okresie. Kwoty netto; widok nie obejmuje VAT, salda rachunku, kosztów wydziałowych, ofert ani dostaw bez zapisu finansowego.

## Pozostałe pozycje

- Wynik działu = przychody − bezpośrednie koszty − wynagrodzenia − samochody − pozostałe koszty działu. Wszystkie kwoty w PLN; projekty netto, wynagrodzenia jako pełny koszt firmy.
- Koszty osób, samochodów i pozostałe koszty działu są miesięczne, za całe wskazane miesiące. Ich okresy pozostają potrzebne do zmian zatrudnienia, wynagrodzeń i samochodów. Brak pokrycia kosztu pracownika w części okresu jest sygnalizowany.
- Oferty i ręczna sprzedaż mają okres szacowanej realizacji; kwoty są dzielone na te miesiące z zachowaniem groszy. Scenariusz ostrożny: potwierdzone oferty; bazowy: także realne ważone szansą; optymistyczny: także rezerwowe w pełnej wartości. Przegrane i wykluczone oferty są pomijane. Projekt zastępuje wskazaną ofertę.
- W tym samym planie źródło można przypisać raz. Nakładające się okresy różnych działów chronią przed podwójnym przypisaniem źródeł i kosztów tej samej osoby, także na przełomie lat. Różne plany tego samego działu są osobnymi wariantami i nie są sumowane ze sobą.

## Poufność i zgodność

Kwoty ręczne, wynagrodzenia, plan bazowy i historia są szyfrowane. Dostęp do projektów, ofert i cen zachowuje istniejące ograniczenia. Niepełny dostęp oznacza częściowe sumy i blokuje zatwierdzenie. Historia i zatwierdzony plan zachowują ochronę źródeł po usunięciu pozycji. Rewizja chroni przed równoczesnym nadpisaniem. Dotychczasowe roczne plany są zachowane jako zakres styczeń–grudzień, bez zmiany zatwierdzonych wartości.
