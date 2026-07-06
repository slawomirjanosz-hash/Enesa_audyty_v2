<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            auth()->user()->hasAnyRole(['superadmin', 'admin', 'auditor_senior']),
            403,
            'Brak uprawnień do przeglądania wszystkich dokumentów.'
        );

        $docs = Document::with(['company', 'offer', 'uploader'])
            ->orderByDesc('updated_at')
            ->get();

        // Group documents by company name
        $documents = $docs->groupBy(function ($doc) {
            return $doc->company?->name ?? 'Brak firmy';
        })->sortKeys();

        return view('documents.index', compact('documents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $companyFolder = $company->folderSlug();
        $relativePath = 'documents/' . $companyFolder . '/' . $safeName;

        Storage::disk('local')->put($relativePath, file_get_contents($file->getRealPath()));

        Document::create([
            'company_id' => $company->id,
            'type' => 'upload',
            'original_filename' => $originalName,
            'stored_path' => $relativePath,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Plik został wgrany.');
    }

    public function download(Document $document)
    {
        if (!Storage::disk('local')->exists($document->stored_path)) {
            abort(404, 'Plik nie istnieje na dysku.');
        }

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function destroy(Document $document): RedirectResponse
    {
        if (Storage::disk('local')->exists($document->stored_path)) {
            Storage::disk('local')->delete($document->stored_path);
        }

        $document->delete();

        return redirect()->back()->with('success', 'Dokument został usunięty.');
    }
}
