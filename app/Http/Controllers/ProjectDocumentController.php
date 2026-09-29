<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectDocumentFolder;
use App\Services\DocumentQuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectDocumentController extends Controller
{
    public function storeDocument(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'required_without:files'],
            'files' => ['nullable', 'array', 'min:1', 'max:20', 'required_without:file'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
            'project_document_folder_id' => ['nullable', 'integer', 'exists:project_document_folders,id'],
        ]);
        $folder = ! empty($data['project_document_folder_id'])
            ? ProjectDocumentFolder::where('project_id', $project->id)->findOrFail($data['project_document_folder_id'])
            : null;
        $files = $request->hasFile('files') ? $request->file('files') : [$request->file('file')];
        app(DocumentQuotaService::class)->assertAdditional($request->user()->id, array_sum(array_map(fn ($file) => $file->getSize(), $files)));
        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $safeName = now()->format('YmdHis').'_'.Str::random(10).'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
            $relativePath = 'projects/'.$project->id.'/'.$safeName;
            Storage::disk('local')->put($relativePath, $file->getContent());
            Document::create([
                'project_id' => $project->id,
                'company_id' => $project->company_id,
                'project_document_folder_id' => $folder?->id,
                'type' => 'upload',
                'original_filename' => $originalName,
                'stored_path' => $relativePath,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'documents'])
            ->with('success', count($files) === 1 ? 'Dokument projektu został dodany i zapisany.' : 'Dodano '.count($files).' dokumentów projektu.');
    }

    public function downloadDocument(Project $project, Document $document)
    {
        $this->authorize('view', $project);
        abort_unless($document->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function destroyDocument(Project $project, Document $document): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($document->project_id === $project->id, 404);
        Storage::disk('local')->delete($document->stored_path);
        $document->delete();

        return redirect()->back()->with('success', 'Dokument projektu został usunięty.');
    }
}
