<?php

namespace App\Http\Controllers;

use App\Models\AuditType;
use App\Models\AuditTypeVersion;
use App\Services\AuditorAccessService;
use Illuminate\Http\Request;

class AuditTypeController extends Controller
{
    public function index()
    {
        $auditTypes = AuditType::withCount('versions')
            ->with(['versions' => fn ($q) => $q->where('is_current', true)])
            ->orderBy('name')
            ->get();

        return view('audit-types.index', compact('auditTypes'));
    }

    public function show(AuditType $auditType)
    {
        $auditType->load('versions.creator');

        return view('audit-types.show', compact('auditType'));
    }

    public function storeVersion(Request $request, AuditType $auditType)
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $data = $request->validate([
            'version_number' => ['required', 'string', 'max:50'],
            'html_file' => ['required', 'file', 'mimetypes:text/html,application/octet-stream', 'max:2048'],
        ]);

        $htmlContent = file_get_contents($request->file('html_file')->getRealPath());

        $auditType->versions()->create([
            'version_number' => $data['version_number'],
            'html_content' => $htmlContent,
            'is_current' => false,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('audit-types.show', $auditType)
            ->with('success', 'Wersja '.$data['version_number'].' została dodana.');
    }

    public function setAsCurrent(AuditTypeVersion $version)
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess(request()->user()), 403);

        $version->auditType->versions()->update(['is_current' => false]);
        $version->update(['is_current' => true]);

        return redirect()->route('audit-types.show', $version->auditType)
            ->with('success', 'Wersja '.$version->version_number.' jest teraz aktualna.');
    }

    public function previewVersion(AuditTypeVersion $version)
    {
        return response($version->html_content)->header('Content-Type', 'text/html');
    }
}
