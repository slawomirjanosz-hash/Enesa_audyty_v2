<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyReliabilityFile;
use App\Services\CompanyReliabilityAccess;
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
        abort_unless(Storage::disk('local')->put($path, $upload->getContent()), 500);
        try {
            CompanyReliabilityFile::create(['company_id' => $company->id, 'name' => $name, 'stored_path' => $path, 'size' => $upload->getSize()]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Dokument źródłowy zapisany w chronionych dokumentach firmy. Sam zapis pliku nie uzupełnia oceny ani kwot raportu.');
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
