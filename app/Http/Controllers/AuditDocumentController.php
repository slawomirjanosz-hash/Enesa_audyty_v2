<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditDocumentFolder;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuditDocumentController extends Controller
{
    public function storeDocument(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorize('update', $audit);
        $data = $request->validate([
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'required_without:files'],
            'files' => ['nullable', 'array', 'min:1', 'max:20', 'required_without:file'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
            'audit_document_folder_id' => ['nullable', 'integer', 'exists:audit_document_folders,id'],
        ]);
        $folder = ! empty($data['audit_document_folder_id'])
            ? AuditDocumentFolder::where('audit_id', $audit->id)->findOrFail($data['audit_document_folder_id'])
            : null;
        $files = $request->hasFile('files') ? $request->file('files') : [$request->file('file')];
        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $safeName = now()->format('YmdHis').'_'.Str::random(10).'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
            $relativePath = 'audits/'.$audit->id.'/'.$safeName;
            Storage::disk('local')->put($relativePath, $file->getContent());
            Document::create([
                'audit_id' => $audit->id,
                'company_id' => $audit->company_id,
                'audit_document_folder_id' => $folder?->id,
                'type' => 'upload',
                'original_filename' => $originalName,
                'stored_path' => $relativePath,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('audits.show', ['audit' => $audit, 'tab' => 'documents'])
            ->with('success', count($files) === 1 ? 'Dokument audytu został dodany i zapisany.' : 'Dodano '.count($files).' dokumentów audytu.');
    }

    public function storeFolder(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorize('update', $audit);
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('audit_document_folders')->where('audit_id', $audit->id)]]);
        $audit->documentFolders()->create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('audits.show', ['audit' => $audit, 'tab' => 'documents'])->with('success', 'Folder został utworzony.');
    }

    public function destroyFolder(Audit $audit, AuditDocumentFolder $folder): RedirectResponse
    {
        $this->authorize('update', $audit);
        abort_unless($folder->audit_id === $audit->id, 404);
        abort_if($folder->documents()->exists(), 422, 'Najpierw przenieś lub usuń dokumenty z folderu.');
        $folder->delete();

        return redirect()->route('audits.show', ['audit' => $audit, 'tab' => 'documents']);
    }

    public function move(Request $request, Audit $audit, Document $document): RedirectResponse
    {
        $this->authorize('update', $audit);
        abort_unless($document->audit_id === $audit->id, 404);
        $data = $request->validate(['audit_document_folder_id' => ['nullable', 'integer']]);
        if (! empty($data['audit_document_folder_id'])) {
            $audit->documentFolders()->findOrFail($data['audit_document_folder_id']);
        }
        $document->update(['audit_document_folder_id' => $data['audit_document_folder_id'] ?? null]);

        return redirect()->route('audits.show', ['audit' => $audit, 'tab' => 'documents']);
    }

    public function downloadDocument(Audit $audit, Document $document)
    {
        $this->authorize('view', $audit);
        abort_unless($document->audit_id === $audit->id, 404);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function destroyDocument(Audit $audit, Document $document): RedirectResponse
    {
        $this->authorize('update', $audit);
        abort_unless($document->audit_id === $audit->id, 404);
        Storage::disk('local')->delete($document->stored_path);
        $document->delete();

        return redirect()->back()->with('success', 'Dokument audytu został usunięty.');
    }
}
