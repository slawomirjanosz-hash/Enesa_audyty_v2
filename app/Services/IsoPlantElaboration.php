<?php

namespace App\Services;

use App\Models\IsoPlantProfile;

/** Deterministic synthesis of approved facts; never infers certification or legal compliance. */
class IsoPlantElaboration
{
    public function __construct(private readonly IsoPlantQuestionnaire $questionnaire) {}

    public function build(IsoPlantProfile $profile): array
    {
        $answers = app(IsoPlantCalculations::class)->apply($profile->definition, $profile->answers);
        $questions = collect($this->questionnaire->questions($profile->definition))->keyBy('key');
        $facts = $this->questionnaire->facts($profile->definition, $answers);
        $used = [];
        $gaps = [];
        $value = function (string $key) use ($facts) {
            $v = $facts[$key] ?? null;
            if (blank($v) || (is_array($v) && array_intersect(['unknown', 'nie wiem'], array_filter($v, 'is_string')))) {
                return null;
            }

            return $v;
        };
        $text = function (string $key, string $label) use ($value, $questions, $answers, &$used, &$gaps): string {
            if ($value($key) === null || ! isset($questions[$key])) {
                $gaps[$key] = $label;

                return '[do uzupełnienia: '.$label.']';
            }
            $used[$key] = true;
            $q = $questions[$key];
            $display = $this->questionnaire->display($q, $answers[$key]);
            if (in_array($q['type'], ['number', 'auto'], true) && is_numeric($value($key))) {
                $display = rtrim(rtrim(number_format((float) $value($key), 6, ',', $key === 'FAKT_ROK_ODNIESIENIA' ? '' : ' '), '0'), ',');
            }

            return $display.(filled($q['unit'] ?? null) ? ' '.$q['unit'] : '');
        };
        // Earlier profiles retain their own identifiers; only equivalent concepts are mapped.
        $key = fn (string $current, string $legacy): string => $questions->has($current) ? $current : $legacy;
        $sections = [];
        $sections[] = ['title' => 'Cel i podstawa opracowania', 'paragraphs' => [
            'Dokument opisuje stan wyjściowy zakładu i porządkuje przesłanki do zaplanowania systemu zarządzania energią (EnMS). Podstawą są dane profilu zakładu potwierdzone przez klienta i przejrzane przez audytora, według stanu na '.$profile->as_of_date->format('d.m.Y').'.',
            'Opracowanie stanowi zapis przygotowania wdrożenia. Nie zastępuje ustalenia zakresu i granic systemu, przeglądu energetycznego ani oceny zgodności. Wnioski wskazują zadania do dalszych punktów, a nie potwierdzenie ich wykonania.',
        ]];
        $sections[] = ['title' => 'Charakterystyka organizacji i zakładu', 'paragraphs' => [
            'Opracowanie dotyczy organizacji '.$text('organization.name', 'nazwa organizacji').' (identyfikator: '.$text('organization.tax_id', 'identyfikator organizacji').') i zakładu '.$text('site.name', 'nazwa zakładu').', zlokalizowanego pod adresem '.$text('site.address', 'adres zakładu').'. Działalność zakładu obejmuje: '.$text($key('FAKT_BRANZA', 'site.industry'), 'działalność zakładu').'.',
            'Zadeklarowane zatrudnienie wynosi '.$text($key('FAKT_PRACOWNICY', 'operations.headcount'), 'zatrudnienie').'. Organizacja pracy została opisana następująco: '.$text($key('FAKT_ZMIANY', 'operations.shift_pattern'), 'system zmianowy').'. Sezonowość pozapogodowa: '.$text($key('FAKT_SEZONOWOSC', 'operations.seasonality'), 'sezonowość').'. Te warunki należy uwzględnić przy porównywaniu zużycia między okresami.',
            'Osobą wskazaną do koordynacji danych profilu jest '.$text('profile.contact_person', 'osoba kontaktowa').'. Samo wskazanie osoby kontaktowej nie nadaje jej uprawnień zespołu zarządzania energią.',
        ]];
        $locations = $value('FAKT_LOKALIZACJE_LISTA');
        if (is_array($locations)) {
            $used['FAKT_LOKALIZACJE_LISTA'] = true;
            $sections[] = ['title' => 'Lokalizacje i granice rozpoznania', 'paragraphs' => array_merge(array_map(
                fn ($row) => 'Lokalizacja '.($row['c0'] ?? '[nazwa do uzupełnienia]').', adres: '.($row['c1'] ?? '[adres do uzupełnienia]').', funkcja: '.($row['c2'] ?? '[do uzupełnienia]').'. Zadeklarowane objęcie systemem: '.($row['c3'] ?? '[do uzupełnienia]').'.', $locations
            ), ['Lista jest materiałem wejściowym do punktu 4.3. Granice, działalność i uprawnienia do kontroli należy potwierdzić w dokumencie zakresu; nie wykluczać rodzajów energii wewnątrz przyjętych granic.'])];
        } else {
            $gaps['locations'] = 'lokalizacje, działalność i uprawnienia do kontroli w przyszłych granicach systemu';
        }
        $energy = [];
        $carriers = $value('FAKT_NOSNIKI');
        if (is_array($carriers)) {
            $used['FAKT_NOSNIKI'] = true;
            $energy[] = 'Dane o nośnikach odnoszą się do roku '.$text('FAKT_ROK_ODNIESIENIA', 'rok odniesienia').'. Poniższe wielkości są deklaracjami z profilu i wymagają zachowania dokumentów źródłowych.';
            foreach ($carriers as $row) {
                $quantity = is_numeric($row['c1'] ?? null) ? number_format((float) $row['c1'], 2, ',', ' ') : '[ilość do uzupełnienia]';
                $cost = is_numeric($row['c3'] ?? null) ? ' Zadeklarowany koszt roczny netto wynosi '.number_format((float) $row['c3'], 2, ',', ' ').' zł.' : ' Nie podano kosztu rocznego netto; nie przyjęto wartości zerowej.';
                $energy[] = 'Dla nośnika „'.($row['c0'] ?? '[do uzupełnienia]').'” wykazano zużycie '.$quantity.' '.($row['c2'] ?? '[jednostka do uzupełnienia]').'.'.$cost;
            }
            if ($value('ZUZYCIE_TJ') !== null) {
                $energy[] = 'Orientacyjna suma po przeliczeniu jednostek wynosi '.$text('ZUZYCIE_TJ', 'zużycie w TJ').'. Obliczenie korzysta z przeliczników biblioteki ankiety; wartości opałowe należy potwierdzić. Jest to zestawienie jednego roku, nie średnia z trzech lat i nie rozstrzygnięcie obowiązków prawnych.';
            }
        } else {
            $energy[] = 'Nie uzyskano zestawienia pozwalającego przedstawić roczne zużycie wszystkich nośników. Nie obliczono sumy zużycia ani kosztów na podstawie niepełnych danych.';
            $gaps['energy'] = 'pełne zestawienie nośników, ilości, jednostek, okresów i źródeł danych';
        }
        $sections[] = ['title' => 'Obraz gospodarki energetycznej', 'paragraphs' => $energy];
        $sections[] = ['title' => 'Procesy i infrastruktura energetyczna', 'paragraphs' => [
            'Jako główne procesy lub odbiory energii wskazano: '.$text($key('FAKT_PROCESY', 'systems.main_consumers'), 'główne procesy i odbiory').'. Jest to lista do weryfikacji pomiarami, nie zatwierdzony wykaz znaczących użyć energii (SEU).',
            ...array_values(array_filter(array_map(function ($entry) use ($value, $text) {
                [$k, $label] = $entry;

                return $value($k) !== null ? $label.': '.$text($k, $label).'.' : null;
            }, [['FAKT_KOTLOWNIA', 'Sposób zaopatrzenia w ciepło'], ['FAKT_SPREZONE', 'Obecność instalacji sprężonego powietrza'], ['FAKT_WENT', 'Obecność central wentylacyjnych'], ['FAKT_CHLODZENIE', 'Zapotrzebowanie na chłód'], ['FAKT_PV', 'Fotowoltaika'], ['FAKT_CHP', 'Kogeneracja'], ['FAKT_FLOTA', 'Flota w deklarowanym zakresie']]))),
            'Dalsza analiza powinna połączyć wykorzystanie urządzeń z czasem pracy, obciążeniem i warunkami procesu. Sam fakt posiadania urządzenia nie określa jego udziału w zużyciu ani możliwej oszczędności.',
        ]];
        $sections[] = ['title' => 'Dane, pomiary i możliwość oceny wyniku', 'paragraphs' => [
            'Poziom opomiarowania energii elektrycznej opisano jako: '.$text($key('FAKT_PODLICZNIKI', 'metering.coverage'), 'zakres opomiarowania').'. '.($value('FAKT_PODLICZNIKI') === 'brak' ? '' : 'Sposób odczytu: '.$text($key('FAKT_ODCZYT', 'metering.frequency'), 'sposób odczytu').'. ').'Dostępna historia danych '.($questions->has('FAKT_DANE_HISTORIA') ? 'innych niż faktury' : '').': '.$text($key('FAKT_DANE_HISTORIA', 'data.history_coverage'), 'historia danych').'.',
            ($questions->has('FAKT_MES') ? 'Rejestrowanie produkcji w MES/ERP z dokładnością do dnia lub zmiany' : 'Dostępność danych o produkcji').': '.$text($key('FAKT_MES', 'data.production_available'), 'dane o produkcji').'. Przed doborem EnPI i EnB należy sprawdzić zgodność okresów, kompletność danych oraz wpływ produkcji, pogody i sposobu użytkowania. Nie wyznaczono wskaźnika ani linii bazowej wyłącznie z odpowiedzi ankietowych.',
        ]];
        $system = $value('FAKT_SYSTEM_BAZOWY');
        $integration = $value('FAKT_ZGODA_INTEGRACJA');
        if ($integration !== null) {
            $used['FAKT_ZGODA_INTEGRACJA'] = true;
        }
        $sections[] = ['title' => 'Organizacja wdrożenia i istniejące systemy', 'paragraphs' => [
            'Istniejący system zarządzania: '.$text($key('FAKT_SYSTEM_BAZOWY', 'management.existing_systems'), 'istniejące systemy zarządzania').'. '.($system === 'brak' ? 'Należy ocenić obecne praktyki i ustanowić lub uzupełnić zasady nadzoru nad dokumentacją, odpowiedzialności, przeglądów i doskonalenia. Brak certyfikowanego systemu nie oznacza braku procedur.' : ($integration === 'tak' ? 'Zadeklarowano wykorzystanie istniejącej dokumentacji. Należy ocenić jej przydatność i uzupełnić procesy o wymagania energetyczne.' : 'Nie przyjęto automatycznie integracji. Sposób wykorzystania obecnych procesów wymaga uzgodnienia.')),
            'Wyznaczenie osoby odpowiedzialnej za energię: '.$text($key('FAKT_ENERGY_MANAGER', 'management.coordinator_assigned'), 'odpowiedzialność za energię').'. Osobna pozycja budżetowa na poprawę efektywności: '.$text('FAKT_BUDZET', 'środki na poprawę efektywności').'. Te deklaracje nie zastępują decyzji kierownictwa, przydziału uprawnień, czasu i zasobów dla konkretnych zadań.',
        ]];
        $actions = [
            ['title' => 'Uzgodnienie podstaw wdrożenia', 'text' => 'Potwierdzić decyzję kierownictwa, cel wdrożenia, potrzebę certyfikacji, odpowiedzialności, czas i zasoby. W profilu nie ma pełnego zapisu tych decyzji.', 'target' => '5.1, 5.3 i 7.1'],
            ['title' => 'Kontekst, zainteresowane strony i granice', 'text' => 'Wykorzystać opis działalności i lokalizacji do oceny kontekstu, identyfikacji konkretnych wymagań oraz ustalenia zakresu i granic. Nie przenosić deklaracji bez oceny istotności i źródeł.', 'target' => '4.1–4.4'],
            ['title' => 'Podstawa oceny energetycznej', 'text' => 'Zestawić faktury, odczyty i dane o warunkach pracy dla reprezentatywnych okresów. Określić metody wyboru SEU, zmienne istotne, sposób oceny wyniku i plan uzupełnienia pomiarów.', 'target' => '6.3–6.6'],
        ];
        foreach ([
            ['FAKT_ENERGY_MANAGER', 'nie', 'Odpowiedzialność', 'Nie wskazano osoby odpowiedzialnej za energię. Uzgodnić skład zespołu i przypisać zadania oraz uprawnienia.', '5.3'],
            ['FAKT_PODLICZNIKI', 'brak', 'Rozpoznanie zużycia', 'Dostępny jest tylko licznik główny. Ustalić, jak rozdzielić zużycie na istotne obszary: pomiary, bilans lub uzasadnione oszacowania.', '6.3, 6.6'],
            ['FAKT_ANALIZA_DANYCH', 'nie', 'Wykorzystanie danych', 'Zadeklarowano brak analizy danych. Ustalić odpowiedzialność, częstotliwość i sposób przeglądu wyników.', '6.6, 9.1.1'],
            ['FAKT_INWESTYCJE', 'tak', 'Zmiany w zakładzie', 'Planowane inwestycje wymagają uwzględnienia w przewidywanym zużyciu i weryfikacji przyjętych granic oraz danych odniesienia.', '4.3, 6.3, 8.2'],
            ['FAKT_PRZETARGI', 'tak', 'Wymagania odbiorców', 'Sprawdzić treść specyfikacji przetargowych i znaczenie certyfikatu; zachować dowody wymagań.', '4.2'],
        ] as [$k, $expected, $title, $body, $target]) {
            if ($value($k) === $expected) {
                $used[$k] = true;
                $actions[] = ['title' => $title, 'text' => $body, 'target' => $target];
            }
        }
        $evidence = [];
        foreach (array_keys($used) as $k) {
            $answer = $answers[$k] ?? [];
            $evidence[] = ['key' => $k, 'source' => filled($answer['source'] ?? null) ? $answer['source'] : 'Deklaracja w zatwierdzonym profilu; nie wskazano dokumentu źródłowego.', 'detail' => $answer['detail'] ?? null];
        }

        return compact('sections', 'actions', 'gaps', 'evidence');
    }
}
