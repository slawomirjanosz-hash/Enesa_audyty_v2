<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectDocumentFolder;
use App\Models\ProjectDocumentShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectDocumentFolderController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('project_document_folders')->where('project_id', $project->id)],
        ]);
        $project->documentFolders()->create(['name' => trim($data['name']), 'created_by' => $request->user()->id]);

        return $this->back($project, 'Katalog dokumentów został utworzony.');
    }

    public function destroy(Project $project, ProjectDocumentFolder $folder): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($folder->project_id === $project->id, 404);
        if ($folder->documents()->exists()) {
            return $this->back($project, 'Najpierw usuń lub przenieś dokumenty z katalogu.', 'error');
        }
        $folder->delete();

        return $this->back($project, 'Pusty katalog i jego linki zostały usunięte.');
    }

    public function share(Request $request, Project $project, ProjectDocumentFolder $folder): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($folder->project_id === $project->id, 404);
        $data = $request->validate([
            'access_level' => ['required', Rule::in(['view', 'upload'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $folder->shares()->create($data + ['active' => true, 'created_by' => $request->user()->id]);

        return $this->back($project, 'Link do katalogu został utworzony.');
    }

    public function revoke(Project $project, ProjectDocumentFolder $folder, ProjectDocumentShare $share): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($folder->project_id === $project->id && $share->project_document_folder_id === $folder->id, 404);
        $share->update(['active' => false]);

        return $this->back($project, 'Link został wyłączony.');
    }

    private function back(Project $project, string $message, string $key = 'success'): RedirectResponse
    {
        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'documents'])->with($key, $message);
    }
}
