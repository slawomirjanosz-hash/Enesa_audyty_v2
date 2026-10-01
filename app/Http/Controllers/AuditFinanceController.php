<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditFinanceGroup;
use App\Models\AuditFinancialEntry;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class AuditFinanceController extends Controller
{
    public function storeFinancialEntry(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorize('update', $audit);
        $data = $request->validate([
            'type' => ['required', 'in:cost,invoice'],
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'supplier_company_id' => [
                'nullable', 'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query->where('company_type', 'supplier')->whereNull('archived_at')),
            ],
            'finance_group_id' => ['nullable', 'integer'],
            'entry_date' => ['required', 'date'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:planned,issued,paid'],
            'notes' => ['nullable', 'string'],
        ]);
        $data = $this->normalizeFinancialEntryRelations($audit, $data);
        $audit->financialEntries()->create($data + ['created_by' => $request->user()->id]);

        return $this->financeRedirect($audit, 'Pozycja finansowa została dodana.');
    }

    public function updateFinancialEntry(Request $request, Audit $audit, AuditFinancialEntry $entry): RedirectResponse
    {
        $this->authorize('update', $audit);
        abort_unless($entry->audit_id === $audit->id, 404);
        $data = $request->validate([
            'type' => ['required', 'in:cost,invoice'],
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'supplier_company_id' => [
                'nullable', 'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query->where('company_type', 'supplier')->whereNull('archived_at')),
            ],
            'finance_group_id' => ['nullable', 'integer'],
            'entry_date' => ['required', 'date'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:planned,issued,paid'],
            'notes' => ['nullable', 'string'],
        ]);
        $data = $this->normalizeFinancialEntryRelations($audit, $data);
        $entry->update($data);

        return $this->financeRedirect($audit, 'Pozycja finansowa została zaktualizowana.');
    }

    public function updateFinancialEntryStatus(Request $request, Audit $audit, AuditFinancialEntry $entry): JsonResponse
    {
        $this->authorize('update', $audit);
        abort_unless($entry->audit_id === $audit->id, 404);
        $data = $request->validate(['status' => ['required', 'in:planned,issued,paid']]);
        $entry->update(['status' => $data['status']]);
        $audit->load('financialEntries');

        return response()->json([
            'status' => $entry->status,
            'summary' => [
                'invoiced' => $audit->totalInvoiced(),
                'planned_invoiced' => $audit->plannedInvoiced(),
                'costs' => $audit->totalCosts(),
                'planned_costs' => $audit->plannedCosts(),
                'result' => $audit->result(),
            ],
        ]);
    }

    public function importFinancialEntries(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorize('update', $audit);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'type' => ['required', 'in:cost,invoice'],
            'finance_group_id' => ['nullable', 'integer'],
            'new_group_name' => ['nullable', 'string', 'max:120'],
        ]);

        $groupId = $data['type'] === 'invoice'
            ? $this->issuedFinanceGroup($audit)->id
            : $this->resolveFinanceGroup($audit, $data['finance_group_id'] ?? null, $data['new_group_name'] ?? null);
        $sheet = Excel::toCollection(null, $data['file'])->first();
        if (! $sheet || $sheet->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'Plik nie zawiera danych.']);
        }

        $rows = $sheet->map(fn ($row) => collect($row)->values());
        $headerRowIndex = $rows->search(function ($row) {
            $candidate = $row->map(fn ($value) => $this->normalizeFinanceHeader($value));

            return $this->findFinanceColumn($candidate, ['data', 'data ksiegowania', 'data dokumentu', 'data wystawienia']) !== null
                && $this->findFinanceColumn($candidate, ['kwota netto', 'netto', 'wartosc netto', 'kwota wn', 'kwota', 'wartosc']) !== null;
        });
        if ($headerRowIndex === false) {
            throw ValidationException::withMessages(['file' => 'Nie znaleziono wiersza nagłówków.']);
        }
        $headers = $rows[$headerRowIndex]->map(fn ($value) => $this->normalizeFinanceHeader($value));
        $columns = [
            'date' => $this->findFinanceColumn($headers, ['data', 'data ksiegowania', 'data dokumentu', 'data wystawienia']),
            'supplier' => $this->findFinanceColumn($headers, ['podmiot', 'przedmiot', 'dostawca', 'kontrahent', 'klient']),
            'document' => $this->findFinanceColumn($headers, ['dokument', 'nr dokumentu', 'numer dokumentu', 'numer faktury', 'nr faktury']),
            'amount' => $this->findFinanceColumn($headers, ['kwota netto', 'netto', 'wartosc netto', 'kwota wn', 'kwota', 'wartosc']),
            'description' => $this->findFinanceColumn($headers, ['opis', 'nazwa', 'tytul', 'pozycja']),
            'status' => $this->findFinanceColumn($headers, ['status', 'stan']),
            'payment_date' => $this->findFinanceColumn($headers, ['data platnosci', 'termin platnosci', 'platnosc do']),
        ];
        if ($columns['date'] === null || $columns['amount'] === null) {
            throw ValidationException::withMessages(['file' => 'Plik musi zawierać kolumny Data oraz Kwota (np. Kwota netto).']);
        }

        $report = ['inserted' => 0, 'duplicates' => 0, 'invalid' => 0, 'inserted_amount' => 0.0, 'duplicate_amount' => 0.0, 'preview' => [], 'duplicate_preview' => []];
        $knownFingerprints = $audit->financialEntries()->whereNotNull('import_fingerprint')->pluck('import_fingerprint')->flip();

        foreach ($rows->slice($headerRowIndex + 1) as $rowIndex => $row) {
            if ($row->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
                continue;
            }
            $date = $this->parseFinanceDate($this->financeCell($row, $columns['date']));
            $amount = $this->parseFinanceAmount($this->financeCell($row, $columns['amount']));
            if (! $date || $amount === null || $amount < 0) {
                $report['invalid']++;

                continue;
            }

            $supplier = $this->nullableFinanceText($this->financeCell($row, $columns['supplier']));
            $supplierCompanyId = $supplier
                ? Company::suppliers()->whereRaw('LOWER(name) = LOWER(?)', [$supplier])->value('id')
                : null;
            $document = $this->nullableFinanceText($this->financeCell($row, $columns['document']));
            $description = $this->nullableFinanceText($this->financeCell($row, $columns['description']));
            $paymentDate = $this->parseFinanceDate($this->financeCell($row, $columns['payment_date']));
            $status = $this->parseFinanceStatus($this->financeCell($row, $columns['status']), $data['type']);
            $name = $description ?: $supplier ?: $document ?: ($data['type'] === 'cost' ? 'Koszt z importu' : 'Faktura z importu');
            $fingerprint = $this->financeFingerprint($data['type'], $document, $date, $supplier, $amount, $description);
            $previewRow = ['row' => $rowIndex + 1, 'date' => $date, 'document' => $document, 'name' => $name, 'amount' => $amount];

            if ($knownFingerprints->has($fingerprint)) {
                $report['duplicates']++;
                $report['duplicate_amount'] += $amount;
                if (count($report['duplicate_preview']) < 15) {
                    $report['duplicate_preview'][] = $previewRow;
                }

                continue;
            }

            try {
                $audit->financialEntries()->create([
                    'finance_group_id' => $groupId,
                    'type' => $data['type'],
                    'name' => Str::limit($name, 255, ''),
                    'document_number' => $document ? Str::limit($document, 100, '') : null,
                    'supplier' => $data['type'] === 'invoice' ? null : ($supplier ? Str::limit($supplier, 255, '') : null),
                    'supplier_company_id' => $data['type'] === 'invoice' ? null : $supplierCompanyId,
                    'entry_date' => $date,
                    'payment_date' => $paymentDate,
                    'amount' => $amount,
                    'status' => $status,
                    'source' => 'excel_import',
                    'import_row_order' => $rowIndex + 1,
                    'import_fingerprint' => $fingerprint,
                    'notes' => $description,
                    'created_by' => $request->user()->id,
                ]);
                $knownFingerprints->put($fingerprint, true);
                $report['inserted']++;
                $report['inserted_amount'] += $amount;
                if (count($report['preview']) < 15) {
                    $report['preview'][] = $previewRow;
                }
            } catch (Throwable $exception) {
                if ($audit->financialEntries()->where('import_fingerprint', $fingerprint)->exists()) {
                    $knownFingerprints->put($fingerprint, true);
                    $report['duplicates']++;
                    $report['duplicate_amount'] += $amount;

                    continue;
                }
                throw $exception;
            }
        }

        return $this->financeRedirect($audit, "Import zakończony: dodano {$report['inserted']}, pominięto duplikatów {$report['duplicates']} i błędnych wierszy {$report['invalid']}.")
            ->with('finance_import_report', $report);
    }

    public function storeFinanceGroup(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorize('update', $audit);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $audit->financeGroups()->firstOrCreate(['name' => trim($data['name'])]);

        return $this->financeRedirect($audit, 'Grupa finansowa została zapisana.');
    }

    public function bulkUpdateFinancialEntries(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorize('update', $audit);
        $data = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', 'in:planned,issued,paid,delete'],
        ]);
        $entries = $audit->financialEntries()->whereKey($data['entry_ids']);
        $count = (clone $entries)->count();
        if ($count !== count($data['entry_ids'])) {
            throw ValidationException::withMessages(['entry_ids' => 'Co najmniej jedna pozycja nie należy do tego audytu.']);
        }
        if ($data['action'] === 'delete') {
            DB::transaction(fn () => $entries->get()->each->delete());
            $message = "Usunięto {$count} pozycji finansowych.";
        } else {
            DB::transaction(fn () => $entries->get()->each->update(['status' => $data['action']]));
            $message = "Zmieniono status {$count} pozycji finansowych.";
        }

        return $this->financeRedirect($audit, $message);
    }

    public function destroyFinanceGroup(Audit $audit, AuditFinanceGroup $group): RedirectResponse
    {
        $this->authorize('update', $audit);
        abort_unless($group->audit_id === $audit->id, 404);
        $group->delete();

        return $this->financeRedirect($audit, 'Grupa została usunięta. Pozycje finansowe pozostały w rejestrze.');
    }

    public function destroyFinancialEntry(Audit $audit, AuditFinancialEntry $entry): RedirectResponse
    {
        $this->authorize('update', $audit);
        abort_unless($entry->audit_id === $audit->id, 404);
        $entry->delete();

        return $this->financeRedirect($audit, 'Pozycja finansowa została usunięta.');
    }

    private function validateFinanceGroup(Audit $audit, ?int $groupId): void
    {
        if ($groupId && ! $audit->financeGroups()->whereKey($groupId)->exists()) {
            throw ValidationException::withMessages(['finance_group_id' => 'Wybrana grupa nie należy do tego audytu.']);
        }
    }

    private function normalizeFinancialEntryRelations(Audit $audit, array $data): array
    {
        if ($data['type'] === 'invoice') {
            $data['finance_group_id'] = $this->issuedFinanceGroup($audit)->id;
            $data['supplier'] = null;
            $data['supplier_company_id'] = null;

            return $data;
        }

        $this->validateFinanceGroup($audit, $data['finance_group_id'] ?? null);
        if (! empty($data['supplier_company_id'])) {
            $data['supplier'] = Company::find($data['supplier_company_id'])?->name;
        }

        return $data;
    }

    private function issuedFinanceGroup(Audit $audit): AuditFinanceGroup
    {
        return $audit->financeGroups()->firstOrCreate(['name' => 'Wystawione']);
    }

    private function resolveFinanceGroup(Audit $audit, ?int $groupId, ?string $newGroupName): ?int
    {
        if ($newGroupName && trim($newGroupName) !== '') {
            return $audit->financeGroups()->firstOrCreate(['name' => trim($newGroupName)])->id;
        }
        $this->validateFinanceGroup($audit, $groupId);

        return $groupId;
    }

    private function normalizeFinanceHeader(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }

    private function findFinanceColumn($headers, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $index = $headers->search($alias, true);
            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }

    private function financeCell($row, ?int $column): mixed
    {
        return $column === null ? null : $row->get($column);
    }

    private function nullableFinanceText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function parseFinanceDate(mixed $value): ?string
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
                    // Try the next common spreadsheet date format.
                }
            }

            return Carbon::parse($text)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseFinanceAmount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }
        $text = preg_replace('/[^0-9,.-]/u', '', (string) ($value ?? ''));
        if ($text === '' || $text === null) {
            return null;
        }
        $lastComma = strrpos($text, ',');
        $lastDot = strrpos($text, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif ($lastDot !== false) {
            $text = str_replace(',', '', $text);
        }

        return is_numeric($text) ? round((float) $text, 2) : null;
    }

    private function parseFinanceStatus(mixed $value, string $type): string
    {
        $status = $this->normalizeFinanceHeader($value);
        if (Str::contains($status, ['oplac', 'zapla', 'paid', 'rozlicz'])) {
            return 'paid';
        }
        if (Str::contains($status, ['wystaw', 'zaksi', 'issued', 'otrzym'])) {
            return 'issued';
        }

        return $type === 'cost' && $status === '' ? 'issued' : 'planned';
    }

    private function financeFingerprint(string $type, ?string $document, string $date, ?string $supplier, float $amount, ?string $description): string
    {
        $normalizedDocument = $this->normalizeFinanceHeader($document);
        $identity = $normalizedDocument !== ''
            ? "document|{$normalizedDocument}|".number_format($amount, 2, '.', '')
            : implode('|', ['row', $date, $this->normalizeFinanceHeader($supplier), number_format($amount, 2, '.', ''), $this->normalizeFinanceHeader($description)]);

        return hash('sha256', $type.'|'.$identity);
    }

    private function financeRedirect(Audit $audit, string $message): RedirectResponse
    {
        return redirect()->route('audits.show', ['audit' => $audit, 'tab' => 'finances'])->with('success', $message);
    }
}
