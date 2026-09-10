<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\IsoImplementationResponse;
use App\Models\IsoSectionDocument;
use App\Services\AuditorAccessService;
use App\Services\DocumentVersionService;
use App\Services\IsoContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use Symfony\Component\HttpFoundation\Response;

class IsoImplementationController extends Controller
{
    public function __construct(private readonly AuditorAccessService $access) {}

    public function contextScreen(Request $request, Audit $audit)
    {
        $this->authorizeStaff($request, $audit);

        return $this->contextScreenView($audit, false);
    }

    public function contextScreenForClient(Request $request, Audit $audit)
    {
        $this->authorizeClient($request, $audit);

        return $this->contextScreenView($audit, true);
    }

    public function contextTemplate(AuditType $auditType)
    {
        abort_unless($auditType->slug === 'iso50001', 404);

        return view('audits.iso50001-context-screen', ['answers' => [], 'isTemplatePreview' => true,
            'backUrl' => route('audit-types.show', ['auditType' => $auditType, 'section' => '4-1']), 'baseRoute' => null]);
    }

    private function contextScreenView(Audit $audit, bool $client)
    {
        $response = $audit->isoImplementationResponses()->where('section_id', '4-1')->where('action_key', 'context_generator')->first();

        return view('audits.iso50001-context-screen', [
            'audit' => $audit, 'answers' => app(IsoContextService::class)->normalize(old('answers', $response?->answers ?? [])),
            'isTemplatePreview' => false, 'baseRoute' => $client ? 'client.audits.iso50001.context.' : 'audits.iso50001.context.',
            'backUrl' => route($client ? 'client.audits.show' : 'audits.show', ['audit' => $audit, 'tab' => 'iso50001', 'section' => '4-1']),
        ]);
    }

    public function store(Request $request, Audit $audit, string $section, string $action): RedirectResponse
    {
        abort_unless($this->access->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);

        return $this->persist($request, $audit, $section, $action, false);
    }

    public function generate(Request $request, Audit $audit, string $section, string $action): RedirectResponse
    {
        abort_unless($this->access->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);

        return $this->persist($request, $audit, $section, $action, true);
    }

    public function storeForClient(Request $request, Audit $audit, string $section, string $action): RedirectResponse
    {
        $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();

        return $this->persist($request, $audit, $section, $action, false, true);
    }

    public function generateForClient(Request $request, Audit $audit, string $section, string $action): RedirectResponse
    {
        $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();

        return $this->persist($request, $audit, $section, $action, true, true);
    }

    public function storeContext(Request $request, Audit $audit)
    {
        $this->authorizeStaff($request, $audit);
        $this->persistContext($request, $audit);

        if ($request->expectsJson()) {
            return response()->json(['saved' => true]);
        }

        return $this->contextRedirect($audit, false, 'Ankieta kontekstu została zapisana.');
    }

    public function storeContextForClient(Request $request, Audit $audit)
    {
        $this->authorizeClient($request, $audit);
        $this->persistContext($request, $audit);

        if ($request->expectsJson()) {
            return response()->json(['saved' => true]);
        }

        return $this->contextRedirect($audit, true, 'Ankieta kontekstu została zapisana.');
    }

    public function contextDocx(Request $request, Audit $audit): Response
    {
        $this->authorizeStaff($request, $audit);

        return $this->makeContextDocx($request, $audit);
    }

    public function contextDocxForClient(Request $request, Audit $audit): Response
    {
        $this->authorizeClient($request, $audit);

        return $this->makeContextDocx($request, $audit);
    }

    public function contextPdf(Request $request, Audit $audit): Response
    {
        $this->authorizeStaff($request, $audit);

        return $this->makeContextPdf($request, $audit, true);
    }

    public function contextPdfForClient(Request $request, Audit $audit): Response
    {
        $this->authorizeClient($request, $audit);

        return $this->makeContextPdf($request, $audit, true);
    }

    public function contextPdfPreview(Request $request, Audit $audit): Response
    {
        $this->authorizeStaff($request, $audit);

        return $this->makeContextPdf($request, $audit, false);
    }

    public function contextPdfPreviewForClient(Request $request, Audit $audit): Response
    {
        $this->authorizeClient($request, $audit);

        return $this->makeContextPdf($request, $audit, false);
    }

    private function authorizeStaff(Request $request, Audit $audit): void
    {
        abort_unless($this->access->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);
        $this->ensureIsoAudit($audit);
    }

    private function authorizeClient(Request $request, Audit $audit): void
    {
        $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();
        $this->ensureIsoAudit($audit);
    }

    private function contextAnswers(Request $request): array
    {
        $rules = [];
        foreach (config('iso50001-context.questions') as $key => $question) {
            $rules['answers.facts.'.$key] = ['nullable', $question['type'] === 'number' ? 'numeric' : 'string', $question['type'] === 'number' ? 'min:0' : 'max:1000'];
            if (isset($question['options'])) {
                $rules['answers.facts.'.$key][] = Rule::in(array_keys($question['options']));
            }
        }
        $rules['answers.selected'] = ['nullable', 'array', 'max:59'];
        $rules['answers.selected.*'] = ['string', Rule::in(array_column(config('iso50001-context.factors'), 'id'))];
        foreach (config('iso50001-context.factors') as $factor) {
            $rules['answers.edits.'.$factor['id']] = ['nullable', 'string', 'max:5000'];
        }
        foreach (array_keys(config('iso50001-context.swot')) as $key) {
            $rules['answers.swot.'.$key] = ['nullable', 'string', 'max:10000'];
        }
        $rules['answers.conclusions'] = ['nullable', 'array', 'max:10'];
        $rules['answers.conclusions.*.finding'] = ['nullable', 'string', 'max:5000'];
        $rules['answers.conclusions.*.decision'] = ['nullable', 'string', 'max:5000'];
        $rules['answers.conclusions.*.document'] = ['nullable', 'string', 'max:500'];

        return $request->validate($rules)['answers'] ?? [];
    }

    private function persistContext(Request $request, Audit $audit): IsoImplementationResponse
    {
        return IsoImplementationResponse::updateOrCreate(
            ['audit_id' => $audit->id, 'section_id' => '4-1', 'action_key' => 'context_generator'],
            ['answers' => $this->contextAnswers($request), 'completed_by' => $request->user()->id]
        );
    }

    private function makeContextDocx(Request $request, Audit $audit): Response
    {
        $response = $this->persistContext($request, $audit);
        $audit->loadMissing('company');
        $phpWord = new PhpWord;
        Settings::setOutputEscapingEnabled(true);
        $phpWord->addTitleStyle(1, ['size' => 18, 'bold' => true, 'color' => '174C38']);
        $phpWord->addTitleStyle(2, ['size' => 13, 'bold' => true, 'color' => '174C38']);
        $phpWord->addTitleStyle(3, ['size' => 11, 'bold' => true]);
        $phpWord->setDefaultFontName('Aptos');
        $phpWord->setDefaultFontSize(10);
        $section = $phpWord->addSection(['marginTop' => 900, 'marginRight' => 900, 'marginBottom' => 900, 'marginLeft' => 900]);
        $section->addText('D-EnMS-KON-01', ['color' => '19704B', 'bold' => true]);
        $section->addTitle('Kontekst organizacji i strony zainteresowane', 1);
        $section->addText('PN-EN ISO 50001:2018 — klauzule 4.1 i 4.2', ['color' => '66736B']);
        $this->appendContextDocument($section, $audit, $response->answers);

        $temporary = tempnam(storage_path('framework'), 'iso41_');
        abort_if($temporary === false, 500, 'Nie udało się utworzyć dokumentu.');
        IOFactory::createWriter($phpWord, 'Word2007')->save($temporary);
        $contents = file_get_contents($temporary);
        @unlink($temporary);
        abort_if($contents === false, 500, 'Nie udało się zapisać dokumentu.');
        $filename = 'D-EnMS-KON-01_'.Str::slug($audit->company->name, '_').'.docx';
        $this->storeGeneratedContextDocument($request, $audit, $contents, $filename, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Dokument Word wygenerowany z ankiety kontekstu organizacji.');

        return response($contents, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }

    private function makeContextPdf(Request $request, Audit $audit, bool $store): Response
    {
        $response = $this->persistContext($request, $audit);
        $audit->loadMissing('company');
        $pdf = Pdf::loadView('audits.iso50001-context-pdf', [
            'audit' => $audit, 'answers' => $response->answers, 'factors' => app(IsoContextService::class)->selected($response->answers), 'generatedBy' => $request->user(),
        ])->setPaper('a4');
        $contents = $pdf->output();
        $filename = 'D-EnMS-KON-01_'.Str::slug($audit->company->name, '_').'.pdf';
        if ($store) {
            $this->storeGeneratedContextDocument($request, $audit, $contents, $filename, 'application/pdf', 'PDF wygenerowany z ankiety kontekstu organizacji.');
            $response->update(['generated_at' => now()]);
        }

        return response($contents, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($store ? 'attachment' : 'inline').'; filename="'.$filename.'"']);
    }

    private function storeGeneratedContextDocument(Request $request, Audit $audit, string $contents, string $filename, string $mime, string $description): void
    {
        $version = app(DocumentVersionService::class)->next($audit->id, '4-1', 'mime_type', $mime);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = 'iso50001/client/'.$audit->id.'/4-1/generated/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $contents);
        IsoSectionDocument::create([
            'audit_id' => $audit->id, 'section_id' => '4-1', 'scope' => 'client', 'title' => 'Kontekst organizacji i strony zainteresowane',
            'description' => $description, 'document_year' => now()->year, 'version_number' => $version, 'original_filename' => $filename,
            'stored_path' => $path, 'mime_type' => $mime, 'size' => strlen($contents), 'content_base64' => base64_encode($contents), 'uploaded_by' => $request->user()->id,
        ]);
    }

    private function contextRedirect(Audit $audit, bool $client, string $message): RedirectResponse
    {
        return redirect()->route($client ? 'client.audits.show' : 'audits.show', ['audit' => $audit, 'tab' => 'iso50001', 'section' => '4-1'])->with('success', $message);
    }

    private function appendContextDocument($section, Audit $audit, array $answers): void
    {
        $service = app(IsoContextService::class);
        $fill = fn ($value) => filled($value) ? $value : '[do uzupełnienia]';
        $table = function (array $headers, array $rows) use ($section): void {
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'D8E0E6', 'cellMargin' => 90]);
            $table->addRow(null, ['tblHeader' => true]);
            foreach ($headers as $header) {
                $table->addCell(null, ['bgColor' => '174C38'])->addText($header, ['bold' => true, 'color' => 'FFFFFF']);
            }
            foreach ($rows as $row) {
                $table->addRow();
                foreach ($row as $value) {
                    $table->addCell()->addText((string) $value);
                }
            }
        };
        $section->addText('Organizacja: '.(($answers['facts']['organization'] ?? '') ?: $audit->company->name), ['bold' => true]);
        $section->addText('Zakres systemu: '.$fill($answers['facts']['scope'] ?? null));
        $section->addText('Data opracowania: '.now()->format('d.m.Y'));
        $section->addTitle('1. Cel dokumentu', 2);
        $section->addText('Dokument identyfikuje czynniki wewnętrzne i zewnętrzne wpływające na zdolność organizacji do osiągania zamierzonych wyników systemu zarządzania energią oraz strony zainteresowane, których wymagania muszą być uwzględnione. Analiza stanowi podstawę wyznaczenia zakresu systemu, rejestru ryzyk i szans oraz celów energetycznych.');
        $factors = $service->selected($answers);
        foreach (['W' => '2. Kontekst wewnętrzny (kl. 4.1)', 'Z' => '3. Kontekst zewnętrzny (kl. 4.1)'] as $prefix => $title) {
            $section->addTitle($title, 2);
            foreach (config('iso50001-context.dimensions') as $dimension => $label) {
                if (! str_starts_with($dimension, $prefix)) {
                    continue;
                }
                $items = array_values(array_filter($factors, fn ($factor) => $factor['dimension'] === $dimension));
                if (! $items) {
                    continue;
                }
                $section->addTitle($label, 3);
                $table(['Zidentyfikowany czynnik', 'Wpływ', 'Skutek dla systemu'], array_map(fn ($factor) => [$factor['text'], $factor['impact'], $factor['effect']], $items));
            }
        }
        $section->addTitle('4. Synteza — analiza SWOT', 2);
        $rows = [];
        foreach (config('iso50001-context.swot') as $key => $label) {
            $rows[] = [$label, $fill($answers['swot'][$key] ?? null)];
        }
        $table(['Obszar', 'Analiza'], $rows);
        $section->addTitle('5. Strony zainteresowane (kl. 4.2)', 2);
        $table(['Strona', 'Typ', 'Wymagania i oczekiwania', 'Wymóg zgodności'], $service->stakeholders($answers));
        $section->addTitle('6. Wnioski — jak kontekst ukształtował system', 2);
        $rows = [];
        for ($i = 0; $i < 4; $i++) {
            $row = $answers['conclusions'][$i] ?? [];
            $rows[] = [$i + 1, $fill($row['finding'] ?? null), $fill($row['decision'] ?? null), $fill($row['document'] ?? null)];
        }
        $table(['Lp.', 'Wniosek', 'Decyzja projektowa', 'Dokument'], $rows);
        $section->addTitle('7. Dokumenty powiązane i aktualizacja', 2);
        $table(['Dokument', 'Powiązanie'], config('iso50001-context.relatedDocuments'));
        $section->addText('Dokument podlega przeglądowi co najmniej raz w roku, przed Przeglądem Zarządzania, lub przy istotnej zmianie otoczenia organizacji.');
        $section->addText('Opracował (Energy Manager): ...................................... Data: ....................');
        $section->addText('Zatwierdził (Zarząd): ...................................... Data: ....................');
    }

    private function persist(Request $request, Audit $audit, string $section, string $action, bool $generate, bool $client = false): RedirectResponse
    {
        $this->ensureIsoAudit($audit);
        $workflow = config('iso50001-workflows.'.$section.'.'.$action);
        abort_unless(is_array($workflow), 404);

        $rules = collect($workflow['fields'])->mapWithKeys(fn (array $field, string $key) => [
            'answers.'.$key => ['nullable', $field['type'] === 'number' ? 'numeric' : ($field['type'] === 'date' ? 'date' : 'string'), $field['type'] === 'number' ? 'min:0' : 'max:5000'],
        ])->all();
        $data = $request->validate($rules);
        $response = IsoImplementationResponse::updateOrCreate(
            ['audit_id' => $audit->id, 'section_id' => $section, 'action_key' => $action],
            ['answers' => $data['answers'] ?? [], 'completed_by' => $request->user()->id]
        );

        if ($generate) {
            $pdf = Pdf::loadView('audits.iso50001-action-pdf', [
                'audit' => $audit->loadMissing('company'), 'workflow' => $workflow, 'section' => $section,
                'answers' => $response->answers, 'generatedBy' => $request->user(),
            ])->setPaper('a4');
            $version = app(DocumentVersionService::class)->next($audit->id, $section, 'title', $workflow['title']);
            $filename = 'ISO50001_'.str_replace('-', '.', $section).'_'.Str::slug($workflow['title'], '_').'_v'.str_replace('.', '_', $version).'.pdf';
            $path = 'iso50001/client/'.$audit->id.'/'.$section.'/generated/'.Str::uuid().'.pdf';
            $pdfContents = $pdf->output();
            Storage::disk('local')->put($path, $pdfContents);
            IsoSectionDocument::create([
                'audit_id' => $audit->id, 'section_id' => $section, 'scope' => 'client',
                'title' => $workflow['title'], 'description' => 'Dokument wygenerowany z ankiety klienta.',
                'document_year' => now()->year, 'version_number' => $version, 'original_filename' => $filename,
                'stored_path' => $path, 'mime_type' => 'application/pdf', 'size' => strlen($pdfContents),
                'content_base64' => base64_encode($pdfContents),
                'uploaded_by' => $request->user()->id,
            ]);
            $response->update(['generated_at' => now()]);
        }

        $route = $client ? 'client.audits.show' : 'audits.show';

        return redirect()->route($route, ['audit' => $audit, 'tab' => 'iso50001', 'section' => $section])
            ->with('success', $generate ? 'PDF został wygenerowany i zapisany w dokumentacji punktu '.str_replace('-', '.', $section).'.' : 'Ankieta została zapisana.');
    }

    private function ensureIsoAudit(Audit $audit): void
    {
        abort_unless($audit->surveys()->whereHas('auditType', fn ($query) => $query->where('slug', 'iso50001'))->exists(), 404);
    }
}
