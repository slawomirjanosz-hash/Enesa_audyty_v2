<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\IsoImplementationResponse;
use App\Models\IsoSectionDocument;
use App\Services\AuditorAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IsoImplementationController extends Controller
{
    public function __construct(private readonly AuditorAccessService $access) {}

    public function store(Request $request, Audit $audit, string $action): RedirectResponse
    {
        abort_unless($this->access->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);

        return $this->persist($request, $audit, $action, false);
    }

    public function generate(Request $request, Audit $audit, string $action): RedirectResponse
    {
        abort_unless($this->access->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);

        return $this->persist($request, $audit, $action, true);
    }

    public function storeForClient(Request $request, Audit $audit, string $action): RedirectResponse
    {
        $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();

        return $this->persist($request, $audit, $action, false, true);
    }

    public function generateForClient(Request $request, Audit $audit, string $action): RedirectResponse
    {
        $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();

        return $this->persist($request, $audit, $action, true, true);
    }

    private function persist(Request $request, Audit $audit, string $action, bool $generate, bool $client = false): RedirectResponse
    {
        $this->ensureIsoAudit($audit);
        $workflow = config('iso50001-workflows.3-1.'.$action);
        abort_unless(is_array($workflow), 404);

        $rules = collect($workflow['fields'])->mapWithKeys(fn (array $field, string $key) => [
            'answers.'.$key => ['nullable', $field['type'] === 'number' ? 'numeric' : ($field['type'] === 'date' ? 'date' : 'string'), $field['type'] === 'number' ? 'min:0' : 'max:5000'],
        ])->all();
        $data = $request->validate($rules);
        $response = IsoImplementationResponse::updateOrCreate(
            ['audit_id' => $audit->id, 'section_id' => '3-1', 'action_key' => $action],
            ['answers' => $data['answers'] ?? [], 'completed_by' => $request->user()->id]
        );

        if ($generate) {
            $pdf = Pdf::loadView('audits.iso50001-action-pdf', [
                'audit' => $audit->loadMissing('company'), 'workflow' => $workflow,
                'answers' => $response->answers, 'generatedBy' => $request->user(),
            ])->setPaper('a4');
            $version = (string) (IsoSectionDocument::where('audit_id', $audit->id)->where('section_id', '3-1')->where('title', $workflow['title'])->count() + 1).'.0';
            $filename = 'ISO50001_3.1_'.Str::slug($workflow['title'], '_').'_v'.str_replace('.', '_', $version).'.pdf';
            $path = 'iso50001/client/'.$audit->id.'/3-1/generated/'.Str::uuid().'.pdf';
            Storage::disk('local')->put($path, $pdf->output());
            IsoSectionDocument::create([
                'audit_id' => $audit->id, 'section_id' => '3-1', 'scope' => 'client',
                'title' => $workflow['title'], 'description' => 'Dokument wygenerowany z ankiety klienta.',
                'document_year' => now()->year, 'version_number' => $version, 'original_filename' => $filename,
                'stored_path' => $path, 'mime_type' => 'application/pdf', 'size' => Storage::disk('local')->size($path),
                'uploaded_by' => $request->user()->id,
            ]);
            $response->update(['generated_at' => now()]);
        }

        $route = $client ? 'client.audits.show' : 'audits.show';

        return redirect()->route($route, ['audit' => $audit, 'tab' => 'iso50001', 'section' => '3-1'])
            ->with('success', $generate ? 'PDF został wygenerowany i zapisany w dokumentacji punktu 3.1.' : 'Ankieta została zapisana.');
    }

    private function ensureIsoAudit(Audit $audit): void
    {
        abort_unless($audit->surveys()->whereHas('auditType', fn ($query) => $query->where('slug', 'iso50001'))->exists(), 404);
    }
}
