# Plan finansowy działu

Moduł znajduje się w menu obok Projektów. Każdy dział ma osobne plany roczne. Po wybraniu osoby, projektu lub oferty nazwa pozycji jest pobierana automatycznie. Własną nazwę wpisuje się tylko dla planowanej sprzedaży i pozostałych kosztów działu. Opis jest opcjonalny.

## Uruchomienie

1. W ustawieniach firmy włącz „Plan finansowy działu”. Migracja włącza go automatycznie we wdrożeniach z włączonym modułem Projektów.
2. Superadmin ma dostęp administracyjny. Pozostałym pracownikom nadaj jawnie `financial_plans.view` albo `financial_plans.manage`. Uprawnienie operacyjne `system.full_access` nie daje dostępu do wynagrodzeń.
3. Utwórz dział, wybierz jego pracowników i ewentualnie osoby uprawnione do planowania. Skład działu i dostęp do planu to osobne listy. Delegować dostęp może właściciel działu lub superadmin.
4. Otwórz rok i dodaj projekty, oferty, planowaną sprzedaż, koszty osób i pozostałe koszty działu. Pracownicy widoczni w selektorze są aktywnymi kontami wewnętrznymi.

## Zasady obliczeń

- Wszystkie kwoty są w PLN. Przychody i koszty projektowe są netto. Wynagrodzenie oznacza pełny koszt firmy.
- Wynik = przychody − bezpośrednie koszty realizacji − wynagrodzenia − samochody − pozostałe koszty działu.
- Koszt osoby i samochodu jest miesięczny. Okresy obejmują całe miesiące, bez proporcji dziennych. Podwyżkę zapisuje się w kolejnej pozycji na nowy, nienakładający się okres. Ta sama osoba nie może być naliczona dwa razy w różnych działach w tym samym miesiącu i roku planu.
- Dla pozostałych kosztów działu również wpisuje się kwotę miesięczną. Pozostałe kwoty w pozycji oferty, projektu lub sprzedaży dotyczą całego wskazanego okresu, również jeśli przekracza granicę roku.
- Projekt korzysta z wartości umowy, wystawionych i planowanych faktur, poniesionych i planowanych kosztów oraz zakupów. Faktury zastępują odpowiednią część wartości umowy. Zakup ujęty w finansach nie jest doliczany ponownie jako wymaganie.
- Zapisy finansowe i zakupy z określoną datą trafiają do odpowiedniego miesiąca. Pozostała wartość umowy i koszty bez daty są rozkładane równo na okres pozycji, z zachowaniem groszy.
- Ręczna korekta przychodu zastępuje wartość umowy lub oferty. Ręczny koszt projektu to **pozostały koszt realizacji**, który zastępuje planowane koszty źródłowe; koszty już poniesione pozostają doliczone. Rezerwa jest dodatkowa.
- Wykonanie projektów jest fakturowe: statusy `issued` i `paid`, według `entry_date`. Wykonanie kosztów osób i działu wpisuje się osobno. Braki wykonania są oznaczane ostrzeżeniami, nie są dowodem zerowego kosztu.
- Przepływy pieniężne są częściowym harmonogramem zapisów projektowych według `payment_date`, łącznie z planowanymi pozycjami. Nie obejmują kosztów wydziałowych, niewprowadzonych płatności, VAT ani salda rachunku.

## Oferty i scenariusze

- Wykluczona oferta nie wchodzi do prognozy. Przegrana i archiwalna oferta oraz utracona lub odrzucona szansa CRM są automatycznie pomijane.
- Ostrożny: projekty i oferty potwierdzone. Bazowy: dodatkowo realne oferty przemnożone przez ręcznie ocenioną szansę. Optymistyczny: realne i rezerwowe oferty w pełnej wartości. Przychód i koszt oferty mają ten sam współczynnik.
- Ręczna planowana sprzedaż i koszty wydziałowe są wspólne dla scenariuszy.
- Jedna szansa CRM może mieć tylko jedną ofertę w planach danego roku. Projekt może wskazywać ofertę, którą zastępuje; wtedy oferta przestaje być liczona, także jeśli była przypisana do innego działu.
- Projekt i oferta mogą być przypisane tylko raz w danym roku. Podział tego samego projektu pomiędzy działy nie jest obsługiwany.

## Zatwierdzanie i poufność

Zatwierdzenie zapisuje niezmienny plan roczny według scenariusza bazowego. Kolejne edycje i aktualizacje źródeł zmieniają prognozę. Brak kosztu, przychodu, wynagrodzenia wybranego pracownika lub dostępu do źródła blokuje zatwierdzenie. Jawne zero jest dopuszczalnym oszacowaniem. Ponowne zatwierdzenie tego samego planu jest blokowane.

Źródła zachowują istniejące zakresy dostępu do projektów, ofert i cen. Niepełny dostęp daje częściową prognozę z ostrzeżeniem. Zatwierdzony plan i historia zmian zachowują ochronę źródeł także po usunięciu pozycji z aktualnej prognozy.

Kwoty ręczne, wynagrodzenia, zatwierdzony plan i szczegółowa historia są szyfrowane w bazie. Ogólna lista zmian nie zawiera ich wartości. Każda zmiana prognozy zapisuje autora i jest chroniona numerem rewizji przed nadpisaniem zmian z innej karty. Opis zmiany jest opcjonalny. Utrata klucza aplikacji uniemożliwia odczyt zaszyfrowanych danych.
