<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\IsoPresentation;
use App\Services\AuditorAccessService;
use App\Services\IsoPresentationStorage;
use App\Services\PresentationRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IsoPresentationController extends Controller
{
    public function store(Request $request, AuditType $auditType, PresentationRenderer $renderer)
    {
        $this->manage($request, $auditType);
        $data = $this->validateData($request, true);
        $this->section($data['section_id']);
        $slides = $this->convert($request, $renderer);
        app(IsoPresentationStorage::class)->save([
            'section_id' => $data['section_id'], 'title' => $data['title'], 'description' => $data['description'] ?? null,
            'original_filename' => $request->file('presentation_file')->getClientOriginalName(),
            'source_size' => $request->file('presentation_file')->getSize(), 'created_by' => $request->user()->id,
        ], $slides);

        return $this->backToSection($auditType, $data['section_id'], 'Prezentacja została dodana. Klient może już przeglądać slajdy.');
    }

    public function update(Request $request, AuditType $auditType, IsoPresentation $presentation, PresentationRenderer $renderer)
    {
        $this->manage($request, $auditType);
        $data = $this->validateData($request, false);
        unset($data['section_id'], $data['presentation_file']);
        if ($request->hasFile('presentation_file')) {
            $slides = $this->convert($request, $renderer);
            app(IsoPresentationStorage::class)->save($data + [
                'original_filename' => $request->file('presentation_file')->getClientOriginalName(),
                'source_size' => $request->file('presentation_file')->getSize(),
            ], $slides, $presentation);
        } else {
            $presentation->update($data);
        }

        return $this->backToSection($auditType, $presentation->section_id, 'Prezentacja została zaktualizowana.');
    }

    public function destroy(Request $request, AuditType $auditType, IsoPresentation $presentation)
    {
        $this->manage($request, $auditType);
        $section = $presentation->section_id;
        $presentation->delete();

        return $this->backToSection($auditType, $section, 'Prezentacja i jej podglądy zostały usunięte.');
    }

    public function templateSlide(AuditType $auditType, IsoPresentation $presentation, int $slide)
    {
        abort_unless($auditType->slug === 'iso50001', 404);

        return $this->image($presentation, $slide);
    }

    public function auditSlide(Request $request, Audit $audit, IsoPresentation $presentation, int $slide)
    {
        if ($request->routeIs('client.*')) {
            abort_unless($request->user()->companies()->whereKey($audit->company_id)->exists(), 404);
        } else {
            abort_unless(app(AuditorAccessService::class)->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);
        }
        abort_unless($audit->surveys()->whereHas('auditType', fn ($query) => $query->where('slug', 'iso50001'))->exists(), 404);

        return $this->image($presentation, $slide);
    }

    private function image(IsoPresentation $presentation, int $slide)
    {
        abort_if($slide < 1 || $slide > $presentation->slide_count, 404);
        $data = DB::table('iso_presentation_slides')->where('iso_presentation_id', $presentation->id)->where('position', $slide)->value('image_data');
        abort_unless($data, 404);

        $bytes = base64_decode($data);
        $mime = str_starts_with($bytes, "\x89PNG\r\n\x1a\n") ? 'image/png' : 'image/jpeg';

        return response($bytes, 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    private function manage(Request $request, AuditType $type): void
    {
        abort_unless($type->slug === 'iso50001', 404);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
    }

    private function validateData(Request $request, bool $creating): array
    {
        return $request->validate([
            'section_id' => [$creating ? 'required' : 'nullable', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'presentation_file' => [$creating ? 'required' : 'nullable', 'file', 'max:20480', 'extensions:pptx', 'mimes:pptx'],
        ]);
    }

    private function section(string $section): void
    {
        $ids = collect(config('iso50001.chapters'))->flatMap(fn ($chapter) => [$chapter['id'], ...collect($chapter['items'] ?? [])->pluck('id')->all()]);
        abort_unless($ids->containsStrict($section), 422);
    }

    private function convert(Request $request, PresentationRenderer $renderer): array
    {
        $lock = Cache::lock('iso-presentation-conversion', 120);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['presentation_file' => 'Trwa przygotowanie innej prezentacji. Spróbuj ponownie za chwilę.']);
        }
        try {
            @set_time_limit(110);

            return $renderer->render($request->file('presentation_file')->getRealPath());
        } finally {
            $lock->release();
        }
    }

    private function backToSection(AuditType $type, string $section, string $message)
    {
        return redirect()->route('audit-types.show', ['auditType' => $type, 'section' => $section])->with('success', $message);
    }
}
