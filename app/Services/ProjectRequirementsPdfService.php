<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use Throwable;

class ProjectRequirementsPdfService
{
    private const ALIASES = [
        'name' => ['nazwa', 'nazwa pozycji', 'nazwa materialu', 'nazwa materialu uslugi', 'material', 'towar', 'produkt', 'asortyment', 'pozycja', 'item', 'product', 'service'],
        'type' => ['rodzaj', 'typ', 'typ pozycji', 'kategoria', 'type'],
        'quantity' => ['ilosc', 'ilosc zamawiana', 'liczba', 'qty', 'quantity'],
        'unit' => ['jednostka', 'jednostka miary', 'jm', 'j m', 'unit'],
        'total_cost' => ['szacowany koszt', 'koszt laczny', 'koszt netto', 'wartosc laczna', 'wartosc netto', 'kwota netto', 'wartosc', 'koszt', 'total'],
        'unit_cost' => ['cena', 'cena jednostkowa', 'cena netto', 'cena jedn', 'koszt jednostkowy', 'unit price', 'price'],
        'needed_by' => ['potrzebne do', 'termin', 'termin dostawy', 'data dostawy', 'deadline'],
        'status' => ['status', 'stan', 'etap', 'state'],
        'supplier' => ['dostawca', 'kontrahent', 'producent', 'vendor', 'supplier'],
        'supplier_nip' => ['nip dostawcy', 'nip kontrahenta', 'nip', 'tax id'],
        'responsible_email' => ['email odpowiedzialnego', 'e mail odpowiedzialnego', 'email osoby', 'email'],
        'responsible_name' => ['osoba odpowiedzialna', 'odpowiedzialny', 'prowadzacy', 'owner'],
        'description' => ['opis', 'opis uwagi', 'uwagi', 'komentarz', 'specyfikacja', 'notes'],
    ];

    public function preview(Project $project, UploadedFile $file): array
    {
        try {
            $pdf = (new Parser)->parseFile($file->getRealPath());
            $plainText = trim($pdf->getText());
            $pages = $pdf->getPages();
        } catch (Throwable) {
            $this->fail('Nie udało się odczytać pliku PDF. Sprawdź, czy plik nie jest uszkodzony lub zabezpieczony hasłem.');
        }

        if (mb_strlen($plainText) < 20) {
            $this->fail('PDF nie zawiera tekstu możliwego do odczytania. Prawdopodobnie jest skanem i wymaga OCR.');
        }

        $rawRows = collect();
        $warnings = [];
        foreach ($pages as $pageIndex => $page) {
            $positionedRows = $this->positionedRows($page->getDataTm());
            $header = null;
            foreach ($positionedRows as $positionedRow) {
                $candidate = $this->headerColumns($positionedRow);
                if ($candidate !== null) {
                    $header = $candidate;

                    continue;
                }
                if ($header === null) {
                    continue;
                }
                $values = $this->assignToColumns($positionedRow, $header);
                if ($this->text($values['name'] ?? null)) {
                    $rawRows->push($values + ['page' => $pageIndex + 1]);
                }
            }
        }

        if ($rawRows->isEmpty()) {
            $this->fail('Nie znaleziono tabeli materiałów. PDF powinien zawierać nagłówki takie jak Nazwa oraz Ilość, Jednostka, Cena lub Status.');
        }
        if ($rawRows->count() > 300) {
            $warnings[] = 'PDF zawiera więcej niż 300 pozycji. Do podglądu pobrano pierwsze 300.';
            $rawRows = $rawRows->take(300);
        }

        $teamIds = $project->members()->pluck('users.id')->push($project->manager_id)->filter()->unique();
        $team = User::whereIn('id', $teamIds)->get();
        $teamByEmail = $team->keyBy(fn (User $user) => Str::lower($user->email));
        $teamByName = $team->groupBy(fn (User $user) => $this->normalize($user->name));
        $suppliers = Company::suppliers()->active()->get();
        $suppliersByNip = $suppliers->filter(fn (Company $supplier) => $supplier->nip)->keyBy(fn (Company $supplier) => preg_replace('/\D+/', '', $supplier->nip));
        $suppliersByName = $suppliers->groupBy(fn (Company $supplier) => $this->normalize($supplier->name));

        $rows = $rawRows->values()->map(function (array $raw, int $index) use ($teamByEmail, $teamByName, $suppliersByNip, $suppliersByName, &$warnings) {
            $quantity = $this->number($raw['quantity'] ?? null);
            if ($quantity === null || $quantity <= 0) {
                $quantity = 1.0;
                $warnings[] = 'Pozycja '.($index + 1).': nie rozpoznano ilości - ustawiono 1.';
            }
            $type = $this->type($raw['type'] ?? null);
            $unit = $this->text($raw['unit'] ?? null) ?: ($type === 'service' ? 'usł.' : 'szt.');
            $totalCost = $this->number($raw['total_cost'] ?? null);
            if ($totalCost === null) {
                $unitCost = $this->number($raw['unit_cost'] ?? null);
                $totalCost = $unitCost === null ? null : round($unitCost * $quantity, 2);
            }
            $neededBy = $this->date($raw['needed_by'] ?? null);
            if ($this->text($raw['needed_by'] ?? null) && ! $neededBy) {
                $warnings[] = 'Pozycja '.($index + 1).': nie rozpoznano terminu - sprawdź datę.';
            }

            $email = Str::lower($this->text($raw['responsible_email'] ?? null) ?? '');
            $responsible = $email !== '' ? $teamByEmail->get($email) : null;
            if (! $responsible && $this->text($raw['responsible_name'] ?? null)) {
                $matches = $teamByName->get($this->normalize($raw['responsible_name']), collect());
                $responsible = $matches->count() === 1 ? $matches->first() : null;
            }

            $supplierName = $this->text($raw['supplier'] ?? null);
            $nip = preg_replace('/\D+/', '', (string) ($raw['supplier_nip'] ?? ''));
            $supplier = $nip !== '' ? $suppliersByNip->get($nip) : null;
            if (! $supplier && $supplierName) {
                $matches = $suppliersByName->get($this->normalize($supplierName), collect());
                $supplier = $matches->count() === 1 ? $matches->first() : null;
            }

            return [
                'source' => 'Strona '.$raw['page'],
                'type' => $type,
                'name' => $this->text($raw['name']) ?? '',
                'description' => $this->text($raw['description'] ?? null),
                'quantity' => round($quantity, 2),
                'unit' => Str::limit($unit, 30, ''),
                'estimated_cost' => $totalCost === null ? null : round($totalCost, 2),
                'needed_by' => $neededBy,
                'status' => $this->status($raw['status'] ?? null),
                'supplier' => $supplier?->name ?? $supplierName,
                'supplier_company_id' => $supplier?->id,
                'responsible_id' => $responsible?->id,
            ];
        })->all();

        return ['rows' => $rows, 'warnings' => array_values(array_unique($warnings)), 'pages' => count($pages)];
    }

    private function positionedRows(array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            if (! isset($item[0][4], $item[0][5], $item[1]) || ! $this->text($item[1])) {
                continue;
            }
            $x = (float) $item[0][4];
            $y = (float) $item[0][5];
            $rowKey = collect(array_keys($rows))->first(fn ($existingY) => abs((float) $existingY - $y) <= 2.2);
            $key = $rowKey ?? (string) $y;
            $rows[$key][] = ['x' => $x, 'text' => trim((string) $item[1])];
        }
        uksort($rows, fn ($a, $b) => (float) $b <=> (float) $a);

        return array_map(function (array $row) {
            usort($row, fn ($a, $b) => $a['x'] <=> $b['x']);

            return $row;
        }, array_values($rows));
    }

    private function headerColumns(array $row): ?array
    {
        $columns = [];
        foreach ($row as $cell) {
            $header = $this->normalize($cell['text']);
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($columns[$field]) && $this->matchesAlias($header, $aliases)) {
                    $columns[$field] = $cell['x'];
                }
            }
        }
        $support = collect(['type', 'quantity', 'unit', 'total_cost', 'unit_cost', 'needed_by', 'status', 'supplier'])
            ->contains(fn (string $field) => isset($columns[$field]));
        if (! isset($columns['name']) || ! $support) {
            return null;
        }
        asort($columns);

        return $columns;
    }

    private function matchesAlias(string $header, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            if ($header === $alias) {
                return true;
            }
            if (! in_array($alias, ['nazwa', 'material', 'produkt', 'pozycja', 'wartosc', 'koszt', 'opis', 'email', 'status', 'stan', 'typ', 'rodzaj', 'termin', 'dostawca', 'nip'], true)
                && (Str::startsWith($header, $alias.' ') || Str::endsWith($header, ' '.$alias))) {
                return true;
            }
        }

        return false;
    }

    private function assignToColumns(array $row, array $header): array
    {
        $ordered = collect($header)->sort()->all();
        $fields = array_keys($ordered);
        $positions = array_values($ordered);
        $result = array_fill_keys(array_keys(self::ALIASES), null);
        foreach ($row as $cell) {
            $closest = 0;
            $distance = INF;
            foreach ($positions as $index => $position) {
                $current = abs($cell['x'] - $position);
                if ($current < $distance) {
                    $closest = $index;
                    $distance = $current;
                }
            }
            $field = $fields[$closest];
            $result[$field] = trim(implode(' ', array_filter([$result[$field], $cell['text']])));
        }

        return $result;
    }

    private function normalize(mixed $value): string
    {
        return Str::of((string) ($value ?? ''))->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $text = preg_replace('/[^0-9,.-]/u', '', (string) ($value ?? ''));
        if (! $text) {
            return null;
        }
        $comma = strrpos($text, ',');
        $dot = strrpos($text, '.');
        if ($comma !== false && ($dot === false || $comma > $dot)) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        } elseif ($dot !== false) {
            $text = str_replace(',', '', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function date(mixed $value): ?string
    {
        $value = $this->text($value);
        if (! $value) {
            return null;
        }
        foreach (['d.m.Y', 'd-m-Y', 'Y-m-d', 'd/m/Y', 'Y/m/d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function type(mixed $value): string
    {
        return Str::contains($this->normalize($value), ['uslug', 'service', 'robociz', 'montaz', 'praca']) ? 'service' : 'material';
    }

    private function status(mixed $value): string
    {
        $value = $this->normalize($value);
        if (Str::contains($value, ['anul', 'cancel'])) {
            return 'cancelled';
        }
        if (Str::contains($value, ['kupion', 'zakupion', 'dostarcz', 'odebran', 'purchased'])) {
            return 'purchased';
        }
        if (Str::contains($value, ['w realizacji', 'realizowan', 'w drodze', 'in progress'])) {
            return 'in_progress';
        }
        if (Str::contains($value, ['zamow', 'ordered'])) {
            return 'ordered';
        }

        return 'requested';
    }

    private function fail(string $message): never
    {
        $exception = ValidationException::withMessages(['pdf_file' => $message]);
        $exception->errorBag = 'requirementsPdf';

        throw $exception;
    }
}
