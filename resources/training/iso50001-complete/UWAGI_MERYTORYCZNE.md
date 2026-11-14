# Uwagi po opracowaniu prezentacji ISO 50001 — 14.11.2026

Prezentacje obejmują wszystkie 48 sekcji obecnej struktury. Poniższe uwagi dotyczą niejasności w przekazanych materiałach i dalszego dopracowania ankiet. Nie są listą brakujących prezentacji. Nie zmieniono merytorycznych warunków ankiet, odpowiedzi, zatwierdzeń ani dokumentów audytowych.

Podstawą są dwa przekazane tomy „Energia pod kontrolą” Bronisława Pytla, paczka HTML z 11.11.2026 oraz istniejący kod platformy. To przegląd na potrzeby materiałów szkoleniowych, a nie pełny odbiór zgodności pytań, eksportów i wszystkich reguł prototypów.

## Najważniejsze decyzje autora lub audytora

| Punkt | Niejasność w źródle | Jak potraktowano ją w prezentacji / co ustalić dalej |
| --- | --- | --- |
| 6.5 | Specyfikacja na początku oznacza `MET-ZAP` jako wymaganie normy (N), a opis modelu danych jako metodę (M). Sama wskazuje JSON jako źródło prawdy. | Slajdy mówią o zachowaniu bazy i historii zmian. Nie przypisują spornego kodu do M/N. Trzeba uzgodnić klasyfikację oraz poprawić sprzeczny opis. |
| 6.6 | Źródło pozostawia autorowi przejście z opisów tekstowych na identyfikatory powiązanych danych, generowanie planu, rejestr wykonanych odczytów i terminy ważności dowodów pomiarowych. | Pokazano zależności 6.2–6.6 i 9.1 jako sposób pracy. Nie obiecano automatycznego wykrywania wszystkich braków ani generowania powiązań. |
| 7.1 | Próg „na styk” 15% jest wprost opisany jako wartość autorów prototypu, do potwierdzenia przez Martynę; nie pochodzi z normy ani książki. | Brak sztywnego progu na slajdach. Oceniane są zadania i faktycznie dostępny czas każdej osoby. Próg wymaga osobnej decyzji metodycznej. |
| 4.2 → 9.1 | Pozycje rejestru bez kwalifikacji pozostają poza oceną zgodności. Specyfikacja wskazuje, że może to dać pozorne pokrycie 100%, i pozostawia decyzję, czy kwalifikacja w 4.2 ma być obowiązkowa. | Prezentacje wymagają rozpoznania mających zastosowanie zobowiązań i dowodów ich spełnienia. Nie przyjęto nowej blokady ani nowego sposobu liczenia kompletności. |
| 10.1 | Specyfikacja sama wskazuje błędne odwołania w przykładzie `NK-2026-01`: kotłownia wskazuje kod pras, a lakiernia kod kotłowni. W `NK-2026-02` występują różne liczby sprawdzonych obowiązków: trzy i cztery. | Przykłady szkoleniowe nie używają tych kodów ani sprzecznych liczb. Autor powinien poprawić dane demonstracyjne przed ich użyciem w testach odbiorczych. |
| 10.2 | Obowiązkowość pola odbiorców planu jest wyraźnie pozostawiona do potwierdzenia przez Bronka. | Slajdy opisują komunikację i odpowiedzialność bez dodawania nowej blokady zatwierdzenia. |

## Pozostałe ograniczenia i zależności

- **Przygotowanie 3.1–3.5 i certyfikacja 11.1–11.4:** paczka HTML obejmuje 4.1–10.2. Dodatkowe sekcje opracowano na podstawie książki i struktury platformy. To etapy wdrożenia. Harmonogram, zakres i warunki oceny zewnętrznej trzeba uzgodnić z wybraną jednostką; slajdy nie ustanawiają uniwersalnego terminarza ani obowiązku certyfikacji.
- **4.3 i 4.4:** zachowano wcześniejsze prezentacje. Nadal należy osobno sprawdzić wcześniej zauważone różnice między starszymi regułami formularzy a nowszym HTML: wykorzystanie zakresu ISO 14001 w 4.3 oraz znaczenie opisu wyniku energetycznego w 4.4. To nie jest zakres obecnej aktualizacji slajdów.
- **5.1–6.4:** niektóre prototypy zawierają jedynie przykładowe teksty pomocy i odsyłają po ich rozwinięcie do autora. Treść dydaktyczną oparto na książce; nie dopisano brakujących pytań ani treści pomocy do ankiet. Wcześniejsza kwestia liczby pytań w 5.1 wymaga odrębnego odbioru ankiety.
- **6.2:** dodatkowe pola prototypu są rozszerzeniem metodyki względem formularza książkowego. Postęp realizacji wymaga rzeczywistej oceny; prezentacja nie sugeruje, że samo wypełnienie planu potwierdza osiągnięcie celu.
- **6.3–6.5:** progi SEU, dobór wskaźnika/modelu, normalizacja i reprezentatywność okresu bazowego wymagają uzasadnienia dla zakładu. Sam iloraz, wysoki R², niższy rachunek albo zakup fotowoltaiki nie stanowią samodzielnego dowodu poprawy. Nie przyjęto nowych progów ani algorytmów.
- **7.3–7.4:** rejestr zgłoszeń ma być wspólny, lecz opisy jego pól w kolejnych specyfikacjach nie są w pełni zsynchronizowane. Prezentacje pokazują przyjęcie zgłoszenia, rozpatrzenie i odpowiedź. Docelowy model i mapowanie danych trzeba uzgodnić przy zmianie formularzy.
- **7.5, 8.1–8.3:** automatyczne zasilanie wykazu dokumentów, powiązania nastaw z pomiarami, przenoszenie wniosków projektu do zakupów oraz przypomnienia są opisane w źródłach jako mechanizmy lub dalsze prace. Slajdy objaśniają proces i potrzebne dowody, nie potwierdzają ukończenia wszystkich tych funkcji w platformie.
- **9.1 / 9.1.1 / 9.1.2:** zachowano ustalenie użytkownika, że połączona ankieta 9.1 dotyczy nowych audytów. Dodano materiały do wszystkich trzech miejsc bez przenoszenia danych lub zatwierdzeń starszych audytów.
- **9.2:** bezstronność audytora jest opisana jako zasada pracy. Prezentacja nie tworzy nowej roli systemowej ani nie nadaje dostępu do audytów.
- **9.3:** decyzję „bez zmian” trzeba odróżnić od decyzji mieszanej, która zawiera także zadanie. Slajdy kierują do jednoznacznej decyzji, odpowiedzialności i dalszego sprawdzenia; nie wprowadzają liczby obowiązkowych decyzji, uniwersalnej częstotliwości ani nowej automatycznej interpretacji tekstu.
- **Wymagania prawne:** nie powielano z historycznych przykładów terminów i progów jako aktualnego prawa. Kwalifikacja obowiązków konkretnej organizacji i stan prawny pozostają przedmiotem jej rzeczywistej oceny zgodności.

## Granica obecnego wdrożenia

Każda prezentacja ma własne źródła w stopce, cel, powiązania i pytania kontrolne. Wątpliwości powyżej nie blokują objaśnienia procesu, ale wymagają rozstrzygnięcia przed wprowadzeniem odpowiadających im nowych blokad, obliczeń lub migracji ankiet. Specyfikacje dla wykonawcy, dane demonstracyjne i niepotwierdzone decyzje autora nie są wyświetlane klientowi jako część ankiety lub dokumentu audytowego.
