<?php

namespace App\Http\Controllers;

use App\Models\AuditType;
use App\Models\AuditTypeVersion;
use App\Services\AuditorAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditTypeController extends Controller
{
    public function surveys(): View
    {
        return view('audit-types.placeholder', [
            'title' => 'Ankiety HTML',
            'icon' => 'forms',
            'description' => 'Tutaj znajdą się narzędzia do tworzenia, publikowania i obsługi ankiet audytowych dostępnych w przeglądarce.',
            'features' => ['Kreator formularzy audytowych', 'Publikowanie ankiet dla klientów', 'Podgląd odpowiedzi i postępu wypełniania'],
        ]);
    }

    public function versioning(): View
    {
        return view('audit-types.placeholder', [
            'title' => 'Wersjonowanie audytów',
            'icon' => 'versions',
            'description' => 'Tutaj znajdą się narzędzia do kontroli kolejnych wersji formularzy, zmian oraz aktywnych wariantów audytów.',
            'features' => ['Historia zmian formularzy', 'Porównywanie wersji', 'Wybór wersji obowiązującej dla nowych audytów'],
        ]);
    }

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
        if ($auditType->slug === 'iso50001') {
            return view('audit-types.iso50001', [
                'auditType' => $auditType,
                'chapters' => config('iso50001.chapters', []),
            ]);
        }

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
