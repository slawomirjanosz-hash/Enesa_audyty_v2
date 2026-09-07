<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\Document;
use App\Models\ProjectDocumentShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicProjectDocumentFolderController extends Controller
{
    public function show(ProjectDocumentShare $share): View
    {
        $this->ensureAvailable($share);
        $share->load(['folder.project.company', 'folder.documents']);
        $companySettings = CompanySettings::first();

        return view('projects.public-document-folder', compact('share', 'companySettings'));
    }

    public function upload(Request $request, ProjectDocumentShare $share): RedirectResponse
    {
        $this->ensureAvailable($share);
        abort_unless($share->allowsUpload(), 403);
        $share->load('folder.project');
        $data = $request->validate([
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'required_without:files'],
            'files' => ['nullable', 'array', 'min:1', 'max:20', 'required_without:file'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
        ]);
        $files = $request->hasFile('files') ? $request->file('files') : [$request->file('file')];
        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $safeName = now()->format('YmdHis').'_'.Str::random(16).'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
            $relativePath = 'projects/'.$share->folder->project_id.'/shared/'.$share->folder->id.'/'.$safeName;
            Storage::disk('local')->put($relativePath, $file->getContent());
            Document::create([
                'project_id' => $share->folder->project_id,
                'company_id' => $share->folder->project->company_id,
                'project_document_folder_id' => $share->folder->id,
                'type' => 'upload',
                'original_filename' => $originalName,
                'stored_path' => $relativePath,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => null,
            ]);
        }

        $message = count($files) === 1 ? 'Plik został dodany.' : 'Dodano '.count($files).' plików.';

        return redirect(URL::signedRoute('public.project-documents.show', $share))->with('success', $message);
    }

    public function download(ProjectDocumentShare $share, Document $document)
    {
        $this->ensureAvailable($share);
        abort_unless($document->project_document_folder_id === $share->project_document_folder_id, 404);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    private function ensureAvailable(ProjectDocumentShare $share): void
    {
        abort_unless($share->isAvailable(), 410, 'Ten link wygasł lub został wyłączony.');
    }
}
