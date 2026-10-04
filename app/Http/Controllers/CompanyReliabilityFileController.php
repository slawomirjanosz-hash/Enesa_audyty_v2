<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyReliabilityFile;
use App\Services\CompanyReliabilityAccess;
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
        abort_unless(Storage::disk('local')->put($path, $upload->getContent()), 500);
        try {
            CompanyReliabilityFile::create(['company_id' => $company->id, 'name' => $name, 'stored_path' => $path, 'size' => $upload->getSize(), 'parsed_finances' => $parsed]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('companies.reliability.show', $company)->with('success', $parsed
            ? 'Zapisano XML i uzupełniono pola finansowe. Sprawdź kwoty i lata (w tym rok danych porównawczych), następnie zapisz raport. Ocena firmy nie została zmieniona.'
            : 'Zapisano załącznik bez uzupełnienia pól. '.($warning ?? 'Automatyczny odczyt dotyczy XML JednostkaInna, nie PDF ani XHTML.'));
    }

    public function import(Company $company, CompanyReliabilityFile $file)
    {
        $this->check($company, 'create');
        abort_unless($file->company_id === $company->id, 404);
        abort_unless(strtolower(pathinfo($file->stored_path, PATHINFO_EXTENSION)) === 'xml', 422);
        abort_unless(Storage::disk('local')->exists($file->stored_path), 404);
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
