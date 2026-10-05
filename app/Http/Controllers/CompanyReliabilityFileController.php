<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyReliabilityFile;
use App\Services\CompanyReliabilityAccess;
use App\Services\DocumentQuotaService;
use App\Services\FinancialStatementPdf;
use App\Services\FinancialStatementXml;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyReliabilityFileController extends Controller
{
    private function check(Company $company, string $action = 'view'): void
    {
        abort_unless(app(CompanyReliabilityAccess::class)->allows(auth()->user(), $action, $company), 403);
    }

    public function store(Company $company, Request $request)
    {
        $this->check($company, 'create');
        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimetypes:application/pdf,application/xml,text/xml,text/plain,application/xhtml+xml,text/html']]);
        $upload = $request->file('file');
        $extension = strtolower($upload->getClientOriginalExtension());
        if (! in_array($extension, ['pdf', 'xml', 'xhtml'], true)) {
            throw ValidationException::withMessages(['file' => 'Dozwolone pliki: PDF, XML, XHTML.']);
        }
        app(DocumentQuotaService::class)->assertAdditional($request->user()->id, $upload->getSize());
        $name = Str::limit(preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '_', $upload->getClientOriginalName()), 200, '');
        $path = 'private-reliability-sources/'.Str::uuid().'.'.$extension;
        $parsed = null;
        $warning = null;
        if ($extension === 'xml') {
            try {
                $parsed = $this->extract($company, $upload->getContent(), $name);
            } catch (ValidationException $exception) {
                $warning = collect($exception->errors())->flatten()->implode(' ');
            }
        }
        if ($extension === 'pdf') {
            try {
                $parsed = app(FinancialStatementPdf::class)->parse($upload->getRealPath(), $company);
            } catch (ValidationException $exception) {
                $warning = collect($exception->errors())->flatten()->implode(' ');
            }
        }
        abort_unless(Storage::disk('local')->put($path, $upload->getContent()), 500);
        try {
            CompanyReliabilityFile::create(['company_id' => $company->id, 'name' => $name, 'stored_path' => $path, 'size' => $upload->getSize(), 'parsed_finances' => $parsed]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('companies.reliability.show', $company)->with('success', isset($parsed['pdf_proposals'])
            ? 'Zapisano PDF. Poniżej dokumentów źródłowych sprawdź odczyt AI i potwierdź uzupełnienie pól. Ocena firmy nie została zmieniona.'
            : ($parsed
            ? 'Zapisano XML i uzupełniono pola finansowe. Sprawdź kwoty i lata (w tym rok danych porównawczych), następnie zapisz raport. Ocena firmy nie została zmieniona.'
            : 'Zapisano załącznik bez uzupełnienia pól. '.($warning ?? 'XHTML pozostaje załącznikiem.')));
    }

    public function import(Company $company, CompanyReliabilityFile $file, Request $request)
    {
        $this->check($company, 'create');
        abort_unless($file->company_id === $company->id, 404);
        $extension = strtolower(pathinfo($file->stored_path, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, ['xml', 'pdf'], true), 422);
        abort_unless(Storage::disk('local')->exists($file->stored_path), 404);
        if ($extension === 'pdf') {
            if ($request->boolean('confirm_pdf')) {
                $parsed = $file->parsed_finances;
                abort_unless(($parsed['nip'] ?? null) === Company::normalizeNip($company->nip) && ! empty($parsed['pdf_proposals']), 422);
                $parsed['rows'] = array_map(function ($row) use ($file) {
                    $row['source'] = mb_substr($file->name.'; '.$row['source'], 0, 1000);

                    return $row;
                }, $parsed['pdf_proposals']);
                $parsed['confirmed_by'] = $request->user()->id;
                $parsed['confirmed_at'] = now()->toIso8601String();
                $file->parsed_finances = $parsed;
            } else {
                $file->parsed_finances = app(FinancialStatementPdf::class)->parse(Storage::disk('local')->path($file->stored_path), $company);
            }
            $file->updated_at = now();
            $file->save();

            return back()->with('success', $request->boolean('confirm_pdf') ? 'Uzupełniono pola z potwierdzonego PDF. Sprawdź formularz i zapisz raport.' : 'Odczyt PDF gotowy do sprawdzenia. Potwierdź kwoty poniżej dokumentów źródłowych.');
        }
        $file->parsed_finances = $this->extract($company, Storage::disk('local')->get($file->stored_path), $file->name);
        $file->updated_at = now();
        $file->save();

        return redirect()->route('companies.reliability.show', $company)->with('success', 'Uzupełniono pola z zapisanego XML. Sprawdź kwoty i rok porównawczy, następnie zapisz raport.');
    }

    private function extract(Company $company, string $xml, string $name): array
    {
        $nip = (string) Company::normalizeNip($company->nip);

        return ['nip' => $nip, 'rows' => app(FinancialStatementXml::class)->parse($xml, $nip, $name)];
    }

    public function download(Company $company, CompanyReliabilityFile $file)
    {
        $this->check($company);
        abort_unless($file->company_id === $company->id, 404);
        abort_unless(Storage::disk('local')->exists($file->stored_path), 404);
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'download', 'auditable_type' => CompanyReliabilityFile::class,
            'auditable_id' => $file->id, 'subject_label' => 'Poufny dokument źródłowy #'.$file->id]);

        return Storage::disk('local')->download($file->stored_path, $file->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Company $company, CompanyReliabilityFile $file)
    {
        $this->check($company, 'delete');
        abort_unless($file->company_id === $company->id, 404);
        if (Storage::disk('local')->exists($file->stored_path)) {
            abort_unless(Storage::disk('local')->delete($file->stored_path), 500);
        }
        $file->delete();

        return back()->with('success', 'Dokument źródłowy i plik z dysku zostały usunięte.');
    }
}
