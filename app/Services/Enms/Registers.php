<?php

namespace App\Services\Enms;

/** Field contracts for the native register editors, with source wording. */
final class Registers
{
    public const TITLES = ['citation_verification' => 'Weryfikacja merytoryczna cytatu', 'evidence' => 'Rejestr dowodów', 'verification' => 'Weryfikacja dowodu', 'coverage' => 'Pokrycie kryterium', 'closure' => 'Zamknięcie kryterium', 'assessment' => 'Ocena wymagania', 'finding' => 'Ustalenie', 'question' => 'Pytanie do klienta', 'decision' => 'Postanowienie kierownictwa', 'task' => 'Zadanie', 'deadline' => 'Termin', 'effectiveness' => 'Ocena skuteczności', 'authority' => 'Upoważnienie', 'rule' => 'Zasada przywództwa'];

    public function fields(string $kind, array $options): array
    {
        $f = fn ($key, $label, $type = 'text', $required = false, $choices = []) => compact('key', 'label', 'type', 'required', 'choices');
        $e = $options['evidence'] ?? [];
        $r = ($kind === 'task' ? ($options['task_requirements'] ?? null) : null) ?? $options['requirements'] ?? [];
        if (in_array($kind, ['assessment', 'rule'])) {
            $r = $options['normative_requirements'] ?? [];
        }
        $c = $options['criteria'] ?? [];
        $u = $options['users'] ?? [];
        $states = ['wykazane' => 'Wykazane', 'zadeklarowane' => 'Zadeklarowane', 'niewykazane' => 'Niewykazane', 'wtoku' => 'W toku', 'niespelnione' => 'Niespełnione', 'pozazakresem' => 'Poza zakresem'];

        $fields = match ($kind) {
            'citation_verification' => [$f('citation_key', 'Cytat', 'select', true, $options['citations'] ?? []), $f('state', 'Weryfikacja merytoryczna', 'select', true, ['zweryfikowana' => 'Zweryfikowana', 'niezweryfikowana' => 'Niezweryfikowana']), $f('note', 'Wynik sprawdzenia i uwagi', 'textarea', true)],
            'evidence' => [
                $f('rodzaj', 'Rodzaj', 'select', false, ['DOK' => 'Dokument zatwierdzony', 'ZAP' => 'Zapis z działania', 'SYS' => 'Zapis w systemie informatycznym', 'UMO' => 'Umowa, zamówienie, pozycja budżetu', 'OBS' => 'Obserwacja konsultanta na miejscu', 'ROZ' => 'Rozmowa, wywiad', 'INN' => 'Inne — opisowo']),
                $f('tytul', 'Czym to jest', 'textarea'), $f('identyfikator', 'Numer albo symbol'), $f('data', 'Data dowodu', 'date'), $f('wydal', 'Kto wydał'), $f('uczestnicy', 'Uczestnicy'), $f('gdzie', 'Gdzie ten dowód jest'), $f('zakres', 'Która część jest istotna — nieobowiązkowe'), $f('requirements', 'Czego ten dowód dotyczy', 'multi', false, $r), $f('przegladDo', 'Termin ponownego sprawdzenia', 'date'), $f('attachment_id', 'Załącznik z dokumentacji punktu', 'select', false, $options['documents'] ?? []),
            ],
            'verification' => [
                $f('evidence_id', 'Dowód', 'select', true, $e), $f('dostepnosc', 'Czy dowód okazano', 'select', true, ['niezweryfikowany' => 'Niezweryfikowany', 'okazany' => 'Okazany', 'nieokazany' => 'Nieokazany', 'brakdostepu' => 'Brak dostępu']), $f('wiarygodnosc', 'Czy jest tym, za co się podaje', 'select', false, ['potwierdzona' => 'Potwierdzona', 'watpliwa' => 'Wątpliwa', 'niepotwierdzona' => 'Niepotwierdzona']), $f('zakresInformacji', 'Co ten dowód faktycznie zawiera', 'textarea'), $f('sposob', 'Jak to sprawdzono', 'select', false, ['wgląd w dokument' => 'Wgląd w dokument', 'oględziny' => 'Oględziny', 'rozmowa' => 'Rozmowa', 'zapis w systemie' => 'Zapis w systemie', 'próbka' => 'Próbka', 'wgląd w zapisy' => 'Wgląd w zapisy', 'wgląd w zapis w systemie' => 'Wgląd w zapis w systemie', 'wgląd w pozycję budżetu' => 'Wgląd w pozycję budżetu']), $f('probka', 'Ile i z czego sprawdzono', 'textarea'), $f('uwaga', 'Dlaczego budzi wątpliwość', 'textarea'),
            ],
            'coverage' => [$f('evidence_id', 'Dowód', 'select', true, $e), $f('criterion_id', 'Kryterium', 'select', true, $c), $f('zakres', 'Na ile ten dowód pokrywa to kryterium', 'select', true, ['pelne' => 'Pełne', 'czesciowe' => 'Częściowe', 'zadne' => 'Żadne']), $f('coPotwierdza', 'Co to potwierdza', 'textarea'), $f('czegoBrakuje', 'Czego nie pokrywa', 'textarea')],
            'closure' => [$f('criterion_id', 'Kryterium', 'select', true, $c), $f('stan', 'Stan', 'select', true, ['zamkniete' => 'Zamknięte', 'otwarte' => 'Otwarte']), $f('uzasadnienie', 'Uzasadnienie rozstrzygnięcia', 'textarea', true)],
            'assessment' => [$f('requirement_id', 'Obowiązek', 'select', true, $r), $f('stan', 'Ocena', 'select', true, $states), $f('uzasadnienie', 'Uzasadnienie — obowiązkowe zawsze', 'textarea', true), $f('coverage_ids', 'Źródła oceny — pokrycia kryteriów', 'multi', false, $options['coverage'] ?? []), $f('ustalenia', 'Ustalenia dodatkowe — nieobowiązkowe', 'textarea'), $f('mode', 'Tryb oceny', 'select', true, ['ciagla' => 'Ciągła', 'okresowa' => 'Okresowa', 'zdarzeniowa' => 'Zdarzeniowa']), $f('period_from', 'Początek okresu', 'date'), $f('period_until', 'Koniec okresu', 'date'), $f('event', 'Zdarzenie będące podstawą oceny', 'textarea')],
            'finding' => [$f('requirement_id', 'Czego dotyczy', 'select', true, $r), $f('criterion_id', 'Kryterium', 'select', false, $c), $f('typ', 'Typ', 'select', true, ['zgloszona' => 'Zgłoszona', 'potwierdzona' => 'Potwierdzona', 'brakdowodu' => 'Brak dowodu']), $f('opis', 'Opis', 'textarea', true), $f('ryzyko', 'Ryzyko', 'textarea', true), $f('priorytet', 'Priorytet', 'select', true, ['wysoki' => 'Wysoki', 'sredni' => 'Średni', 'niski' => 'Niski'])],
            'question' => [$f('requirement_id', 'Czego dotyczy', 'select', true, $r), $f('pytanie', 'Pytanie', 'textarea', true)],
            'decision' => [$f('przedmiot', 'Czego dotyczy', 'textarea', true), $f('warianty', 'Warianty — opcjonalnie', 'textarea'), $f('rekomendacja', 'Rekomendacja Konsultanta', 'textarea'), $f('decider_id', 'Kto decyduje', 'select', true, $u), $f('requirements', 'Dotyczy obowiązków', 'multi', true, $r), $f('action_type', 'Rodzaj działania', 'select', true, config('enms.action_types')), $f('amount', 'Wartość decyzji', 'number'), $f('currency', 'Waluta')],
            'task' => [$f('source_id', 'Z czego wynika', 'select', false, $options['sources'] ?? []), $f('przedmiot', 'Co ma być zrobione', 'textarea', true), $f('owner_id', 'Właściciel', 'select', true, $u), $f('requirements', 'Realizuje wymagania', 'multi', true, $r), $f('istotnosc', 'Istotność', 'select', true, ['proste' => 'Proste', 'istotne' => 'Istotne']), $f('risk', 'Ryzyko', 'select', true, ['niskie' => 'Niskie', 'srednie' => 'Średnie', 'wysokie' => 'Wysokie']), $f('action_type', 'Rodzaj działania', 'select', true, config('enms.action_types')), $f('stan', 'Stan', 'select', true, ['zaplanowane' => 'Zaplanowane', 'wtoku' => 'W toku', 'wykonane' => 'Wykonane', 'zaniechane' => 'Zaniechane']), $f('evidence_id', 'Dowód wykonania', 'select', false, $e), $f('executor_id', 'Wykonawca działania', 'select', false, $u), $f('area_responsible_ids', 'Odpowiedzialni za obszar', 'multi', false, $u), $f('cycle', 'Cykl — dla zadania powtarzalnego'), $f('occurrence', 'Wystąpienie cyklu'), $f('amount', 'Wartość działania', 'number'), $f('currency', 'Waluta')],
            'deadline' => [$f('target_id', 'Zadanie lub postanowienie', 'select', true, $options['targets'] ?? []), $f('date', 'Termin', 'date', true), $f('origin', 'Pochodzenie terminu', 'select', true, ['enesa' => 'Proponowany przez ENESA', 'organizacja' => 'Zadeklarowany przez organizację', 'zatwierdzony' => 'Zatwierdzony przez uprawniony podmiot']), $f('evidence_id', 'Dowód zatwierdzenia', 'select', false, $e), $f('cycle_until', 'Koniec cyklu ograniczającego', 'date'), $f('cycle_kind', 'Rodzaj cyklu ograniczającego'), $f('reason', 'Przyczyna ustalenia / zmiany terminu', 'textarea', true)],
            'effectiveness' => [$f('task_id', 'Zadanie', 'select', true, $options['task'] ?? []), $f('wynik', 'Wynik', 'select', true, ['skuteczne' => 'Skuteczne', 'nieskuteczne' => 'Nieskuteczne', 'zawczesnie' => 'Za wcześnie na ocenę']), $f('metoda', 'Metoda oceny', 'textarea', true), $f('uzasadnienie', 'Uzasadnienie', 'textarea', true), $f('competence_id', 'Dowód kompetencji oceniającego', 'select', true, $e), $f('supervisor_id', 'Nadzór nad samooceną', 'select', false, $u)],
            'authority' => [$f('user_id', 'Osoba upoważniona', 'select', true, $u), $f('class', 'Klasa działania', 'select', true, config('enms.action_classes')), $f('activity', 'Czynność', 'select', true, ['wykonanie' => 'Wykonanie', 'zatwierdzenie_rezultatu' => 'Zatwierdzenie rezultatu']), $f('evidence_id', 'Podstawa upoważnienia', 'select', false, $e), $f('valid_from', 'Obowiązuje od', 'date', true), $f('valid_until', 'Obowiązuje do', 'date'), $f('limit_amount', 'Limit finansowy', 'number'), $f('currency', 'Waluta'), $f('limit_period', 'Rodzaj limitu', 'select', false, ['event' => 'Na zdarzenie', 'year' => 'Rocznie']), $f('required_steps', 'Liczba wymaganych stopni zgody', 'integer', true), $f('step', 'Stopień zgody', 'integer', true), $f('area', 'Obszar / proces — puste oznacza cały zakres'), $f('exclusion', 'Wyłączenie obszaru / procesu'), $f('substitute_id', 'Zastępca', 'select', false, $u), $f('substitute_from', 'Zastępstwo od', 'date'), $f('substitute_until', 'Zastępstwo do', 'date'), $f('substitute_evidence_id', 'Podstawa zastępstwa', 'select', false, $e)],
            'rule' => [$f('requirement_id', 'Obowiązek', 'select', true, $r), $f('tresc', 'Proponowana treść zasady', 'textarea'), $f('evidence_id', 'Podstawa treści zasady', 'select', false, $e)],
            default => abort(404),
        };
        if (in_array($kind, ['task', 'decision'])) {
            $fields[] = $f('area', 'Obszar / proces');
        }
        if ($kind === 'task') {
            $fields[] = $f('result_action_type', 'Skutek zatwierdzenia rezultatu — ustal przed zatwierdzeniem', 'select', false, array_diff_key(config('enms.action_types'), ['operation' => true]));
        }
        if ($kind === 'authority') {
            $fields[] = $f('subject_kind', 'Podmiot upoważnienia', 'select', false, ['osoba' => 'Osoba', 'rola' => 'Rola organizacyjna', 'organ' => 'Organ kolegialny']);
            $fields[] = $f('subject_name', 'Nazwa roli / organu reprezentowanego przez wskazaną osobę');
        }

        return $fields;
    }
}
