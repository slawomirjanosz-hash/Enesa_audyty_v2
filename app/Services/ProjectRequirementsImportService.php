<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ProjectRequirementsImportService
{
    private const ALIASES = [
        'name' => ['nazwa', 'nazwa pozycji', 'nazwa materialu', 'material', 'towar', 'produkt', 'asortyment', 'pozycja', 'item', 'item name', 'product', 'service name', 'opis pozycji'],
        'type' => ['rodzaj', 'typ', 'typ pozycji', 'rodzaj pozycji', 'kategoria', 'category', 'type'],
        'quantity' => ['ilosc', 'ilosc zamawiana', 'zapotrzebowanie', 'liczba', 'qty', 'quantity'],
        'unit' => ['jednostka', 'jednostka miary', 'jm', 'j m', 'unit'],
        'total_cost' => ['szacowany koszt', 'koszt laczny', 'koszt netto', 'wartosc laczna', 'wartosc netto', 'kwota netto', 'wartosc', 'koszt', 'total', 'total cost'],
        'unit_cost' => ['cena', 'cena jednostkowa', 'cena netto', 'cena jedn', 'koszt jednostkowy', 'unit price', 'price'],
        'needed_by' => ['potrzebne do', 'termin', 'termin dostawy', 'data dostawy', 'wymagane do', 'deadline', 'delivery date'],
        'status' => ['status', 'stan', 'etap', 'state'],
        'supplier' => ['dostawca', 'kontrahent', 'producent', 'vendor', 'supplier'],
        'supplier_nip' => ['nip dostawcy', 'nip kontrahenta', 'nip', 'tax id'],
        'responsible_email' => ['email odpowiedzialnego', 'e mail odpowiedzialnego', 'email osoby', 'email', 'responsible email'],
        'responsible_name' => ['osoba odpowiedzialna', 'odpowiedzialny', 'prowadzacy', 'owner', 'responsible'],
        'description' => ['opis', 'opis uwagi', 'uwagi', 'komentarz', 'specyfikacja', 'notes', 'description'],
    ];

    public function import(Project $project, UploadedFile $file, User $actor): array
    {
        $workbook = Excel::toCollection(null, $file);
        if ($workbook->isEmpty()) {
            $this->fail('Plik nie zawiera żadnych arkuszy ani danych.');
        }

        $parsedRows = collect();
        $invalid = 0;
        $recognizedSheets = 0;

        foreach ($workbook as $sheetIndex => $sheet) {
            $rows = collect($sheet)->map(fn ($row) => collect($row)->values());
            $header = $this->findHeader($rows);
            if (! $header) {
                continue;
            }
            $recognizedSheets++;
            [$headerRowIndex, $columns] = $header;

            foreach ($rows->slice($headerRowIndex + 1) as $rowIndex => $row) {
                if ($row->filter(fn ($value) => trim((string) ($value ?? '')) !== '')->isEmpty()) {
                    continue;
                }
                $parsed = $this->parseRow($row, $columns, $sheetIndex + 1, $rowIndex + 1);
                if (! $parsed) {
                    $invalid++;

                    continue;
                }
                $parsedRows->push($parsed);
            }
        }

        if ($recognizedSheets === 0) {
            $this->fail('Nie znaleziono tabeli materiałów. Plik musi zawierać kolumnę z nazwą oraz np. ilością, jednostką, rodzajem albo kosztem.');
        }
        if ($parsedRows->isEmpty() && $invalid === 0) {
            $this->fail('Rozpoznano nagłówki, ale plik nie zawiera żadnych pozycji do importu.');
        }

        return $this->persist($project, $parsedRows, $invalid, $recognizedSheets, $actor);
    }

    private function findHeader(Collection $rows): ?array
    {
        $best = null;
        foreach ($rows->take(40) as $rowIndex => $row) {
            $headers = $row->map(fn ($value) => $this->normalize($value));
            $columns = collect(array_keys(self::ALIASES))
                ->mapWithKeys(fn (string $field) => [$field => $this->findColumn($headers, self::ALIASES[$field])])
                ->all();
            $score = collect($columns)->filter(fn ($column) => $column !== null)->count();
            $hasSupportingColumn = collect(['type', 'quantity', 'unit', 'total_cost', 'unit_cost', 'needed_by', 'status', 'supplier', 'supplier_nip'])
                ->contains(fn (string $field) => $columns[$field] !== null);
            if ($columns['name'] !== null && $hasSupportingColumn && $score >= 2 && (! $best || $score > $best[2])) {
                $best = [(int) $rowIndex, $columns, $score];
            }
        }

        return $best ? [$best[0], $best[1]] : null;
    }

    private function findColumn(Collection $headers, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $index = $headers->search($alias, true);
            if ($index !== false) {
                return (int) $index;
            }
        }
        foreach ($aliases as $alias) {
            if (mb_strlen($alias) < 4 || in_array($alias, ['nazwa', 'material', 'produkt', 'pozycja', 'wartosc', 'koszt', 'opis', 'email', 'status', 'stan', 'typ', 'rodzaj', 'termin', 'dostawca', 'nip'], true)) {
                continue;
            }
            $index = $headers->search(fn (string $header) => Str::startsWith($header, $alias.' ') || Str::endsWith($header, ' '.$alias));
            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }

    private function parseRow(Collection $row, array $columns, int $sheet, int $rowNumber): ?array
    {
        $name = $this->text($this->cell($row, $columns['name']));
        if (! $name) {
            return null;
        }
        $quantityValue = $this->cell($row, $columns['quantity']);
        $quantity = $quantityValue === null || trim((string) $quantityValue) === '' ? 1.0 : $this->number($quantityValue);
        if ($quantity === null || $quantity <= 0) {
            return null;
        }

        $type = $this->type($this->cell($row, $columns['type']));
        $unit = $this->text($this->cell($row, $columns['unit']));
        if (! $unit || is_numeric(str_replace(',', '.', $unit))) {
            $unit = $type === 'service' ? 'usł.' : 'szt.';
        }
        $totalCostValue = $this->cell($row, $columns['total_cost']);
        $unitCostValue = $this->cell($row, $columns['unit_cost']);
        $cost = $this->number($totalCostValue);
        if ($cost === null) {
            $unitCost = $this->number($unitCostValue);
            $cost = $unitCost === null ? null : round($unitCost * $quantity, 2);
        }
        $hasCostValue = trim((string) ($totalCostValue ?? '')) !== '' || trim((string) ($unitCostValue ?? '')) !== '';
        if (($hasCostValue && $cost === null) || ($cost !== null && $cost < 0)) {
            return null;
        }
        $neededByValue = $this->cell($row, $columns['needed_by']);
        $neededBy = $this->date($neededByValue);
        if (trim((string) ($neededByValue ?? '')) !== '' && $neededBy === null) {
            return null;
        }

        return [
            'type' => $type,
            'name' => Str::limit($name, 255, ''),
            'description' => $this->text($this->cell($row, $columns['description'])),
            'quantity' => round($quantity, 2),
            'unit' => Str::limit($unit, 30, ''),
            'estimated_cost' => $cost === null ? null : round($cost, 2),
            'needed_by' => $neededBy,
            'status' => $this->status($this->cell($row, $columns['status'])),
            'supplier' => $this->text($this->cell($row, $columns['supplier'])),
            'supplier_nip' => preg_replace('/\D+/', '', (string) ($this->cell($row, $columns['supplier_nip']) ?? '')),
            'responsible_email' => Str::lower($this->text($this->cell($row, $columns['responsible_email'])) ?? ''),
            'responsible_name' => $this->text($this->cell($row, $columns['responsible_name'])),
            'sheet' => $sheet,
            'row' => $rowNumber,
        ];
    }

    private function persist(Project $project, Collection $rows, int $invalid, int $recognizedSheets, User $actor): array
    {
        $eligibleIds = $project->members()->pluck('users.id')->push($project->manager_id)->filter()->unique();
        $people = User::whereIn('id', $eligibleIds)->get();
        $peopleByEmail = $people->keyBy(fn (User $user) => Str::lower($user->email));
        $peopleByName = $people->groupBy(fn (User $user) => $this->normalize($user->name));
        $suppliers = Company::suppliers()->active()->get();
        $suppliersByNip = $suppliers->filter(fn (Company $supplier) => $supplier->nip)->keyBy(fn (Company $supplier) => preg_replace('/\D+/', '', $supplier->nip));
        $suppliersByName = $suppliers->groupBy(fn (Company $supplier) => $this->normalize($supplier->name));
        $known = $project->requirements()->get()->mapWithKeys(fn (ProjectRequirement $item) => [$this->fingerprint([
            'type' => $item->type, 'name' => $item->name, 'description' => $item->description,
            'quantity' => (float) $item->quantity, 'unit' => $item->displayUnit(),
            'estimated_cost' => $item->estimated_cost === null ? null : (float) $item->estimated_cost,
            'needed_by' => $item->needed_by?->format('Y-m-d'), 'supplier' => $item->supplier,
        ]) => true]);
        $report = ['inserted' => 0, 'duplicates' => 0, 'invalid' => $invalid, 'unassigned' => 0, 'unmatched_suppliers' => 0, 'sheets' => $recognizedSheets, 'preview' => []];

        DB::transaction(function () use ($rows, $project, $actor, $peopleByEmail, $peopleByName, $suppliersByNip, $suppliersByName, $known, &$report) {
            foreach ($rows as $row) {
                $responsible = $row['responsible_email'] !== '' ? $peopleByEmail->get($row['responsible_email']) : null;
                if (! $responsible && $row['responsible_name']) {
                    $matches = $peopleByName->get($this->normalize($row['responsible_name']), collect());
                    $responsible = $matches->count() === 1 ? $matches->first() : null;
                }
                if (! $responsible && ($row['responsible_email'] !== '' || $row['responsible_name'])) {
                    $report['unassigned']++;
                }

                $supplier = $row['supplier_nip'] !== '' ? $suppliersByNip->get($row['supplier_nip']) : null;
                if (! $supplier && $row['supplier']) {
                    $matches = $suppliersByName->get($this->normalize($row['supplier']), collect());
                    $supplier = $matches->count() === 1 ? $matches->first() : null;
                }
                $row['supplier'] = $supplier?->name ?? $row['supplier'];
                $fingerprint = $this->fingerprint($row);
                if ($known->has($fingerprint)) {
                    $report['duplicates']++;

                    continue;
                }
                if (! $supplier && ($row['supplier_nip'] !== '' || $row['supplier'])) {
                    $report['unmatched_suppliers']++;
                }

                $item = $project->requirements()->create([
                    'type' => $row['type'], 'name' => $row['name'], 'description' => $row['description'],
                    'quantity' => $row['quantity'], 'unit' => $row['unit'], 'estimated_cost' => $row['estimated_cost'],
                    'needed_by' => $row['needed_by'], 'status' => $row['status'],
                    'supplier' => $row['supplier'], 'supplier_company_id' => $supplier?->id,
                    'responsible_id' => $responsible?->id, 'created_by' => $actor->id,
                ]);
                $known->put($fingerprint, true);
                $report['inserted']++;
                if (count($report['preview']) < 12) {
                    $report['preview'][] = ['name' => $item->name, 'quantity' => $item->formattedQuantity().' '.$item->displayUnit(), 'sheet' => $row['sheet'], 'row' => $row['row']];
                }
            }
        });

        return $report;
    }

    private function cell(Collection $row, ?int $column): mixed
    {
        return $column === null ? null : $row->get($column);
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
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            }
            $text = trim((string) $value);
            foreach (['d.m.Y', 'd-m-Y', 'Y-m-d', 'd/m/Y', 'Y/m/d'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $text)->format('Y-m-d');
                } catch (Throwable) {
                }
            }

            return Carbon::parse($text)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function type(mixed $value): string
    {
        $value = $this->normalize($value);

        return Str::contains($value, ['uslug', 'service', 'robociz', 'montaz', 'praca']) ? 'service' : 'material';
    }

    private function status(mixed $value): string
    {
        $value = $this->normalize($value);
        if (Str::contains($value, ['planowan', 'plan', 'planned', 'budget'])) {
            return 'planned';
        }
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

    private function fingerprint(array $row): string
    {
        return hash('sha256', implode('|', [
            $row['type'], $this->normalize($row['name']), number_format((float) $row['quantity'], 2, '.', ''),
            $this->normalize($row['unit']), $row['estimated_cost'] === null ? '' : number_format((float) $row['estimated_cost'], 2, '.', ''),
            $row['needed_by'] ?? '', $this->normalize($row['supplier'] ?? ''), $this->normalize($row['description'] ?? ''),
        ]));
    }

    private function fail(string $message): never
    {
        $exception = ValidationException::withMessages(['file' => $message]);
        $exception->errorBag = 'requirementsImport';

        throw $exception;
    }
}
