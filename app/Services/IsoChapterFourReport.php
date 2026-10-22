<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IsoChapterFourReport
{
    public function conclusionRules(string $prefix, array $choices): array
    {
        $rules = [$prefix => 'nullable|array|max:20', "$prefix.*" => 'array:id,czynniki,znaczenie,decyzja,dzialanie,sprawdzenie', "$prefix.*.id" => 'nullable|uuid|distinct', "$prefix.*.czynniki" => 'nullable|array|max:100', "$prefix.*.czynniki.*" => ['string', Rule::in($choices)]];
        foreach (['znaczenie', 'decyzja', 'dzialanie', 'sprawdzenie'] as $key) {
            $rules["$prefix.*.$key"] = 'required|string|max:3000';
        }

        return $rules;
    }

    public function identify(array $rows): array
    {
        return array_map(fn ($row) => array_replace($row, ['id' => $row['id'] ?? (string) Str::uuid()]), $rows);
    }

    public function rules(string $prefix): array
    {
        return [
            "$prefix.PODSUM" => 'nullable|array:ocena,bezPodsumowania,uzasadnienie,ustalenia,decyzje,braki',
            "$prefix.PODSUM.ocena" => 'nullable|string|max:10000',
            "$prefix.PODSUM.bezPodsumowania" => 'nullable|boolean',
            "$prefix.PODSUM.uzasadnienie" => 'nullable|string|max:3000',
            "$prefix.PODSUM.ustalenia" => 'nullable|array|max:20',
            "$prefix.PODSUM.ustalenia.*" => 'array:czynnik,komentarz',
            "$prefix.PODSUM.ustalenia.*.czynnik" => 'nullable|string|max:255',
            "$prefix.PODSUM.ustalenia.*.komentarz" => 'nullable|string|max:3000',
            "$prefix.PODSUM.decyzje" => 'nullable|array|max:20',
            "$prefix.PODSUM.decyzje.*" => 'array:przedmiot,rekomendacja,uzasadnienie,termin,status,zatwierdzil',
            "$prefix.PODSUM.decyzje.*.przedmiot" => 'nullable|string|max:3000',
            "$prefix.PODSUM.decyzje.*.rekomendacja" => 'nullable|string|max:3000',
            "$prefix.PODSUM.decyzje.*.uzasadnienie" => 'nullable|string|max:3000',
            "$prefix.PODSUM.decyzje.*.termin" => 'nullable|string|max:255',
            "$prefix.PODSUM.decyzje.*.status" => ['nullable', Rule::in(['rekomendacja', 'zatwierdzona'])],
            "$prefix.PODSUM.decyzje.*.zatwierdzil" => "required_if:$prefix.PODSUM.decyzje.*.status,zatwierdzona|nullable|string|max:1000",
            "$prefix.PODSUM.braki" => 'nullable|string|max:10000',
        ];
    }
}
