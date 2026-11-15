<?php

namespace App\Services\Enms;

use Ramsey\Uuid\Uuid;

/** Adapter for the source library. The shared ledger knows no clause codes. */
final class LeadershipLibrary
{
    private ?array $data = null;

    public function data(): array
    {
        return $this->data ??= json_decode(file_get_contents(resource_path('enms/leadership-2026-11-15.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function requirements(): array
    {
        $result = [];
        foreach ([...$this->data()['WYMAGANIA'], ...$this->data()['POZYCJE_METODYCZNE']] as $item) {
            $item['id'] = $this->id('requirement', $item['kod']);
            $item['normative'] = isset($item['iso']);
            $item['dopuszczaNiedotyczy'] = ! $item['normative'] && ($item['dopuszczaNiedotyczy'] ?? false);
            $item['title'] = $item['obszar'] ?? $item['cel'];
            $item['question'] = $item['pytanie'] ?? $item['cel'];
            $item['group'] = collect($this->data()['ZOBOWIAZANIA'])->firstWhere('kod', $item['kodWiodacy'] ?? $item['kod'])['pytanie'] ?? 'P4';
            $result[$item['id']] = $item;
        }

        return $result;
    }

    public function positions(): array
    {
        $result = $this->requirements();
        foreach (['PROCESY_BIZ', 'ZASOBY', 'NAWYKI'] as $group) {
            foreach ($this->data()[$group] as $item) {
                $item['id'] = $this->id('requirement', $item['kod']);
                $item['title'] = $item['nazwa'];
                $item['question'] = $item['pytanie'] ?? $item['opis'] ?? $item['pomoc'] ?? $item['nazwa'];
                $item['normative'] = false;
                $item['pola'] = [];
                $result[$item['id']] = $item;
            }
        }

        return $result;
    }

    public function criteria(): array
    {
        $result = [];
        foreach ($this->requirements() as $requirement) {
            foreach ($requirement['kryteria'] as $criterion) {
                $id = $this->id('criterion', $criterion['id']);
                $result[$id] = $criterion + ['technical_id' => $id, 'requirement_id' => $requirement['id']];
            }
        }

        return $result;
    }

    public function id(string $kind, string $sourceCode): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://enesa.pl/enms/library/5.1/'.$kind.'/'.$sourceCode)->toString();
    }

    public function requirement(string $id): array
    {
        abort_unless(isset($this->positions()[$id]), 422, 'Nieznana pozycja biblioteki.');

        return $this->positions()[$id];
    }
}
