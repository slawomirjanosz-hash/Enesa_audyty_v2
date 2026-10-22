<?php

namespace App\Services;

/** Presentation only. Answers, permissions and document storage belong to the existing workflows. */
class IsoChapterFourPresentation
{
    public function documentSections(string $section, array $sections): array
    {
        $order = match ($section) {
            '4-3' => ['zakres' => '2. Zakres systemu zarządzania energią', 'granice' => '3. Granice fizyczne i organizacyjne', 'upraw' => '4. Uprawnienia do sterowania zużyciem energii', 'energia' => '5. Rodzaje energii w granicach systemu', 'poza' => '6. Co pozostaje poza granicami i dlaczego', 'konsekwencje' => '7. Konsekwencje z analizy kontekstu i wymagań stron'],
            '4-4' => ['decyzje' => '2. Forma systemu i odpowiedzialność', 'czasowniki' => '3. Ustanowienie, wdrożenie, utrzymanie i doskonalenie', 'procesy' => '4. Procesy systemu i ich właściciele', 'wlasne-procesy' => 'Własne procesy systemu', 'powiazania' => '5. Wzajemne oddziaływania procesów', 'obieg' => '6. Obieg informacji i progi reakcji', 'test' => '7. Test ciągłości systemu'],
            default => [],
        };

        return $order ? collect($order)->map(fn ($label, $key) => array_replace($sections[$key], ['title' => $label]))->all() : $sections;
    }

    public function description(string $section, string $group): string
    {
        if (! in_array($section, ['4-3', '4-4'])) {
            return '';
        }
        $contract = json_decode(file_get_contents(resource_path('iso50001/contracts-2026-10-22/'.$section.'.json')), true, 512, JSON_THROW_ON_ERROR);

        return $contract['descriptions'][$group] ?? '';
    }

    public function tabs(string $section, bool $client): array
    {
        $tabs = match ($section) {
            '4-1' => [
                ['1. Klimat i stan prawny', '#climate,#legal'],
                ['2. Czynniki zewnętrzne', '#section-0,#section-1,#section-2,#section-3'],
                ['3. Czynniki wewnętrzne', '#section-4,#section-5,#section-6'],
                ['4. Czynniki własne', '#custom'],
                ['5. Analiza konsultanta', '#swot'],
                ['6. Zatwierdzenia', '#approvals'],
                ['7. Dokument D-EnMS-KON-01', '#clause-document'],
            ],
            '4-2' => array_merge([['Klimat i przegląd', '#climate,#register-review']],
                array_map(fn ($group) => [$group['krotka'], '#group-'.$group['id']], app(IsoStakeholderQuestionnaire::class)->schema()['grupy']),
                [['Własne wymagania', '#custom'], ['Analiza konsultanta', '#consultant'], ['Zatwierdzenia', '#approvals'], ['Dokument D-EnMS-STR-01', '#clause-document']]),
            '4-3' => [['1. Konsekwencje z 4.1 i 4.2', '#konsekwencje'], ['2. Zakres', '#zakres'], ['3. Granice', '#granice'], ['4. Uprawnienia', '#upraw'], ['5. Rodzaje energii', '#energia'], ['6. Poza granicami', '#poza'], ['7. Analiza konsultanta', '#consultant'], ['8. Zatwierdzenia', '#approvals'], ['9. Dokument D-EnMS-ZAK-01', '#clause-document']],
            '4-4' => [['1. Decyzje o systemie', '#decyzje'], ['2. Cztery czasowniki', '#czasowniki'], ['3. Obieg informacji', '#obieg'], ['4. Test ciągłości', '#test'], ['5. Procesy systemu', '#procesy,#wlasne-procesy'], ['6. Powiązania procesów', '#powiazania'], ['7. Analiza konsultanta', '#consultant'], ['8. Zatwierdzenia', '#approvals,#checklist'], ['9. Dokument D-EnMS-SYS-01', '#clause-document']],
            default => [],
        };

        return array_values(array_filter($tabs, fn ($tab) => ! $client || ! in_array($tab[1], ['#swot', '#consultant'])));
    }
}
