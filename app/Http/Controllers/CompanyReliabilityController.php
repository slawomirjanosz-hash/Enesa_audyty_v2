<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyReliabilityFile;
use App\Models\CompanyReliabilityReport;
use App\Models\CompanySettings;
use App\Services\CompanyRegistryLookup;
use App\Services\CompanyReliabilityAccess;
use App\Services\CompanyReliabilityAssessment;
use App\Services\FinancialHealthAssessment;
use App\Support\FinancialAmount;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyReliabilityController extends Controller
{
    private function check(Company $company, string $action = 'view'): void
    {
        abort_unless(app(CompanyReliabilityAccess::class)->allows(auth()->user(), $action, $company), 403);
    }

    public function show(Company $company, Request $request)
    {
        $this->check($company);
        $reports = $company->reliabilityReports()->with('author')->latest('id')->get();
        $lookup = $request->session()->get('reliability.'.$company->id);
        if ($lookup && data_get($lookup, 'nip') !== Company::normalizeNip($company->nip)) {
            $lookup = null;
        }
        $autoLookup = ! $lookup || abs(now()->diffInMinutes(Carbon::parse($lookup['checked_at']))) >= 15;
        $finances = [];
        foreach (CompanyReliabilityFile::where('company_id', $company->id)->whereNotNull('parsed_finances')->latest('updated_at')->latest('id')->cursor() as $file) {
            if (data_get($file->parsed_finances, 'nip') !== Company::normalizeNip($company->nip)) {
                continue;
            }
            foreach ($file->parsed_finances['rows'] as $row) {
                $finances[$row['year']] ??= $row;
            }
        }
        krsort($finances);
        $finances = array_slice(array_values($finances), 0, 3);
        $automatic = app(CompanyReliabilityAssessment::class)->assess($lookup, $finances);

        return response()->view('companies.reliability.show', compact('company', 'reports', 'lookup', 'finances', 'automatic', 'autoLookup'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function lookup(Company $company, Request $request, CompanyRegistryLookup $service)
    {
        $this->check($company, 'create');
        $data = $request->validate(['krs' => ['nullable', 'regex:/^\d{10}$/']]);
        $request->session()->put('reliability.'.$company->id, $service->lookup($company, $data['krs'] ?? null));

        if ($request->expectsJson()) {
            return response()->json(['checked' => true])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('companies.reliability.show', $company)->with('success', 'Sprawdzenie zakończone. Sprawdź wyniki i uzupełnij ocenę. Niedostępne źródło nie oznacza braku problemów.');
    }

    public function financialPreview(Company $company, Request $request)
    {
        $this->check($company, 'create');
        $rows = $request->input('finances', []);
        if (is_array($rows)) {
            foreach ($rows as &$row) {
                if (is_array($row)) {
                    foreach (['revenue', 'profit', 'equity', 'liabilities'] as $key) {
                        $row[$key] = FinancialAmount::normalize($row[$key] ?? null);
                    }
                }
            }
            unset($row);
            $request->merge(['finances' => $rows]);
        }
        $data = $request->validate([
            'finances' => ['array', 'max:3'], 'finances.*' => ['array'],
            'finances.*.year' => ['nullable', 'integer', 'min:2000', 'max:'.now()->year],
            'finances.*.revenue' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'finances.*.profit' => ['nullable', 'numeric', 'between:-999999999999999,999999999999999'],
            'finances.*.equity' => ['nullable', 'numeric', 'between:-999999999999999,999999999999999'],
            'finances.*.liabilities' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
        ]);
        $rows = array_filter($data['finances'] ?? [], fn ($row) => count(array_filter($row, fn ($value) => $value !== null && $value !== '')) > 0);
        $health = app(FinancialHealthAssessment::class)->assess($rows);

        return response()->json(['health' => $health, 'html' => view('companies.reliability.financial-health', compact('health'))->render()])->header('Cache-Control', 'private, no-store');
    }

    public function store(Company $company, Request $request)
    {
        $this->check($company, 'create');
        $auto = $request->input('status') === 'auto';
        if ($auto) {
            // Manual controls are not declarations in automatic mode.
            $request->merge(['legal' => $request->input('legal') === 'risk' ? 'risk' : 'unknown', 'krz' => $request->input('krz') === 'risk' ? 'risk' : 'unknown', 'debt' => $request->input('debt') === 'risk' ? 'risk' : 'unknown', 'verified_on' => now()->format('Y-m-d')]);
        }
        $finances = $request->input('finances');
        if (is_array($finances)) {
            foreach ($finances as &$row) {
                if (is_array($row)) {
                    foreach (['revenue', 'profit', 'equity', 'liabilities'] as $field) {
                        if (array_key_exists($field, $row)) {
                            $row[$field] = FinancialAmount::normalize($row[$field]);
                        }
                    }
                }
            }
            unset($row);
            $request->merge(['finances' => $finances]);
        }
        $data = $request->validate([
            'status' => ['required', Rule::in(['auto', 'unassessed', 'green', 'yellow', 'red'])],
            'legal' => ['required', Rule::in(['unknown', 'clear', 'risk'])],
            'krz' => ['required', Rule::in(['unknown', 'clear', 'risk'])],
            'debt' => ['required', Rule::in(['unknown', 'clear', 'risk'])],
            'evidence' => ['nullable', 'string', 'max:6000'],
            'notes' => [$auto ? 'nullable' : 'required', 'string', 'max:6000'],
            'verified_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(30)->format('Y-m-d')],
            'finances' => ['nullable', 'array', 'max:3'],
            'finances.*.year' => ['nullable', 'integer', 'min:2000', 'max:'.now()->year],
            'finances.*.revenue' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'finances.*.profit' => ['nullable', 'numeric', 'between:-999999999999999,999999999999999'],
            'finances.*.equity' => ['nullable', 'numeric', 'between:-999999999999999,999999999999999'],
            'finances.*.liabilities' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'finances.*.source' => ['nullable', 'string', 'max:1000'],
        ]);
        // One visible justification field; retain the evidence snapshot for older reports/API clients.
        $data['evidence'] = $data['evidence'] ?? ($auto ? null : ($data['notes'] ?? null));
        $data['finances'] = array_values(array_filter($data['finances'] ?? [], fn ($row) => count(array_filter($row, fn ($v) => $v !== null && $v !== '')) > 0));
        foreach ($data['finances'] as $row) {
            if (empty($row['year']) || empty($row['source'])) {
                throw ValidationException::withMessages(['finances' => 'Każdy okres finansowy wymaga roku i źródła danych.']);
            }
        }
        $lookup = $request->session()->get('reliability.'.$company->id);
        if ($lookup && (now()->diffInMinutes(Carbon::parse($lookup['checked_at']), true) > 30 || $lookup['nip'] !== Company::normalizeNip($company->nip))) {
            $lookup = null;
        }
        $automatic = app(CompanyReliabilityAssessment::class)->assess($lookup, $data['finances']);
        if ($auto) {
            $data['status'] = $automatic['status'];
            if (in_array('risk', [$data['legal'], $data['krz'], $data['debt']], true)) {
                $data['status'] = 'red';
                $automatic['status'] = 'red';
                $automatic['summary'] = 'Pracownik zgłosił zagrożenie. Wymagana weryfikacja przed współpracą.';
                $automatic['checks'][] = ['label' => 'Dodatkowe zgłoszenie pracownika', 'state' => 'risk', 'message' => 'Wskazano zagrożenie w dodatkowych kontrolach. To zgłoszenie ręczne, nie wynik API.'];
            }
            $data['notes'] = filled($data['notes'] ?? null) ? $data['notes'] : $automatic['summary'];
            $data['evidence'] = 'Automatyczne źródła: odpis aktualny KRS i wykaz VAT (wyniki i czas w raporcie); dane finansowe według wskazanych dokumentów. KRZ i prywatne rejestry długów nie zostały automatycznie sprawdzone.';
        }
        $risks = in_array('risk', [$data['legal'], $data['krz'], $data['debt']], true);
        if ($automatic['status'] === 'red' && $data['status'] !== 'red') {
            throw ValidationException::withMessages(['status' => 'Wykryto sygnał zagrożenia finansowego. Wybierz ocenę automatyczną lub czerwoną; szczegóły są w sekcji analizy.']);
        }
        if ($risks && $data['status'] !== 'red') {
            throw ValidationException::withMessages(['status' => 'Wykryte zagrożenie prawne lub zaległości wymagają czerwonego statusu.']);
        }
        if ($data['status'] === 'green') {
            $complete = $data['legal'] === 'clear' && $data['krz'] === 'clear' && $data['debt'] === 'clear'
                && filled($data['evidence']) && data_get($lookup, 'vat.state') === 'checked'
                && in_array(data_get($lookup, 'vat.status'), ['Czynny', 'Zwolniony'], true)
                && data_get($lookup, 'krs.state') !== 'identity_mismatch'
                && (empty(data_get($lookup, 'vat.krs')) || data_get($lookup, 'krs.state') === 'checked')
                && count($data['finances']) > 0;
            foreach ($data['finances'] as $row) {
                $complete = $complete && isset($row['revenue'], $row['profit'], $row['equity'], $row['liabilities'])
                    && (float) $row['equity'] >= 0 && (float) $row['profit'] >= 0;
            }
            $complete = $complete && collect($data['finances'])->max('year') >= now()->year - 2;
            if (! $complete) {
                throw ValidationException::withMessages(['status' => 'Zielona ocena wymaga aktualnego sprawdzenia VAT, potwierdzenia trzech kontroli, źródeł i pełnych danych finansowych bez straty ani ujemnego kapitału. Przy niepełnych danych wybierz „Nie oceniono” lub ostrożność.']);
            }
        }
        if (in_array('clear', [$data['legal'], $data['krz'], $data['debt']], true) && blank($data['evidence'])) {
            throw ValidationException::withMessages(['notes' => 'W uwagach podaj podstawę ręcznej oceny: rejestr lub dokument, datę i wynik sprawdzenia.']);
        }
        $snapshot = ['company' => ['name' => $company->name, 'nip' => $company->nip], 'assessment' => $data,
            'automatic' => $automatic, 'assessment_mode' => $auto ? 'automatic' : 'manual',
            'registry' => $lookup, 'author' => $request->user()->name, 'generated_at' => now()->format('d.m.Y H:i'),
            'issuer' => CompanySettings::first()?->name];
        $report = new CompanyReliabilityReport(['company_id' => $company->id, 'created_by' => $request->user()->id,
            'status' => $data['status'], 'snapshot' => $snapshot, 'stored_path' => 'private-reliability/'.Str::uuid().'.pdf']);
        $pdf = Pdf::loadView('companies.reliability.pdf', ['report' => $report, 'logo' => CompanySettings::first()?->logoDataUri()])
            ->setPaper('a4')->setOption('isRemoteEnabled', false)->output();
        abort_unless(Storage::disk('local')->put($report->stored_path, $pdf), 500, 'Nie udało się zapisać PDF.');
        $report->size = strlen($pdf);
        try {
            DB::transaction(fn () => $report->save());
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($report->stored_path);
            throw $e;
        }

        return redirect()->route('companies.reliability.show', $company)->with('success', 'Raport PDF zapisany w chronionych dokumentach firmy.');
    }

    public function download(Company $company, CompanyReliabilityReport $report)
    {
        $this->check($company);
        abort_unless($report->company_id === $company->id, 404);
        abort_unless(Storage::disk('local')->exists($report->stored_path), 404);
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'download', 'auditable_type' => CompanyReliabilityReport::class,
            'auditable_id' => $report->id, 'subject_label' => 'Poufny raport #'.$report->id]);

        return Storage::disk('local')->download($report->stored_path, $report->filename(), ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(Company $company, CompanyReliabilityReport $report)
    {
        $this->check($company, 'delete');
        abort_unless($report->company_id === $company->id, 404);
        if (Storage::disk('local')->exists($report->stored_path)) {
            abort_unless(Storage::disk('local')->delete($report->stored_path), 500, 'Nie udało się usunąć PDF.');
        }
        $report->delete();

        return redirect()->route('companies.reliability.show', $company)->with('success', 'Raport i plik PDF usunięte. Status wynika teraz z ostatniego pozostałego raportu.');
    }
}
