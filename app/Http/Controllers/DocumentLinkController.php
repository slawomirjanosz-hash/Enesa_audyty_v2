<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\DocumentLink;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentLinkController extends Controller
{
    public function storeAudit(Request $request, Audit $audit): RedirectResponse
    {
        return $this->store($request, $audit);
    }

    public function storeProject(Request $request, Project $project): RedirectResponse
    {
        return $this->store($request, $project);
    }

    public function destroyAudit(Audit $audit, DocumentLink $link): RedirectResponse
    {
        return $this->destroy($audit, $link);
    }

    public function destroyProject(Project $project, DocumentLink $link): RedirectResponse
    {
        return $this->destroy($project, $link);
    }

    private function store(Request $request, Audit|Project $owner): RedirectResponse
    {
        $this->authorize('update', $owner);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'url' => ['required', 'url:https', 'max:2048', function ($attribute, $value, $fail) {
            $parts = parse_url($value);
            if (! $parts || isset($parts['user']) || isset($parts['pass'])) {
                $fail('Link nie może zawierać loginu ani hasła.');
            }
        }]]);
        $owner->documentLinks()->create($data + ['created_by' => $request->user()->id]);

        return $this->back($owner)->with('success', 'Link do dysku został dodany.');
    }

    private function destroy(Audit|Project $owner, DocumentLink $link): RedirectResponse
    {
        $this->authorize('update', $owner);
        $key = $owner instanceof Audit ? 'audit_id' : 'project_id';
        abort_unless($link->{$key} === $owner->id, 404);
        $link->delete();

        return $this->back($owner);
    }

    private function back(Audit|Project $owner): RedirectResponse
    {
        $prefix = $owner instanceof Audit ? 'audits' : 'projects';

        return redirect()->route($prefix.'.show', [$owner, 'tab' => 'documents']);
    }
}
