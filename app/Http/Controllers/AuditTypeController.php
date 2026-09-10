<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\AuditTypeVersion;
use App\Models\IsoSectionDocument;
use App\Models\IsoTrainingVideo;
use App\Services\AuditorAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                'trainingVideos' => IsoTrainingVideo::query()->latest()->get(),
                'canManageTraining' => app(AuditorAccessService::class)->hasFullAccess(request()->user()),
                'templateDocuments' => IsoSectionDocument::query()->where('scope', 'template')->with('uploader')->get()->groupBy('section_id'),
            ]);
        }

        $auditType->load('versions.creator');

        return view('audit-types.show', compact('auditType'));
    }

    public function storeIsoDocument(Request $request, AuditType $auditType): RedirectResponse
    {
        abort_unless($auditType->slug === 'iso50001', 404);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $data = $this->validateIsoDocument($request);
        $this->ensureIsoSection($data['section_id']);
        $this->storeIsoFiles($request, $data, null, 'template');

        return redirect()->route('audit-types.show', ['auditType' => $auditType, 'section' => $data['section_id']])
            ->with('success', 'Dokumentacja wzorcowa została dodana.');
    }

    public function downloadIsoDocument(AuditType $auditType, IsoSectionDocument $document)
    {
        abort_unless($auditType->slug === 'iso50001' && $document->scope === 'template' && $document->audit_id === null, 404);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function destroyIsoDocument(Request $request, AuditType $auditType, IsoSectionDocument $document): RedirectResponse
    {
        abort_unless($auditType->slug === 'iso50001' && $document->scope === 'template' && $document->audit_id === null, 404);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        Storage::disk('local')->delete($document->stored_path);
        $section = $document->section_id;
        $document->delete();

        return redirect()->route('audit-types.show', ['auditType' => $auditType, 'section' => $section])->with('success', 'Dokument wzorcowy został usunięty.');
    }

    private function validateIsoDocument(Request $request): array
    {
        return $request->validate([
            'section_id' => ['required', 'string', 'max:40'], 'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'], 'document_year' => ['nullable', 'integer', 'between:2000,2200'],
            'version_number' => ['required', 'string', 'max:40'], 'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:30720', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,csv,txt,jpg,jpeg,png,zip'],
        ]);
    }

    private function ensureIsoSection(string $sectionId): void
    {
        $ids = collect(config('iso50001.chapters', []))->flatMap(fn (array $chapter) => [$chapter['id'], ...collect($chapter['items'] ?? [])->pluck('id')->all()]);
        abort_unless($ids->containsStrict($sectionId), 422);
    }

    private function storeIsoFiles(Request $request, array $data, ?Audit $audit, string $scope): void
    {
        foreach ($request->file('files') as $file) {
            $safeName = now()->format('YmdHis').'_'.Str::random(12).'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $path = 'iso50001/'.$scope.'/'.($audit?->id ?? 'library').'/'.$data['section_id'].'/'.$safeName;
            Storage::disk('local')->put($path, $file->getContent());
            IsoSectionDocument::create([
                'audit_id' => $audit?->id, 'section_id' => $data['section_id'], 'scope' => $scope,
                'title' => count($request->file('files')) === 1 && filled($data['title'] ?? null) ? $data['title'] : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $data['description'] ?? null, 'document_year' => $data['document_year'] ?? now()->year,
                'version_number' => $data['version_number'], 'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $path, 'mime_type' => $file->getClientMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    public function storeTrainingVideo(Request $request, AuditType $auditType): RedirectResponse
    {
        abort_unless($auditType->slug === 'iso50001', 404);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $data = $request->validate([
            'section_id' => ['nullable', 'string', 'max:40'],
            'topic' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'youtube_url' => ['required', 'url', 'max:500', 'regex:/^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\//i'],
        ], ['youtube_url.regex' => 'Podaj prawidłowy adres filmu w serwisie YouTube.']);
        if (filled($data['section_id'] ?? null)) {
            $this->ensureIsoSection($data['section_id']);
        }

        IsoTrainingVideo::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('audit-types.show', ['auditType' => $auditType, 'section' => $data['section_id'] ?? 'training'])
            ->with('success', 'Film szkoleniowy został dodany.');
    }

    public function destroyTrainingVideo(Request $request, AuditType $auditType, IsoTrainingVideo $video): RedirectResponse
    {
        abort_unless($auditType->slug === 'iso50001', 404);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $video->delete();

        return redirect()->route('audit-types.show', ['auditType' => $auditType, 'section' => $video->section_id ?? 'training'])
            ->with('success', 'Film szkoleniowy został usunięty.');
    }

    public function updateTrainingVideo(Request $request, AuditType $auditType, IsoTrainingVideo $video): RedirectResponse
    {
        abort_unless($auditType->slug === 'iso50001', 404);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $data = $request->validate([
            'section_id' => ['nullable', 'string', 'max:40'],
            'topic' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'youtube_url' => ['required', 'url', 'max:500', 'regex:/^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\//i'],
        ], ['youtube_url.regex' => 'Podaj prawidłowy adres filmu w serwisie YouTube.']);
        if (filled($data['section_id'] ?? null)) {
            $this->ensureIsoSection($data['section_id']);
        }
        $video->update($data);

        return redirect()->route('audit-types.show', ['auditType' => $auditType, 'section' => $data['section_id'] ?? 'training'])
            ->with('success', 'Dane filmu szkoleniowego zostały zmienione.');
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
