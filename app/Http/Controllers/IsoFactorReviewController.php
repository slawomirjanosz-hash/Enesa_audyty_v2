<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\CompanySettings;
use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Services\AuditorAccessService;
use App\Services\IsoFactorConclusions;
use App\Services\IsoFactorDocument;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoHtmlState;
use App\Services\IsoPlatformRelease;
use App\Services\IsoQuestionnaireWorkbook;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class IsoFactorReviewController extends Controller
{
    public function __construct(private readonly IsoFactorQuestionnaire $questionnaire) {}

    private function access(Request $request, Audit $audit, IsoPlantProfile $profile, bool $write = false): bool
    {
        $client = $request->routeIs('client.*');
        if ($client) {
            $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();
        } else {
            abort_unless(app(AuditorAccessService::class)->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);
            abort_if($write && ! $request->user()->can('audits.manage'), 403);
        }
        abort_unless($profile->audit_id === $audit->id && DB::table('iso_plant_sites')->where('id', $profile->site_id)->where('company_id', $audit->company_id)->exists(), 404);
        abort_unless($audit->surveys()->whereHas('auditType', fn ($q) => $q->where('slug', 'iso50001'))->exists(), 404);
        abort_unless(IsoPlantProfile::whereKey($profile->id)->latestPerSite()->exists(), 409, 'Otwórz ankietę z aktualnego profilu zakładu.');

        return $client;
    }

    private function prefix(bool $client): string
    {
        return $client ? 'client.audits.factors.' : 'audits.factors.';
    }

    public function show(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit, $profile);
        $review = IsoFactorReview::firstOrNew(['audit_id' => $audit->id, 'site_id' => $profile->site_id], ['status' => 'editing', 'lock_version' => 0, 'answers' => [], 'basis' => []]);
        $facts = $this->questionnaire->facts($profile);
        $answers = $this->questionnaire->currentAnswers($review->answers ?? [], $review->basis ?? [], $facts);
        if ($request->boolean('source_example')) {
            abort_unless(! $client && $request->user()->can('audits.manage'), 403);
            $answers = app(IsoPlatformRelease::class)->answers('4-1', ['facts' => $facts]);
        }

        return view('audits.factors.show', [
            'audit' => $audit, 'profile' => $profile, 'review' => $review, 'client' => $client,
            'prefix' => $this->prefix($client), 'questionnaire' => $this->questionnaire, 'facts' => $facts, 'answers' => $answers,
            'exampleSwot' => ! $client && $request->boolean('source_example') ? app(IsoPlatformRelease::class)->swot() : null,
            'ready' => $profile->status === 'approved' && $profile->client_approval && $profile->auditor_approval,
            'stale' => $review->exists && $review->source_hash !== $this->questionnaire->hash($profile),
            'canWrite' => $client || $request->user()->can('audits.manage'),
            'canClientApprove' => $client && $request->user()->hasRole('client_admin'),
            'events' => $review->exists ? DB::table('iso_factor_events')->where('review_id', $review->id)->orderByDesc('id')->limit(50)->get(['action', 'user_name', 'created_at']) : collect(),
        ]);
    }

    public function update(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit, $profile, true);
        $htmlState = $request->has('html_state') ? app(IsoHtmlState::class)->ingest($request, '4-1', ['facts' => $this->questionnaire->facts($profile)], IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->first()?->answers ?? []) : null;
        $data = $request->validate(['lock_version' => 'required|integer|min:0', 'source_hash' => 'required|string|size:64', 'operation' => ['required', Rule::in(['save', 'submit', 'approve', 'save_swot', 'publish', 'return', 'withdraw'])], 'answers' => 'nullable|array', 'note' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($request, $audit, $profile, $client, $data, $htmlState) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $profile = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->access($request, $audit, $profile, true);
            abort_unless($profile->status === 'approved' && $profile->client_approval && $profile->auditor_approval, 409, 'Najpierw zatwierdź profil zakładu przez klienta i audytora.');
            $hash = $this->questionnaire->hash($profile);
            abort_unless(hash_equals($hash, $data['source_hash']), 409, 'Profil zakładu zmienił się. Odśwież ankietę, aby wczytać aktualne czynniki.');
            $review = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->lockForUpdate()->first() ?? new IsoFactorReview(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'status' => 'editing', 'lock_version' => 0, 'answers' => [], 'basis' => []]);
            abort_unless($review->lock_version === (int) $data['lock_version'], 409, 'Ankieta zmieniła się w innym oknie. Odśwież stronę.');
            $publishing = $data['operation'] === 'publish';
            $op = $publishing ? 'approve' : $data['operation'];
            $registry = app(IsoFactorConclusions::class);
            $oldRegistry = $registry->registry($review);
            $stale = $review->exists && $review->source_hash !== $hash;
            abort_if($stale && ! in_array($op, ['save', 'submit']), 409, 'Zapisz ponownie ankietę po aktualizacji profilu zakładu.');
            $facts = $this->questionnaire->facts($profile);
            if ($publishing && $htmlState !== null) {
                $proposed = $this->questionnaire->normalize($request->input('answers', []), $facts, true);
                $stored = $this->questionnaire->normalize($review->answers ?? [], $facts, true);
                abort_unless($proposed === $stored, 409, 'Odpowiedzi klienta zmieniły się. Zapisz je i uzyskaj ponowne zatwierdzenie przed publikacją.');
            }
            if (in_array($op, ['save_swot', 'approve'])) {
                abort_unless(! $client && $review->client_approval && in_array($review->status, ['submitted', 'approved']), 403);
                $confirmedCodes = array_column(array_filter($this->questionnaire->factors($facts, $review->answers ?? []), fn ($factor) => $factor['visible'] && ($factor['rodzaj'] === 'AUTO' || data_get($review->answers, 'factors.'.$factor['kod'].'.decyzja') === 'potwierdzony')), 'kod');
                $rules = ['swot' => 'required|array:strengths,weaknesses,opportunities,threats,conclusions,wnioski,podsum',
                    'swot.wnioski' => ($op === 'approve' ? 'required' : 'nullable').'|array|min:1|max:100',
                    'swot.podsum' => 'nullable|array:ocena,ustalenia,decyzje,braki',
                    'swot.podsum.ocena' => 'nullable|string|max:10000',
                    'swot.podsum.ustalenia' => 'nullable|array|max:30',
                    'swot.podsum.ustalenia.*.czynnik' => ['nullable', Rule::in(array_merge($confirmedCodes, array_column($review->answers['custom'] ?? [], 'id')))],
                    'swot.podsum.ustalenia.*.komentarz' => 'nullable|string|max:3000',
                    'swot.podsum.decyzje' => 'nullable|array|max:30',
                    'swot.podsum.decyzje.*' => 'array:przedmiot,rekomendacja,uzasadnienie,termin,status,zatwierdzil',
                    'swot.podsum.decyzje.*.*' => 'nullable|string|max:3000',
                    'swot.podsum.braki' => 'nullable|array|max:30',
                    'swot.podsum.braki.*.t' => 'nullable|string|max:3000',
                    'swot.wnioski.*' => 'array',
                    'swot.wnioski.*.czynnik' => ['nullable', Rule::in($confirmedCodes)],
                ];
                foreach (['znaczenie', 'decyzja', 'dzialanie', 'sprawdzenie'] as $key) {
                    $rules['swot.wnioski.*.'.$key] = ($op === 'approve' ? 'required' : 'nullable').'|string|max:5000';
                }
                $labels = [];
                foreach (IsoFactorReview::SWOT_FIELDS as $key => $label) {
                    $rules['swot.'.$key] = 'nullable|string|max:10000';
                    $labels['swot.'.$key] = $label;
                }
                $validated = $request->validate($rules, [], $labels);
                if ($op === 'save_swot' && $validated['swot'] === array_intersect_key($review->swot ?? [], IsoFactorReview::SWOT_FIELDS + ['wnioski' => 'Wnioski'])) {
                    return;
                }
                if ($htmlState !== null) {
                    $rows = $registry->validate($validated['swot']['wnioski'] ?? [], array_merge(array_column($this->questionnaire->schema()['czynniki'], 'kod'), array_column($review->answers['custom'] ?? [], 'id')), $op === 'approve');
                    foreach ($rows as &$row) {
                        $row['scope'] = collect($oldRegistry)->firstWhere('id', $row['id'])['scope'] ?? 'consultant';
                    }
                    unset($row);
                    abort_unless($registry->forClient($rows) === $registry->forClient($oldRegistry), 409, 'Zmieniono wniosek należący do odpowiedzi klienta. Zapisz korektę odpowiedzi i uzyskaj ponowne zatwierdzenie klienta.');
                    $validated['swot']['wnioski'] = $rows;
                }
                $review->swot = $validated['swot'] + ['author' => $this->approval($request)];
                if ($htmlState !== null) {
                    $review->answers = array_replace_recursive($review->answers ?? [], ['_html' => array_intersect_key($htmlState, array_flip(['ID_SEQ', '_schema']))]);
                }
            }
            if (in_array($op, ['save', 'submit'])) {
                abort_unless(! $client || $stale || in_array($review->status, ['editing', 'returned', 'auditor_corrected']), 409);
                abort_if($op === 'submit' && (! $client || ! $request->user()->hasRole('client_admin')), 403);
                $request->validate(['answers' => 'required|array', 'complete_form' => 'required|accepted']);
                $input = $data['answers'];
                $previous = $this->questionnaire->currentAnswers($review->answers ?? [], $review->basis ?? [], $facts);
                $input['factors'] = array_replace_recursive($previous['factors'] ?? [], is_array($input['factors'] ?? null) ? $input['factors'] : []);
                if ($htmlState === null && ($input['FAKT_KLIMAT_ISTOTNY'] ?? null) !== ($previous['FAKT_KLIMAT_ISTOTNY'] ?? null)) {
                    foreach ($this->questionnaire->factors($facts, $previous) as $factor) {
                        if (array_key_exists('FAKT_KLIMAT_ISTOTNY', $factor['dependencies'])) {
                            unset($input['factors'][$factor['kod']]['decyzja']);
                        }
                    }
                }
                try {
                    $answers = $this->questionnaire->normalize($input, $facts, $op === 'submit');
                } catch (ValidationException $error) {
                    throw ValidationException::withMessages(collect($error->errors())->mapWithKeys(fn ($messages, $key) => ['answers.'.$key => $messages])->all());
                }
                if ($htmlState !== null || isset($review->answers['_html'])) {
                    $answers['_html'] = $htmlState ?? $review->answers['_html'];
                }
                $newRegistry = $oldRegistry;
                if ($htmlState === null) {
                    foreach ($answers['factors'] as $code => $answer) {
                        foreach ($newRegistry as &$row) {
                            if ($row['scope'] === 'client' && in_array($code, $row['czynniki'], true)) {
                                foreach (['znaczenie' => 'znaczenie', 'decyzja' => 'decyzja_w', 'dzialanie' => 'dzialanie', 'sprawdzenie' => 'sprawdzenie'] as $key => $field) {
                                    $row[$key] = $answer[$field] ?? '';
                                }
                            }
                        }
                    }
                    unset($row);
                }
                if ($htmlState !== null) {
                    $rows = $registry->validate($request->input('factor_conclusions', []), array_merge(array_column($this->questionnaire->schema()['czynniki'], 'kod'), array_column($answers['custom'] ?? [], 'id')));
                    foreach ($rows as &$row) {
                        $oldRow = collect($oldRegistry)->firstWhere('id', $row['id']);
                        abort_if($client && $oldRow && $oldRow['scope'] !== 'client', 403);
                        $row['scope'] = $oldRow['scope'] ?? 'client';
                    }
                    unset($row);
                    $newRegistry = $client ? array_merge(array_values(array_filter($oldRegistry, fn ($row) => $row['scope'] !== 'client')), $rows) : $rows;
                }
                $changed = $stale || $answers !== ($review->answers ?? []) || $newRegistry !== $oldRegistry;
                if (! $client && ! $changed) {
                    return;
                }
                $changeField = $client ? 'client_changes' : 'auditor_changes';
                $changes = $review->$changeField ?? [];
                $track = ! $client || $review->auditor_changes || $review->client_changes || in_array($review->status, ['returned', 'auditor_corrected']);
                $before = ($review->answers['factors'] ?? []) + ['FAKT_KLIMAT_ISTOTNY' => [$review->answers['FAKT_KLIMAT_ISTOTNY'] ?? null, $review->answers['climate_reason'] ?? null], 'custom' => $review->answers['custom'] ?? []];
                $after = $answers['factors'] + ['FAKT_KLIMAT_ISTOTNY' => [$answers['FAKT_KLIMAT_ISTOTNY'], $answers['climate_reason']], 'custom' => $answers['custom']];
                $before['PRAWO'] = $review->answers['PRAWO'] ?? [];
                $after['PRAWO'] = $answers['PRAWO'] ?? [];
                foreach ($after as $key => $value) {
                    if ($track && ($before[$key] ?? null) !== $value) {
                        $changes[$key] = ['before' => $changes[$key]['before'] ?? ($before[$key] ?? null), 'after' => $value, 'by' => $request->user()->name];
                    }
                }
                if ($review->document_id) {
                    app(IsoFactorDocument::class)->retainLegacyAssociation($review);
                    IsoSectionDocument::whereKey($review->document_id)->update(['description' => 'Dokument historyczny — ankieta 4.1 została zmieniona.']);
                }
                $review->$changeField = $changes ?: null;
                if ($op === 'submit') {
                    $review->auditor_changes = null;
                }
                $review->swot = array_replace($review->swot ?? [], ['wnioski' => $newRegistry]);
                $review->answers = $answers;
                $review->basis = array_column($this->questionnaire->factors($facts, $answers), 'basis', 'kod');
                $review->status = $op === 'submit' ? 'submitted' : (! $client || $review->status === 'auditor_corrected' ? 'auditor_corrected' : 'editing');
                $review->client_approval = $op === 'submit' ? $this->approval($request) : null;
                $review->auditor_approval = null;
                $review->document_id = null;
                $review->issuer = null;
                $review->review_note = null;
            } elseif ($op === 'save_swot') {
                $wasApproved = $review->status === 'approved';
                if ($review->document_id) {
                    app(IsoFactorDocument::class)->retainLegacyAssociation($review);
                    IsoSectionDocument::whereKey($review->document_id)->update(['description' => 'Dokument historyczny — analiza SWOT została zmieniona.']);
                }
                $review->document_id = null;
                $review->auditor_approval = null;
                $review->status = 'submitted';
            } elseif ($op === 'approve') {
                abort_unless(! $client && in_array($review->status, ['submitted', 'approved']) && $review->client_approval, 403);
                abort_if(($review->client_approval['user_id'] ?? null) === $request->user()->id, 403);
                $request->validate(['note' => 'required|string|max:3000']);
                $this->questionnaire->normalize($review->answers, $facts, true);
                $review->status = 'approved';
                $review->auditor_approval = $this->approval($request);
                $review->auditor_changes = $review->client_changes = null;
                $review->review_note = $data['note'];
                $issuer = CompanySettings::first();
                $review->issuer = ['name' => $issuer?->name, 'logo' => $issuer?->logoDataUri()];
            } elseif ($op === 'return') {
                abort_unless(! $client && $review->status === 'submitted', 403);
                $request->validate(['note' => 'required|string|max:3000']);
                $review->status = 'returned';
                $review->review_note = $data['note'];
                $review->client_approval = null;
            } elseif ($op === 'withdraw') {
                abort_unless($client && $request->user()->hasRole('client_admin') && $review->status === 'submitted', 403);
                $review->status = 'editing';
                $review->client_approval = null;
            }
            $review->source_profile_id = $profile->id;
            $review->source_hash = $hash;
            $review->lock_version++;
            $review->save();
            if ($publishing) {
                app(IsoFactorDocument::class)->publish($review, $profile, $request->user());
            }
            DB::table('iso_factor_events')->insert(['review_id' => $review->id, 'user_name' => $request->user()->name, 'action' => $op, 'snapshot' => json_encode($review->only(['swot', 'answers', 'basis', 'source_profile_id', 'source_hash', 'status', 'client_approval', 'auditor_approval', 'auditor_changes', 'client_changes', 'review_note']), JSON_THROW_ON_ERROR), 'created_at' => now()]);
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'update', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => 'Ankieta czynników 4.1 — '.$op, 'route_name' => $request->route()->getName()]);
        });

        return redirect()->route($this->prefix($client).'show', [$audit, $profile])->with('success', 'Zapisano ankietę czynników 4.1.');
    }

    private function approval(Request $request): array
    {
        return ['user_id' => $request->user()->id, 'name' => $request->user()->name, 'at' => now()->toIso8601String()];
    }

    public function excel(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $this->access($request, $audit, $profile);
        abort_unless($profile->status === 'approved' && $profile->client_approval && $profile->auditor_approval, 409, 'Najpierw zatwierdź profil zakładu.');
        $review = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->first();

        return app(IsoQuestionnaireWorkbook::class)->download($profile, 'factors', $review);
    }

    public function importExcel(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $this->access($request, $audit, $profile, true);
        $request->validate(['excel' => 'required|file|mimes:xlsx|max:10240']);
        $review = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->first();
        $data = app(IsoQuestionnaireWorkbook::class)->read($request->file('excel')->getRealPath(), $profile, 'factors', $review);
        $request->replace($data + ['operation' => 'save', 'complete_form' => '1']);

        return $this->update($request, $audit, $profile)->with('success', 'Zaimportowano odpowiedzi z Excela. Sprawdź czynniki i decyzje przed zatwierdzeniem ankiety.');
    }

    public function pdf(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit, $profile, ! $request->boolean('preview'));
        $data = $request->validate(['lock_version' => 'required|integer|min:0', 'preview' => 'nullable|boolean']);
        $contents = DB::transaction(function () use ($request, $audit, $profile, $data, $client) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $profile = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->access($request, $audit, $profile);
            $review = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->lockForUpdate()->firstOrFail();
            abort_unless($review->lock_version === (int) $data['lock_version'], 409);
            abort_unless($profile->status === 'approved' && $profile->client_approval && $profile->auditor_approval && $review->source_hash === $this->questionnaire->hash($profile), 409, 'Profil zakładu zmienił się — ponownie zatwierdź ankietę.');
            abort_unless($review->status === 'approved' && $review->client_approval && $review->auditor_approval, 403);
            $document = $review->document_id ? IsoSectionDocument::find($review->document_id) : null;
            abort_if($client && ! $document, 403, 'Dokument publikuje konsultant.');
            if (! ($data['preview'] ?? false) && ! $document) {
                $document = app(IsoFactorDocument::class)->publish($review, $profile, $request->user());
            }
            $contents = $document?->contents() ?: Pdf::loadView('audits.factors.pdf', ['review' => $review, 'profile' => $profile])->setPaper('a4')->output();
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'download', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => IsoFactorReview::DOCUMENT_TITLE, 'route_name' => $request->route()->getName()]);

            return $contents;
        });
        if ($data['preview'] ?? false) {
            return response($contents, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition('inline', IsoFactorReview::DOCUMENT_TITLE.'.pdf', Str::ascii(IsoFactorReview::DOCUMENT_TITLE).'.pdf'), 'Cache-Control' => 'private, no-store']);
        }

        return redirect()->route($this->prefix($client).'show', [$audit, $profile])->with('success', 'PDF zapisano w Dokumentacji punktu 4.1.');
    }

    public function document(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit, $profile);
        $request->validate(['edition' => 'nullable|integer|min:1', 'format' => ['nullable', Rule::in(['html', 'pdf'])]]);
        $review = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->firstOrFail();
        $service = app(IsoFactorDocument::class);
        $edition = $request->filled('edition') ? $service->editions($review)->firstWhere('id', (int) $request->edition) : ($client ? $service->editions($review)->first() : null);
        abort_if($request->filled('edition') && ! $edition, 404);
        abort_if($client && ! $edition, 403, 'Dokument nie został jeszcze opublikowany.');
        if ($edition && empty($edition->generation_metadata['html'])) {
            abort_unless($contents = $edition->contents(), 404);

            return response($contents, 200, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
        }
        $body = $edition?->generation_metadata['html'] ?? $service->body($review, $profile);
        if ($edition) {
            $review->issuer = $edition->generation_metadata['issuer'] ?? $review->issuer;
        }
        if ($request->input('format') === 'pdf') {
            $contents = $edition ? $edition->contents() : Pdf::loadView('audits.factors.pdf', compact('body', 'review', 'profile'))->setPaper('a4')->output();

            return response($contents, 200, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
        }

        return response()->view('audits.factors.pdf', compact('body', 'review', 'profile'))->header('Cache-Control', 'private, no-store');
    }
}
