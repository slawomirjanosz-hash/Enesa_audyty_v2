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
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\Response;

class IsoImplementationController extends Controller
{
    public function __construct(private readonly AuditorAccessService $access) {}

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

    public function storeContext(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorizeStaff($request, $audit);
        $this->persistContext($request, $audit);

        return $this->contextRedirect($audit, false, 'Ankieta kontekstu została zapisana.');
    }

    public function storeContextForClient(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorizeClient($request, $audit);
        $this->persistContext($request, $audit);

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
            'audit' => $audit, 'answers' => $response->answers, 'factors' => $this->contextFactors($response->answers['facts'] ?? []), 'generatedBy' => $request->user(),
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
        $version = (string) (IsoSectionDocument::where('audit_id', $audit->id)->where('section_id', '4-1')->where('mime_type', $mime)->count() + 1).'.0';
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

    private function contextFactors(array $facts): array
    {
        $yes = fn (string $key): bool => ($facts[$key] ?? null) === 'tak';
        $factor = fn (string $area, string $impact, string $text, string $effect): array => compact('area', 'impact', 'text', 'effect');
        $items = [
            $factor('Wewnętrzne — organizacyjne', 'Ujemny', 'Brak sformalizowanego systemu zarządzania energią i zasad nadzoru nad dokumentacją.', 'Ustanowić strukturę EnMS, odpowiedzialności i zasady nadzoru.'),
            $factor('Zewnętrzne — regulacyjne', 'Ujemny', 'Otoczenie prawne efektywności energetycznej podlega zmianom.', 'Przeglądać rejestr wymagań prawnych co najmniej raz w roku.'),
            $factor('Zewnętrzne — rynkowe', 'Ujemny', 'Wahania cen nośników energii zwiększają ryzyko kosztowe.', 'Uwzględnić ryzyko cenowe w rejestrze ryzyk i planowaniu.'),
            $factor('Zewnętrzne — technologiczne', 'Dodatni', 'Dostępne są technologie monitoringu, sterowania i odzysku energii.', 'Oceniać je jako możliwości poprawy wyniku energetycznego.'),
        ];
        if (($facts['metering'] ?? null) === 'brak') {
            $items[] = $factor('Wewnętrzne — techniczne', 'Ujemny', 'Pomiar energii odbywa się wyłącznie na przyłączu głównym.', 'Rozbudować opomiarowanie i początkowo wyznaczyć linię bazową na poziomie zakładu.');
        }
        if (($facts['metering'] ?? null) === 'kompleksowo') {
            $items[] = $factor('Wewnętrzne — techniczne', 'Dodatni', 'Opomiarowanie umożliwia analizę na poziomie urządzeń.', 'Wyznaczyć EnPI dla obszarów znaczącego zużycia energii.');
        }
        if (! $yes('scada')) {
            $items[] = $factor('Wewnętrzne — techniczne', 'Ujemny', 'Brak systemu nadrzędnego do archiwizacji danych energetycznych.', 'Plan zbierania danych powinien określać odczyty ręczne.');
        }
        if ((float) ($facts['infrastructure_age'] ?? 0) > 15) {
            $items[] = $factor('Wewnętrzne — techniczne', 'Ujemny', 'Główna infrastruktura energetyczna jest eksploatowana ponad 15 lat.', 'Nadać priorytet ocenie sprawności i modernizacji urządzeń.');
        }
        if ($yes('compressed_air')) {
            $items[] = $factor('Wewnętrzne — techniczne', 'Ujemny', 'Sprężone powietrze może stanowić obszar istotnych strat.', 'Zweryfikować SEU, sterowanie sprężarek i program kontroli szczelności.');
        }
        if ($yes('pv') && ! $yes('battery')) {
            $items[] = $factor('Wewnętrzne — techniczne', 'Możliwość', 'Produkcja PV bez magazynu może powodować oddawanie nadwyżek do sieci.', 'Ocenić autokonsumpcję i opłacalność magazynu energii.');
        }
        if ($yes('waste_heat')) {
            $items[] = $factor('Wewnętrzne — techniczne', 'Możliwość', 'W procesie występuje ciepło odpadowe o potencjale odzysku.', 'Ująć odzysk ciepła na liście możliwości poprawy.');
        }
        if (($facts['energy_manager'] ?? null) !== 'tak') {
            $items[] = $factor('Wewnętrzne — organizacyjne', 'Ujemny', 'Odpowiedzialność za gospodarkę energetyczną nie jest formalnie umocowana.', 'Wyznaczyć Energy Managera, zakres obowiązków i uprawnienia.');
        }
        if ((int) ($facts['locations'] ?? 1) > 1) {
            $items[] = $factor('Wewnętrzne — organizacyjne', 'Możliwość', 'System obejmuje wiele lokalizacji.', 'Ujednolicić zasady i definicje EnPI między lokalizacjami.');
        }
        if (! $yes('efficiency_budget')) {
            $items[] = $factor('Wewnętrzne — finansowe', 'Ujemny', 'Nie wyodrębniono budżetu na efektywność energetyczną.', 'Pierwszy plan oprzeć na działaniach bezkosztowych i niskonakładowych oraz przygotować budżet.');
        }
        if ((float) ($facts['consumption_tj'] ?? 0) > 85) {
            $items[] = $factor('Zewnętrzne — regulacyjne', 'Ujemny', 'Roczne zużycie energii przekracza 85 TJ.', 'Zweryfikować obowiązki i podporządkować harmonogram terminom prawnym.');
        }
        if ($yes('energy_audit')) {
            $items[] = $factor('Zewnętrzne — regulacyjne', 'Dodatni', 'Dostępny jest audyt energetyczny przedsiębiorstwa.', 'Wykorzystać wyniki jako wejście do przeglądu energetycznego.');
        }
        if ($yes('ets') || $yes('csrd')) {
            $items[] = $factor('Zewnętrzne — regulacyjne', 'Możliwość', 'Dane energetyczne są powiązane z raportowaniem emisyjnym lub zrównoważonego rozwoju.', 'Ujednolicić EnPI z raportowanymi wskaźnikami.');
        }
        if ($yes('customers_co2')) {
            $items[] = $factor('Zewnętrzne — rynkowe', 'Możliwość', 'Odbiorcy oczekują danych o śladzie węglowym lub efektywności.', 'Rozpoznać wymagania jako potencjalne wymogi zgodności i ustalić sposób raportowania.');
        }
        if ($yes('power_limit')) {
            $items[] = $factor('Zewnętrzne — rynkowe', 'Ujemny', 'Dostępna moc przyłączeniowa ogranicza rozwój.', 'Rozważyć redukcję zapotrzebowania jako alternatywę dla rozbudowy przyłącza.');
        }
        if ($yes('seasonality')) {
            $items[] = $factor('Zewnętrzne — rynkowe', 'Możliwość', 'Sezonowość wpływa na profil zużycia energii.', 'Normalizować EnPI i linię bazową względem zmiennych sezonowych.');
        }

        return $items;
    }

    private function appendContextDocument($section, Audit $audit, array $answers): void
    {
        $facts = $answers['facts'] ?? [];
        $section->addText('Organizacja: '.($facts['organization'] ?? $audit->company->name), ['bold' => true]);
        $section->addText('Zakres systemu: '.($facts['scope'] ?? 'Do uzupełnienia'));
        $section->addText('Data opracowania: '.now()->format('d.m.Y'));
        $section->addTitle('1. Cel dokumentu', 2);
        $section->addText('Dokument identyfikuje czynniki wewnętrzne i zewnętrzne wpływające na zdolność organizacji do osiągania zamierzonych wyników systemu zarządzania energią.');
        $section->addTitle('2. Czynniki kontekstowe', 2);
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'D8E0E6', 'cellMargin' => 90]);
        $table->addRow();
        foreach (['Obszar', 'Zidentyfikowany czynnik', 'Wpływ', 'Skutek dla systemu'] as $heading) {
            $table->addCell()->addText($heading, ['bold' => true, 'color' => 'FFFFFF'], ['bgColor' => '174C38']);
        }
        foreach ($this->contextFactors($facts) as $item) {
            $table->addRow();
            foreach (['area', 'text', 'impact', 'effect'] as $key) {
                $table->addCell()->addText($item[$key]);
            }
        }
        $section->addTitle('3. Synteza — analiza SWOT', 2);
        $swot = $answers['swot'] ?? [];
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'D8E0E6', 'cellMargin' => 90]);
        foreach (config('iso50001-context.swot') as $key => $label) {
            $table->addRow();
            $table->addCell(2400)->addText($label, ['bold' => true]);
            $table->addCell(6800)->addText($swot[$key] ?? '[do uzupełnienia]');
        }
        $section->addTitle('4. Strony zainteresowane', 2);
        foreach (['Zarząd — ograniczenie kosztów i przewidywalność wydatków.', 'Pracownicy i utrzymanie ruchu — jasne zasady eksploatacji i dostęp do danych.', 'Organy regulacyjne — spełnienie wymagań prawnych.', 'Odbiorcy — uzgodnione wymagania energetyczne i emisyjne.', 'Jednostka certyfikująca — kompletność oraz dostępność zapisów systemowych.'] as $line) {
            $section->addListItem($line);
        }
        $section->addTitle('5. Wnioski i decyzje projektowe', 2);
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'D8E0E6', 'cellMargin' => 90]);
        $table->addRow();
        foreach (['Lp.', 'Wniosek', 'Decyzja projektowa', 'Dokument'] as $heading) {
            $table->addCell()->addText($heading, ['bold' => true]);
        }
        foreach ($answers['conclusions'] ?? [] as $index => $conclusion) {
            $table->addRow();
            foreach ([(string) ($index + 1), $conclusion['finding'] ?? '', $conclusion['decision'] ?? '', $conclusion['document'] ?? ''] as $value) {
                $table->addCell()->addText($value ?: '[do uzupełnienia]');
            }
        }
        $section->addTitle('6. Dokumenty powiązane i aktualizacja', 2);
        foreach (['D-EnMS-ZAK-01 — Zakres systemu', 'D-EnMS-RYZ-01 — Rejestr ryzyk i szans', 'D-EnMS-CEL-01 — Cele energetyczne', 'Protokół Przeglądu Zarządzania'] as $line) {
            $section->addListItem($line);
        }
        $section->addText('Dokument podlega przeglądowi co najmniej raz w roku oraz przy istotnej zmianie otoczenia organizacji.');
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
            $version = (string) (IsoSectionDocument::where('audit_id', $audit->id)->where('section_id', $section)->where('title', $workflow['title'])->count() + 1).'.0';
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
