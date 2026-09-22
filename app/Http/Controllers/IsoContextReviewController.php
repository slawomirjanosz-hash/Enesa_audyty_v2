<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\AuditType;
use App\Models\IsoContextReview;
use App\Models\IsoSectionDocument;
use App\Services\AuditorAccessService;
use App\Services\DocumentQuotaService;
use App\Services\IsoContextLibrary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

class IsoContextReviewController extends Controller
{
    public function __construct(private readonly IsoContextLibrary $library) {}

    public function template(AuditType $auditType)
    {
        abort_unless($auditType->slug === 'iso50001', 404);

        return view('audit-types.iso-context-library', ['auditType' => $auditType, 'questions' => $this->library->questions(), 'library' => $this->library->data()]);
    }

    private function access(Request $request, Audit $audit): bool
    {
        $client = $request->routeIs('client.*');
        if ($client) {
            $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();
        } else {
            abort_unless(app(AuditorAccessService::class)->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);
        }
        abort_unless($audit->surveys()->whereHas('auditType', fn ($q) => $q->where('slug', 'iso50001'))->exists(), 404);

        return $client;
    }

    private function year(Request $request): int
    {
        return (int) ($request->validate(['year' => 'nullable|integer|min:2020|max:2100'])['year'] ?? now()->year);
    }

    public function show(Request $request, Audit $audit)
    {
        $client = $this->access($request, $audit);
        $year = $this->year($request);
        $review = IsoContextReview::where('audit_id', $audit->id)->where('year', $year)->first()
            ?? new IsoContextReview(['year' => $year, 'revision' => 0, 'status' => 'draft', 'answers' => []]);
        $answers = $this->library->formAnswers(old('answers', $review->answers ?? []));

        return view('audits.iso-context-review', [
            'audit' => $audit, 'client' => $client, 'review' => $review, 'answers' => $answers,
            'questions' => $this->library->questions(), 'factors' => $this->library->factors($answers),
            'stakeholders' => $this->library->stakeholders($answers), 'mode' => $this->library->mode($answers),
            'blockers' => $this->library->blockers($answers),
            'history' => $review->exists ? DB::table('iso_context_revisions')->leftJoin('users', 'users.id', '=', 'iso_context_revisions.user_id')
                ->where('iso_context_review_id', $review->id)->orderByDesc('iso_context_revisions.id')->limit(30)
                ->get(['iso_context_revisions.revision', 'iso_context_revisions.action', 'iso_context_revisions.note', 'iso_context_revisions.created_at', 'users.name']) : collect(),
            'routePrefix' => $client ? 'client.audits.iso-review.' : 'audits.iso-review.',
        ]);
    }

    private function answers(Request $request, array $previous, bool $client): array
    {
        $input = $request->input('answers');
        $decimal = fn ($value) => is_string($value) ? str_replace(',', '.', trim($value)) : $value;
        if (is_array($input)) {
            foreach ($this->library->questions() as $question) {
                if ($question['numeric'] && is_array($input['facts'] ?? null) && isset($input['facts'][$question['kod']])) {
                    $input['facts'][$question['kod']] = $decimal($input['facts'][$question['kod']]);
                }
            }
            if (is_array($input['energy'] ?? null)) {
                foreach ($input['energy'] as &$energyRow) {
                    if (is_array($energyRow) && isset($energyRow['tj'])) {
                        $energyRow['tj'] = $decimal($energyRow['tj']);
                    }
                }
                unset($energyRow);
            }
            $request->merge(['answers' => $input]);
        }
        $rules = ['answers' => 'required|array', 'answers.facts' => 'nullable|array',
            'answers.factors' => 'nullable|array', 'answers.stakeholders' => 'nullable|array', 'answers.swot' => 'nullable|array'];
        foreach ($this->library->questions() as $q) {
            $rules['answers.facts.'.$q['kod']] = $q['numeric']
                ? ['nullable', function ($attribute, $value, $fail) {
                    if ($value !== 'nie wiem' && (! is_numeric($value) || (float) $value < 0 || ! is_finite((float) $value))) {
                        $fail('Wpisz liczbę nieujemną albo „nie wiem”.');
                    }
                }] : ['nullable', Rule::in(array_keys($q['options']))];
        }
        foreach ($this->library->data()['czynniki_kontekstowe_4_1'] as $factor) {
            $prefix = 'answers.factors.'.$factor['kod'];
            $rules[$prefix] = 'nullable|array';
            $rules[$prefix.'.selected'] = 'nullable|boolean';
            $rules[$prefix.'.text'] = 'nullable|string|max:5000';
            $rules[$prefix.'.reason'] = 'nullable|string|max:2000';
        }
        foreach ($this->library->data()['strony_zainteresowane_4_2'] as $party) {
            $prefix = 'answers.stakeholders.'.$party['kod'];
            $rules[$prefix] = 'nullable|array';
            $rules[$prefix.'.selected'] = 'nullable|boolean';
            foreach (['text', 'source', 'reason'] as $key) {
                $rules[$prefix.'.'.$key] = 'nullable|string|max:5000';
            }
            if (! $client) {
                $rules[$prefix.'.compliance'] = ['nullable', Rule::in(['pending', 'yes', 'no'])];
            }
        }
        foreach (['scope', 'base_documents', 'climate_reason'] as $key) {
            $rules['answers.'.$key] = 'nullable|string|max:10000';
        }
        foreach (['audit_year', 'csrd_year'] as $key) {
            $rules['answers.'.$key] = 'nullable|integer|min:1900|max:2100';
        }
        $rules['answers.contract_end'] = 'nullable|date';
        $rules['answers.energy'] = 'nullable|array|max:12';
        $rules['answers.energy.*'] = 'array:name,tj,source';
        $rules['answers.energy.*.name'] = 'nullable|string|max:200';
        $rules['answers.energy.*.tj'] = 'nullable|numeric|min:0|max:100000000';
        $rules['answers.energy.*.source'] = 'nullable|string|max:500';
        $rules['answers.energy_unknown'] = 'nullable|boolean';
        if (! $client) {
            foreach (['strengths', 'weaknesses', 'opportunities', 'threats'] as $key) {
                $rules['answers.swot.'.$key] = 'nullable|string|max:10000';
            }
            $rules['answers.conclusions'] = 'nullable|array|max:10';
            $rules['answers.conclusions.*'] = 'array:finding,decision,document';
            foreach (['finding', 'decision', 'document'] as $key) {
                $rules['answers.conclusions.*.'.$key] = 'nullable|string|max:5000';
            }
        }
        $valid = $request->validate($rules)['answers'];
        // Explicitly whitelist top-level data: Laravel's array validation may retain unruled keys.
        $out = [];
        $out['facts'] = array_intersect_key($valid['facts'] ?? [], array_flip(array_column($this->library->questions(), 'kod')));
        foreach (['factors' => 'czynniki_kontekstowe_4_1', 'stakeholders' => 'strony_zainteresowane_4_2'] as $key => $table) {
            foreach ($this->library->data()[$table] as $row) {
                $id = $row['kod'];
                if (isset($valid[$key][$id])) {
                    $out[$key][$id] = array_intersect_key($valid[$key][$id], array_flip($key === 'factors' ? ['selected', 'text', 'reason'] : ['selected', 'text', 'source', 'reason', ...($client ? [] : ['compliance'])]));
                    if ($client && $key === 'stakeholders') {
                        $out[$key][$id]['compliance'] = $previous[$key][$id]['compliance'] ?? 'pending';
                    }
                }
            }
            // Hidden unmatched decisions are retained for explicit re-verification, not silently lost.
            $out[$key] = array_replace($previous[$key] ?? [], $out[$key] ?? []);
        }
        foreach ($this->library->data()['czynniki_kontekstowe_4_1'] as $factor) {
            if ($factor['rodzaj'] === 'AUTO' || str_starts_with($factor['kod'], 'KTX-ZR-') || $factor['kod'] === 'KTX-WO-03') {
                unset($out['factors'][$factor['kod']]);
            }
        }
        foreach (['scope', 'base_documents', 'climate_reason', 'audit_year', 'csrd_year', 'contract_end', 'energy', 'energy_unknown'] as $key) {
            $out[$key] = $valid[$key] ?? null;
        }
        $out['energy'] = array_values($out['energy'] ?? []);
        $activeEnergy = array_filter($out['energy'], fn ($e) => filled($e['name'] ?? null) || filled($e['tj'] ?? null) || filled($e['source'] ?? null));
        $knownEnergy = array_filter($activeEnergy, fn ($e) => filled($e['tj'] ?? null));
        $out['facts']['ZUZYCIE_TJ'] = ($out['energy_unknown'] ?? false) || count($knownEnergy) !== count($activeEnergy)
            ? 'nie wiem' : ($knownEnergy ? (string) array_sum(array_column($knownEnergy, 'tj')) : '');
        foreach (['swot', 'conclusions'] as $key) {
            $out[$key] = $client ? ($previous[$key] ?? []) : ($valid[$key] ?? []);
        }
        $out['conclusions'] = array_values($out['conclusions']);
        $before = collect($this->library->stakeholders($previous))->keyBy('kod');
        foreach ($this->library->stakeholders($out) as $party) {
            $keys = array_flip(['selected', 'text', 'source', 'reason', 'requirements', 'matches']);
            if (isset($before[$party['kod']]) && array_intersect_key($before[$party['kod']], $keys) !== array_intersect_key($party, $keys)) {
                $out['stakeholders'][$party['kod']]['compliance'] = 'pending';
            }
        }

        return $out;
    }

    public function update(Request $request, Audit $audit)
    {
        $client = $this->access($request, $audit);
        $year = $this->year($request);
        $data = $request->validate(['revision' => 'required|integer|min:0', 'operation' => ['required', Rule::in(['save', 'submit', 'withdraw', 'review', 'return', 'approve', 'reopen', 'request_reopen', 'copy'])], 'note' => 'nullable|string|max:4000']);
        DB::transaction(function () use ($request, $audit, $client, $year, $data) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $review = IsoContextReview::firstOrCreate(['audit_id' => $audit->id, 'year' => $year]);
            abort_if($review->revision !== (int) $data['revision'], 409, 'Dane zmieniły się w innym oknie. Odśwież stronę przed zapisem.');
            $op = $data['operation'];
            $old = $review->answers ?? [];
            if (in_array($op, ['save', 'submit'], true)) {
                abort_unless(in_array($review->status, $client ? ['draft', 'returned'] : ['draft', 'returned', 'reviewing'], true), 403, 'Ankieta jest zablokowana. Najpierw otwórz ją ponownie.');
                $review->answers = $this->answers($request, $old, $client);
                if ($op === 'submit') {
                    foreach ($this->library->questions() as $question) {
                        if (! filled($review->answers['facts'][$question['kod']] ?? null)) {
                            throw ValidationException::withMessages(['answers' => 'Odpowiedz na wszystkie pytania. Jeśli nie znasz odpowiedzi, wybierz lub wpisz „nie wiem”.']);
                        }
                    }
                    $review->status = 'submitted';
                }
            } elseif ($op === 'copy') {
                abort_unless($review->revision === 0, 422, 'Kopiowanie jest dostępne tylko dla pustego roku.');
                $previous = IsoContextReview::where('audit_id', $audit->id)->where('year', '<', $year)->orderByDesc('year')->firstOrFail();
                $review->answers = $previous->answers;
                $review->status = 'draft';
            } elseif ($op === 'request_reopen') {
                abort_unless($client && in_array($review->status, ['reviewing', 'approved'], true), 403);
                if (! filled($data['note'] ?? null)) {
                    throw ValidationException::withMessages(['note' => 'Podaj powód ponownego otwarcia.']);
                }
            } else {
                $allowed = match ($op) {
                    'withdraw' => $client && $review->status === 'submitted',
                    'review' => ! $client && $review->status === 'submitted',
                    'return' => ! $client && $review->status === 'reviewing',
                    'approve' => ! $client && $review->status === 'reviewing',
                    'reopen' => ! $client && $review->status === 'approved',
                    default => false,
                };
                abort_unless($allowed, 403);
                if ($op === 'approve' && ($blockers = $this->library->blockers($old))) {
                    throw ValidationException::withMessages(['answers' => $blockers]);
                }
                if (in_array($op, ['return', 'reopen'], true) && ! filled($data['note'] ?? null)) {
                    throw ValidationException::withMessages(['note' => 'Podaj uzasadnienie.']);
                }
                $review->status = match ($op) {
                    'withdraw' => 'draft', 'review' => 'reviewing', 'return', 'reopen' => 'returned', 'approve' => 'approved',
                };
                if (! $client) {
                    $review->reviewer_id = $request->user()->id;
                }
            }
            $review->revision++;
            $review->save();
            DB::table('iso_context_revisions')->insert(['iso_context_review_id' => $review->id, 'user_id' => $request->user()->id,
                'revision' => $review->revision, 'action' => $op, 'status' => $review->status,
                'answers' => json_encode($review->answers ?? [], JSON_THROW_ON_ERROR), 'note' => $data['note'] ?? null, 'created_at' => now()]);
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'updated', 'auditable_type' => Audit::class,
                'auditable_id' => $audit->id, 'subject_label' => 'ISO 4.1–4.2 / '.$year.' / '.$op,
                'changes' => ['revision' => ['old' => $review->revision - 1, 'new' => $review->revision]], 'route_name' => $request->route()->getName()]);
        });

        return redirect()->route(($client ? 'client.' : '').'audits.iso-review.show', ['audit' => $audit, 'year' => $year])
            ->with('success', 'Zapisano. Propozycje czynników i stron zostały przeliczone.');
    }

    public function export(Request $request, Audit $audit)
    {
        $this->access($request, $audit);
        $year = $this->year($request);
        $data = $request->validate(['format' => ['required', Rule::in(['pdf', 'docx'])], 'preview' => 'nullable|boolean']);
        $review = IsoContextReview::where('audit_id', $audit->id)->where('year', $year)->firstOrFail();
        $rows = [['Organizacja', $audit->company->name], ['Rok / rewizja', $year.' / '.$review->revision], ['Tryb', $this->library->mode($review->answers ?? [])]];
        foreach (['scope' => 'Zakres systemu', 'climate_reason' => 'Uzasadnienie oceny istotności zmiany klimatu',
            'base_documents' => 'Dokumenty istniejącego systemu', 'audit_year' => 'Rok wykonania audytu energetycznego',
            'csrd_year' => 'Rok rozpoczęcia raportowania CSRD', 'contract_end' => 'Data końca umowy na energię'] as $key => $label) {
            $rows[] = [$label, (string) (($review->answers[$key] ?? '') ?: '[do uzupełnienia]')];
        }
        foreach ($review->answers['energy'] ?? [] as $row) {
            if (filled($row['name'] ?? null) || filled($row['tj'] ?? null) || filled($row['source'] ?? null)) {
                $rows[] = ['Nośnik energii · '.($year - 1), (($row['name'] ?? '') ?: '[do uzupełnienia]')
                    ."\nZużycie [TJ]: ".(filled($row['tj'] ?? null) ? $row['tj'] : '[do uzupełnienia]')
                    ."\nŹródło / przeliczenie: ".(($row['source'] ?? '') ?: '[do uzupełnienia]')];
            }
        }
        if ($blockers = $this->library->blockers($review->answers ?? [])) {
            $rows[] = ['Uwagi wymagające uzupełnienia lub rozstrzygnięcia', implode("\n", $blockers)];
        }
        foreach ($this->library->questions() as $q) {
            $value = $review->answers['facts'][$q['kod']] ?? '';
            $rows[] = [$q['pytanie'], $q['options'][$value] ?? (filled($value) ? (string) $value : '[do uzupełnienia]')];
        }
        foreach ($this->library->factors($review->answers ?? []) as $row) {
            if ($row['selected'] && ! $row['pending'] && ! $row['stale']) {
                $rows[] = [$row['kod'].' · '.$row['wymiar'], ($row['text'] ?: '[do uzupełnienia]')."\nSkutek: ".$row['skutek_dla_systemu']];
            }
        }
        foreach ($this->library->stakeholders($review->answers ?? []) as $row) {
            if ($row['selected']) {
                $requirements = array_map(fn ($r) => $r['source'].': '.$r['text'], $row['requirements']);
                $rows[] = [$row['kod'].' · '.$row['strona'], ($row['matches'] ? '' : "UWAGA: wybór nie odpowiada aktualnym danym — do ponownej oceny.\n")
                    .($row['text'] ?: '[do uzupełnienia]')."\n".implode("\n", $requirements)
                    ."\nŹródło: ".($row['source'] ?: '[do uzupełnienia]')
                    ."\nOcena zgodności: ".(['pending' => 'do rozstrzygnięcia', 'yes' => 'tak', 'no' => 'nie'][$row['compliance']] ?? 'do rozstrzygnięcia')
                    ."\nUzasadnienie: ".($row['reason'] ?: '[do uzupełnienia]')];
            }
        }
        foreach (['strengths' => 'Mocne strony', 'weaknesses' => 'Słabe strony', 'opportunities' => 'Szanse', 'threats' => 'Zagrożenia'] as $key => $label) {
            $rows[] = ['SWOT · '.$label, $review->answers['swot'][$key] ?? '[do uzupełnienia]'];
        }
        $conclusions = array_pad(array_values($review->answers['conclusions'] ?? []), 4, []);
        foreach ($conclusions as $i => $row) {
            $rows[] = ['Wniosek '.($i + 1), implode("\n", array_map(fn ($k) => ($row[$k] ?? '') ?: '[do uzupełnienia]', ['finding', 'decision', 'document']))];
        }
        $format = $data['format'];
        if ($format === 'pdf') {
            $contents = Pdf::loadView('audits.iso-context-review-pdf', compact('rows'))->setPaper('a4')->output();
        } else {
            Settings::setOutputEscapingEnabled(true);
            $word = new PhpWord;
            $word->setDefaultFontName('Arial');
            $word->setDefaultFontSize(10);
            $section = $word->addSection();
            $section->addText('ISO 50001 · Kontekst i strony zainteresowane', ['bold' => true]);
            foreach ($rows as [$label, $text]) {
                $section->addText($label, ['bold' => true]);
                $text = filled($text) ? $text : '[do uzupełnienia]';
                $section->addText($text, str_contains($text, '[do uzupełnienia]') ? ['color' => 'B42222'] : []);
            }
            $temp = tempnam(storage_path('framework'), 'iso-review-');
            abort_if($temp === false, 500, 'Nie udało się utworzyć pliku tymczasowego.');
            try {
                IOFactory::createWriter($word, 'Word2007')->save($temp);
                $contents = file_get_contents($temp);
            } finally {
                @unlink($temp);
            }
        }
        abort_unless(is_string($contents) && $contents !== '', 500, 'Nie udało się wygenerować dokumentu.');
        $filename = 'ISO_4_1_4_2_'.$year.'_r'.$review->revision.'.'.$format;
        $path = 'iso50001/client/'.$audit->id.'/4-1/generated/'.Str::uuid().'.'.$format;
        $mime = $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        if ($format === 'pdf' && ($data['preview'] ?? false)) {
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'download', 'auditable_type' => Audit::class,
                'auditable_id' => $audit->id, 'subject_label' => 'Podgląd: '.$filename, 'route_name' => $request->route()->getName()]);

            return response($contents, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="'.$filename.'"']);
        }
        app(DocumentQuotaService::class)->assertAdditional($request->user()->id, strlen($contents));
        abort_unless(Storage::disk('local')->put($path, $contents), 500);
        IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => '4-1', 'scope' => 'client',
            'title' => 'Kontekst i strony zainteresowane (4.1–4.2)', 'description' => 'Dokument wygenerowany z zapisanych odpowiedzi ankiety.',
            'document_year' => $year, 'version_number' => $review->revision.'.0', 'original_filename' => $filename,
            'stored_path' => $path, 'mime_type' => $mime, 'size' => strlen($contents), 'content_base64' => base64_encode($contents), 'uploaded_by' => $request->user()->id]);

        ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'download', 'auditable_type' => Audit::class,
            'auditable_id' => $audit->id, 'subject_label' => $filename, 'route_name' => $request->route()->getName()]);

        return response($contents, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }
}
