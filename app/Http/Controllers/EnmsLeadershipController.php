<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\CompanySettings;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Services\Enms\AuthorizedActions;
use App\Services\Enms\LeadershipDocuments;
use App\Services\Enms\LeadershipWorkbook;
use App\Services\Enms\LeadershipWorkflow;
use App\Services\Enms\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class EnmsLeadershipController extends Controller
{
    public function __construct(private readonly LeadershipWorkflow $workflow) {}

    public function workspace(Request $request, Audit $audit, IsoPlantProfile $profile): Workspace
    {
        return Workspace::for($request->user(), $audit, $profile, $request->routeIs('client.*'));
    }

    public function show(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $w = $this->workspace($request, $audit, $profile);
        $client = $w->client;
        $prefix = $client ? 'client.audits.enms.' : 'audits.enms.';
        $service = $this->workflow;
        $library = $service->library;
        $sources = $service->sources($w);
        $status = $service->status($w);
        $readiness = $service->readiness($w);
        $records = $w->records();
        // No consultant drafts in the client's HTML, including hidden elements.
        if ($client) {
            $records = array_filter($records, fn ($r) => in_array($r['kind'], ['declaration', 'self_assessment', 'evidence', 'task', 'deadline', 'question', 'answer', 'explanation', 'submission', 'authority', 'effectiveness', 'document', 'rule']));
        }
        $options = $service->options($w);
        if ($client) {
            $options['sources'] = [];
        }
        $editable = $w->writable && (! $client || ! $status['submitted']);
        $tabs = [
            'pytania' => 'Ankieta · 1. Cztery pytania na początek',
            'obowiazki' => 'Ankieta · 2. Obowiązki kierownictwa',
            'dowody' => 'Ankieta · 3. Rejestr dowodów',
            'spojnosc' => 'Ankieta · 4. Przed przekazaniem',
        ];
        if (! $client) {
            $tabs += ['weryfikacja' => 'Ocena · 5. Deklaracje i dowody', 'ocena' => 'Ocena · 6. Pokrycie i ocena', 'ustalenia' => 'Ocena · 7. Ustalenia i pytania'];
        }
        $tabs += ['zadania' => 'Ocena · 8. Postanowienia i zadania', 'raport' => 'Dokumenty · 9. Raport diagnostyczny', 'karta' => 'Dokumenty · 10. Karta przywództwa', 'historia' => 'Historia'];
        if ($client) {
            unset($tabs['historia']);
        }
        $tab = array_key_exists($request->query('tab', 'pytania'), $tabs) ? $request->query('tab', 'pytania') : 'pytania';
        $groups = match ($tab) {
            'dowody' => ['evidence'], 'weryfikacja' => ['verification', 'citation_verification'], 'ocena' => ['coverage', 'closure', 'assessment'],
            'ustalenia' => ['finding', 'question'], 'zadania' => $client ? ['task', 'deadline'] : ['decision', 'task', 'deadline', 'authority', 'effectiveness'],
            'karta' => ['rule'], default => [],
        };
        $edit = $request->query('edit') ? ($records[$request->query('edit')] ?? abort(404)) : null;
        $history = $client ? [] : $w->history();
        $actions = app(AuthorizedActions::class);

        return view('audits.enms.show', compact('w', 'audit', 'profile', 'client', 'prefix', 'service', 'library', 'sources', 'status', 'readiness', 'records', 'options', 'editable', 'tabs', 'tab', 'groups', 'edit', 'history', 'actions'));
    }

    public function update(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $w = $this->workspace($request, $audit, $profile);
        abort_unless($w->writable, 403);
        $data = $request->validate(['revision' => 'required|integer|min:0', 'source_hash' => 'required|string|size:64', 'operation' => ['required', Rule::in(['declaration', 'self_assessment', 'record', 'submission', 'completion', 'explanation', 'answer', 'close_question', 'withdraw_question', 'withdraw', 'return', 'authorized'])], 'record_id' => 'nullable|uuid', 'requirement_id' => 'nullable|uuid', 'question_id' => 'nullable|string|max:10', 'kind' => 'nullable|string', 'payload' => 'nullable|array', 'action' => 'nullable|string', 'grant_id' => 'nullable|uuid']);
        $operation = $data['operation'];
        $message = 'Zapisano. Historia i powiązania zostały zachowane.';
        if ($operation === 'declaration') {
            $this->workflow->saveDeclaration($w, $data['revision'], $data['source_hash'], $data['requirement_id'] ?? '', $data['payload'] ?? []);
        } elseif ($operation === 'self_assessment') {
            $this->workflow->saveSelfAssessment($w, $data['revision'], $data['source_hash'], $data['question_id'] ?? '', $data['payload'] ?? []);
        } elseif ($operation === 'record') {
            $this->workflow->save($w, $data['revision'], $data['source_hash'], $data['kind'] ?? '', $data['record_id'] ?? null, $data['payload'] ?? []);
        } elseif ($operation === 'authorized') {
            if ($request->filled('pending_approval')) {
                $pending = $w->record($request->validate(['pending_approval' => 'required|uuid'])['pending_approval'], 'approval')['payload'];
                abort_unless($pending['target_id'] === ($data['record_id'] ?? '') && $pending['operation'] === ($data['action'] ?? ''), 422);
                abort_unless($pending['target_version'] === $w->record($pending['target_id'])['version'], 409, 'Przedmiot zatwierdzenia zmienił się.');
                $data['payload'] = $pending['data'];
            }
            $complete = app(AuthorizedActions::class)->apply($w, $data['revision'], $data['source_hash'], $data['action'] ?? '', $data['record_id'] ?? '', $data['grant_id'] ?? '', $data['payload'] ?? []);
            $message = $complete ? 'Zapisano czynność i podstawę upoważnienia.' : 'Zapisano zgodę. Pozostałe wymagane stopnie oczekują na zatwierdzenie.';
        } else {
            $this->workflow->transition($w, $data['revision'], $data['source_hash'], $operation, $data['payload'] ?? []);
        }

        return redirect()->route($w->client ? 'client.audits.enms.show' : 'audits.enms.show', [$audit, $profile, 'tab' => $request->input('tab', 'pytania')])->with('success', $message);
    }

    public function draft(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $w = $this->workspace($request, $audit, $profile);
        $data = $request->validate(['revision' => 'required|integer|min:0', 'source_hash' => 'required|string|size:64']);
        // View composers have not run on this POST; resolve branding just as the existing ISO generators do.
        $brand = CompanySettings::first() ?? new CompanySettings(['name' => 'ENESA']);
        app(LeadershipDocuments::class)->draft($w, $data['revision'], $data['source_hash'], $request->all(), ['name' => $brand->name, 'logo' => $brand->logoDataUri()]);

        return redirect()->route('audits.enms.show', [$audit, $profile, 'tab' => $request->input('type') === 'karta' ? 'karta' : 'raport'])->with('success', 'Przygotowano projekt. Wydanie i zatwierdzenie są osobnymi czynnościami.');
    }

    public function document(Request $request, Audit $audit, IsoPlantProfile $profile, string $record)
    {
        $w = $this->workspace($request, $audit, $profile);
        $r = $w->record($record);
        abort_unless(in_array($r['kind'], ['document', 'document_draft']), 404);
        abort_if($w->client && $r['kind'] !== 'document', 403);
        $service = app(LeadershipDocuments::class);
        $bytes = $r['kind'] === 'document' ? $service->bytes($r) : $service->render($r['payload']);
        $filename = $r['kind'] === 'document' ? IsoSectionDocument::where('audit_id', $audit->id)->find($r['payload']['document_id'])?->original_filename : null;
        $filename ??= $service->filename($r['payload']);

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename, Str::ascii($filename)), 'Cache-Control' => 'private, no-store']);
    }

    public function excel(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $w = $this->workspace($request, $audit, $profile);
        $book = app(LeadershipWorkbook::class)->build($w);

        return response()->streamDownload(function () use ($book) {
            try {
                (new Xlsx($book))->setPreCalculateFormulas(false)->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, '5.1 Przywództwo.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    public function importExcel(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $w = $this->workspace($request, $audit, $profile);
        $request->validate(['file' => 'required|file|mimes:xlsx|max:5120']);
        app(LeadershipWorkbook::class)->import($w, $request->file('file')->getRealPath());

        return redirect()->route($w->client ? 'client.audits.enms.show' : 'audits.enms.show', [$audit, $profile, 'tab' => 'spojnosc'])->with('success', 'Zaimportowano odpowiedzi. Sprawdź ankietę przed zatwierdzeniem.');
    }
}
