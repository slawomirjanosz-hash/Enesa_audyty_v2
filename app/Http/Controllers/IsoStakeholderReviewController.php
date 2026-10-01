<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\CompanySettings;
use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Models\IsoStakeholderReview;
use App\Services\AuditorAccessService;
use App\Services\IsoStakeholderQuestionnaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class IsoStakeholderReviewController extends Controller
{
    public function __construct(private readonly IsoStakeholderQuestionnaire $questionnaire) {}

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
        abort_unless(IsoPlantProfile::whereKey($profile->id)->latestPerSite()->exists(), 409, 'Otwórz aktualny profil zakładu.');

        return $client;
    }

    private function prefix(bool $client): string
    {
        return $client ? 'client.audits.stakeholders.' : 'audits.stakeholders.';
    }

    public function show(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit, $profile);
        $factors = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->first();
        $review = IsoStakeholderReview::firstOrNew(['audit_id' => $audit->id, 'site_id' => $profile->site_id], ['status' => 'editing', 'lock_version' => 0]);
        $parties = $this->questionnaire->parties($profile, $factors);
        $answers = $this->questionnaire->currentAnswers($review->answers ?? [], $review->basis ?? [], $parties);
        $hash = $this->questionnaire->hash($profile, $factors);

        return view('audits.stakeholders.show', [
            'audit' => $audit, 'profile' => $profile, 'review' => $review, 'client' => $client,
            'prefix' => $this->prefix($client), 'questionnaire' => $this->questionnaire, 'parties' => $parties, 'answers' => $answers, 'hash' => $hash,
            'ready' => $this->questionnaire->ready($profile, $factors), 'stale' => $review->exists && $review->source_hash !== $hash,
            'canWrite' => $client || $request->user()->can('audits.manage'), 'canClientApprove' => $client && $request->user()->hasRole('client_admin'),
            'register' => $this->questionnaire->register($answers, $parties, $review->consultant ?? []),
            'events' => $review->exists ? DB::table('iso_stakeholder_events')->where('review_id', $review->id)->orderByDesc('id')->limit(50)->get(['action', 'user_name', 'created_at']) : collect(),
        ]);
    }

    private function approval(Request $request): array
    {
        return ['user_id' => $request->user()->id, 'name' => $request->user()->name, 'at' => now()->toIso8601String()];
    }

    private function invalidateDocuments(IsoStakeholderReview $review): void
    {
        IsoSectionDocument::whereIn('id', array_filter([$review->document_id, $review->compliance_document_id]))->update(['description' => 'Dokument historyczny — rejestr stron zainteresowanych został zmieniony.']);
        $review->document_id = $review->compliance_document_id = null;
        $review->compliance_register = null;
        $review->auditor_approval = null;
    }

    public function update(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit, $profile, true);
        $data = $request->validate(['lock_version' => 'required|integer|min:0', 'source_hash' => 'required|string|size:64', 'operation' => ['required', Rule::in(['save', 'submit', 'consultant', 'approve', 'return', 'withdraw'])], 'note' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($request, $audit, $profile, $client, $data) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $profile = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->access($request, $audit, $profile, true);
            $factors = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->lockForUpdate()->first();
            abort_unless($this->questionnaire->ready($profile, $factors), 409, 'Najpierw zatwierdź profil zakładu oraz ankietę 4.1 jako klient.');
            $hash = $this->questionnaire->hash($profile, $factors);
            abort_unless(hash_equals($hash, $data['source_hash']), 409, 'Dane źródłowe zmieniły się. Odśwież ankietę.');
            $review = IsoStakeholderReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->lockForUpdate()->first() ?? new IsoStakeholderReview(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'status' => 'editing', 'lock_version' => 0]);
            abort_unless($review->lock_version === (int) $data['lock_version'], 409, 'Ankieta zmieniła się w innym oknie. Odśwież stronę.');
            $op = $data['operation'];
            $stale = $review->exists && $review->source_hash !== $hash;
            abort_if($stale && ! in_array($op, ['save', 'submit']), 409, 'Zapisz ankietę po zmianie danych źródłowych.');
            $parties = $this->questionnaire->parties($profile, $factors);
            $before = $review->only(['answers', 'consultant', 'analysis']);
            if (in_array($op, ['save', 'submit'])) {
                abort_unless(! $client || $stale || in_array($review->status, ['editing', 'returned', 'auditor_corrected']), 409);
                abort_if($op === 'submit' && (! $client || ! $request->user()->hasRole('client_admin')), 403);
                $input = $request->validate(['answers' => 'required|array', 'complete_form' => 'required|accepted'])['answers'];
                $previous = $this->questionnaire->currentAnswers($review->answers ?? [], $review->basis ?? [], $parties);
                $input['parties'] = array_replace($previous['parties'] ?? [], is_array($input['parties'] ?? null) ? $input['parties'] : []);
                try {
                    $answers = $this->questionnaire->normalize($input, $parties, $op === 'submit', $review->consultant ?? []);
                } catch (ValidationException $error) {
                    throw ValidationException::withMessages(collect($error->errors())->mapWithKeys(fn ($messages, $key) => ['answers.'.$key => $messages])->all());
                }
                $changed = $stale || $answers !== ($review->answers ?? []);
                if (! $changed && $op === 'save') {
                    return;
                }
                $this->invalidateDocuments($review);
                $review->answers = $answers;
                $review->basis = array_column($parties, 'basis', 'kod');
                $review->client_approval = $op === 'submit' ? $this->approval($request) : null;
                $review->status = $op === 'submit' ? 'submitted' : (! $client || $review->status === 'auditor_corrected' ? 'auditor_corrected' : 'editing');
                if ($op === 'submit') {
                    $review->auditor_changes = null;
                }
            } elseif ($op === 'consultant') {
                abort_if($client || ! $review->exists, 403);
                $validated = $request->validate([
                    'consultant' => 'nullable|array|max:48', 'consultant.*' => 'array',
                    'consultant.*.zgodnosc' => ['nullable', Rule::in(['tak', 'nie', 'pending'])],
                    'consultant.*.reason' => 'nullable|string|max:3000', 'consultant.*.legal_basis' => 'nullable|string|max:5000',
                    'consultant.*.owner' => 'nullable|string|max:255', 'consultant.*.deadline' => 'nullable|date_format:Y-m-d',
                    'analysis' => 'nullable|array:summary,owner,reviewed_on,next_review', 'analysis.summary' => 'nullable|string|max:10000',
                    'analysis.owner' => 'nullable|string|max:255', 'analysis.reviewed_on' => 'nullable|date_format:Y-m-d', 'analysis.next_review' => 'nullable|date_format:Y-m-d|after_or_equal:analysis.reviewed_on',
                ]);
                $decisions = $review->consultant ?? [];
                foreach ($this->questionnaire->register($review->answers ?? [], $parties, $decisions) as $row) {
                    $code = $row['kod'];
                    $decision = array_intersect_key($validated['consultant'][$code] ?? [], array_flip(['zgodnosc', 'reason', 'legal_basis', 'owner', 'deadline']));
                    $decision['zgodnosc'] = $row['classification'] === 'TAK' ? 'tak' : ($row['classification'] === 'nie' ? 'nie' : ($decision['zgodnosc'] ?? 'pending'));
                    if ($row['classification'] === 'KANDYDAT' && $decision['zgodnosc'] !== 'pending' && ! filled($decision['reason'] ?? null)) {
                        throw ValidationException::withMessages(["consultant.$code.reason" => "$code: uzasadnij decyzję dotyczącą zgodności."]);
                    }
                    $decision['basis'] = $this->questionnaire->decisionHash($row);
                    $previousDecision = $decisions[$code] ?? [];
                    unset($previousDecision['author']);
                    if ($previousDecision !== $decision) {
                        $decisions[$code] = $decision + ['author' => $this->approval($request)];
                    }
                }
                if ($decisions === ($review->consultant ?? []) && ($validated['analysis'] ?? []) === ($review->analysis ?? [])) {
                    return;
                }
                $review->consultant = $decisions;
                $review->analysis = $validated['analysis'] ?? [];
                $this->invalidateDocuments($review);
                $review->client_approval = null;
                $review->status = 'auditor_corrected';
            } elseif ($op === 'approve') {
                abort_unless(! $client && $review->status === 'submitted' && $review->client_approval && ($review->client_approval['user_id'] ?? null) !== $request->user()->id, 403);
                $this->questionnaire->normalize($review->answers, $parties, true, $review->consultant ?? []);
                $register = $this->questionnaire->register($review->answers, $parties, $review->consultant ?? []);
                $analysis = $review->analysis ?? [];
                foreach (['summary', 'owner', 'reviewed_on', 'next_review'] as $field) {
                    if (! filled($analysis[$field] ?? null)) {
                        throw ValidationException::withMessages(['analysis.'.$field => 'Uzupełnij i zapisz analizę konsultanta oraz daty przeglądu.']);
                    }
                }
                foreach ($register as $row) {
                    if (! filled($row['jak'] ?? null) || ($row['zgodnosc'] === 'tak' && (! filled($row['consultant']['legal_basis'] ?? null) || ! filled($row['consultant']['owner'] ?? null)))) {
                        throw ValidationException::withMessages(['consultant' => $row['kod'].': uzupełnij sposób uwzględnienia w systemie, a dla wymogu zgodności również aktualną podstawę prawną/umowną i odpowiedzialnego.']);
                    }
                }
                $review->compliance_register = array_values(array_filter($register, fn ($row) => $row['zgodnosc'] === 'tak'));
                $review->status = 'approved';
                $review->auditor_approval = $this->approval($request);
                $review->auditor_changes = $review->client_changes = null;
                $issuer = CompanySettings::first();
                $review->issuer = ['name' => $issuer?->name, 'logo' => $issuer?->logoDataUri()];
            } elseif ($op === 'return') {
                abort_unless(! $client && $review->status === 'submitted', 403);
                $review->review_note = $request->validate(['note' => 'required|string|max:3000'])['note'];
                $review->status = 'returned';
                $review->client_approval = null;
            } else {
                abort_unless($client && $request->user()->hasRole('client_admin') && $review->status === 'submitted', 403);
                $review->client_approval = null;
                $review->status = 'editing';
            }
            if (in_array($op, ['save', 'submit', 'consultant'])) {
                $field = $client ? 'client_changes' : 'auditor_changes';
                $track = ! $client || $review->auditor_changes || $review->client_changes || $before['consultant'];
                $changes = $review->$field ?? [];
                foreach (['answers', 'consultant', 'analysis'] as $key) {
                    if ($track && $before[$key] !== $review->$key) {
                        $changes[$key] = ['before' => $changes[$key]['before'] ?? $before[$key], 'after' => $review->$key, 'by' => $request->user()->name];
                    }
                }
                $review->$field = $changes ?: null;
            }
            $review->source_profile_id = $profile->id;
            $review->source_hash = $hash;
            $review->lock_version++;
            $review->save();
            DB::table('iso_stakeholder_events')->insert(['review_id' => $review->id, 'user_name' => $request->user()->name, 'action' => $op, 'snapshot' => json_encode($review->only(['answers', 'basis', 'consultant', 'analysis', 'compliance_register', 'source_profile_id', 'source_hash', 'lock_version', 'status', 'client_approval', 'auditor_approval', 'auditor_changes', 'client_changes', 'review_note']), JSON_THROW_ON_ERROR), 'created_at' => now()]);
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'update', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => 'Ankieta stron 4.2 — '.$op, 'route_name' => $request->route()->getName()]);
        });

        return redirect()->route($this->prefix($client).'show', [$audit, $profile])->with('success', 'Zapisano rejestr stron zainteresowanych 4.2.');
    }

    public function pdf(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit, $profile);
        $data = $request->validate(['lock_version' => 'required|integer', 'preview' => 'nullable|boolean', 'extract' => 'nullable|boolean']);
        $extract = (bool) ($data['extract'] ?? false);
        $title = $extract ? IsoStakeholderReview::COMPLIANCE_TITLE : IsoStakeholderReview::DOCUMENT_TITLE;
        $contents = DB::transaction(function () use ($request, $audit, $profile, $data, $extract, $title) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $profile = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->access($request, $audit, $profile);
            $factors = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->lockForUpdate()->first();
            $review = IsoStakeholderReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->lockForUpdate()->firstOrFail();
            abort_unless($review->lock_version === (int) $data['lock_version'] && $this->questionnaire->ready($profile, $factors) && $review->source_hash === $this->questionnaire->hash($profile, $factors), 409, 'Dane źródłowe zmieniły się. Ponownie przejrzyj i zatwierdź rejestr.');
            abort_unless($review->status === 'approved' && $review->client_approval && $review->auditor_approval, 403);
            $field = $extract ? 'compliance_document_id' : 'document_id';
            $document = $review->$field ? IsoSectionDocument::find($review->$field) : null;
            $parties = $this->questionnaire->parties($profile, $factors);
            $register = $this->questionnaire->register($review->answers, $parties, $review->consultant ?? []);
            $contents = $document?->contents() ?: Pdf::loadView('audits.stakeholders.pdf', compact('review', 'profile', 'parties', 'register', 'extract', 'title'))->setPaper('a4', 'landscape')->output();
            if (! ($data['preview'] ?? false) && ! $document) {
                $document = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => '4-2', 'scope' => 'client', 'title' => $title, 'description' => 'Zakład: '.($profile->answers['site.name']['value'] ?? '').'. Zatwierdzony przez klienta i audytora.', 'document_year' => now()->year, 'version_number' => '1.0', 'original_filename' => $title.'.pdf', 'stored_path' => 'iso50001/client/'.$audit->id.'/4-2/'.Str::uuid().'.pdf', 'mime_type' => 'application/pdf', 'size' => strlen($contents), 'content_base64' => base64_encode($contents), 'uploaded_by' => $request->user()->id]);
                $review->$field = $document->id;
                $review->save();
            }
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'download', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => $title, 'route_name' => $request->route()->getName()]);

            return $contents;
        });
        if ($data['preview'] ?? false) {
            return response($contents, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition('inline', $title.'.pdf', Str::ascii($title).'.pdf'), 'Cache-Control' => 'private, no-store']);
        }

        return redirect()->route($this->prefix($client).'show', [$audit, $profile])->with('success', 'PDF zapisano w Dokumentacji punktu 4.2.');
    }
}
