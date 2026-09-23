<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\CompanySettings;
use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Services\AuditorAccessService;
use App\Services\IsoFactorQuestionnaire;
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

        return view('audits.factors.show', [
            'audit' => $audit, 'profile' => $profile, 'review' => $review, 'client' => $client,
            'prefix' => $this->prefix($client), 'questionnaire' => $this->questionnaire, 'facts' => $facts, 'answers' => $answers,
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
        $data = $request->validate(['lock_version' => 'required|integer|min:0', 'source_hash' => 'required|string|size:64', 'operation' => ['required', Rule::in(['save', 'submit', 'approve', 'return', 'withdraw'])], 'answers' => 'nullable|array', 'note' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($request, $audit, $profile, $client, $data) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $profile = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->access($request, $audit, $profile, true);
            abort_unless($profile->status === 'approved' && $profile->client_approval && $profile->auditor_approval, 409, 'Najpierw zatwierdź profil zakładu przez klienta i audytora.');
            $hash = $this->questionnaire->hash($profile);
            abort_unless(hash_equals($hash, $data['source_hash']), 409, 'Profil zakładu zmienił się. Odśwież ankietę, aby wczytać aktualne czynniki.');
            $review = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->lockForUpdate()->first() ?? new IsoFactorReview(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'status' => 'editing', 'lock_version' => 0, 'answers' => [], 'basis' => []]);
            abort_unless($review->lock_version === (int) $data['lock_version'], 409, 'Ankieta zmieniła się w innym oknie. Odśwież stronę.');
            $op = $data['operation'];
            $stale = $review->exists && $review->source_hash !== $hash;
            abort_if($stale && ! in_array($op, ['save', 'submit']), 409, 'Zapisz ponownie ankietę po aktualizacji profilu zakładu.');
            $facts = $this->questionnaire->facts($profile);
            if (in_array($op, ['save', 'submit'])) {
                abort_unless(! $client || $stale || in_array($review->status, ['editing', 'returned', 'auditor_corrected']), 409);
                abort_if($op === 'submit' && (! $client || ! $request->user()->hasRole('client_admin')), 403);
                $request->validate(['answers' => 'required|array', 'complete_form' => 'required|accepted']);
                $input = $data['answers'];
                $previous = $this->questionnaire->currentAnswers($review->answers ?? [], $review->basis ?? [], $facts);
                $input['factors'] = array_replace($previous['factors'] ?? [], is_array($input['factors'] ?? null) ? $input['factors'] : []);
                if (($input['FAKT_KLIMAT_ISTOTNY'] ?? null) !== ($previous['FAKT_KLIMAT_ISTOTNY'] ?? null)) {
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
                $changed = $stale || $answers !== ($review->answers ?? []);
                if (! $client && ! $changed) {
                    return;
                }
                $changeField = $client ? 'client_changes' : 'auditor_changes';
                $changes = $review->$changeField ?? [];
                $track = ! $client || $review->auditor_changes || $review->client_changes || in_array($review->status, ['returned', 'auditor_corrected']);
                $before = ($review->answers['factors'] ?? []) + ['FAKT_KLIMAT_ISTOTNY' => [$review->answers['FAKT_KLIMAT_ISTOTNY'] ?? null, $review->answers['climate_reason'] ?? null], 'custom' => $review->answers['custom'] ?? []];
                $after = $answers['factors'] + ['FAKT_KLIMAT_ISTOTNY' => [$answers['FAKT_KLIMAT_ISTOTNY'], $answers['climate_reason']], 'custom' => $answers['custom']];
                foreach ($after as $key => $value) {
                    if ($track && ($before[$key] ?? null) !== $value) {
                        $changes[$key] = ['before' => $changes[$key]['before'] ?? ($before[$key] ?? null), 'after' => $value, 'by' => $request->user()->name];
                    }
                }
                if ($review->document_id) {
                    IsoSectionDocument::whereKey($review->document_id)->update(['description' => 'Dokument historyczny — ankieta 4.1 została zmieniona.']);
                }
                $review->$changeField = $changes ?: null;
                if ($op === 'submit') {
                    $review->auditor_changes = null;
                }
                $review->answers = $answers;
                $review->basis = array_column($this->questionnaire->factors($facts, $answers), 'basis', 'kod');
                $review->status = $op === 'submit' ? 'submitted' : (! $client || $review->status === 'auditor_corrected' ? 'auditor_corrected' : 'editing');
                $review->client_approval = $op === 'submit' ? $this->approval($request) : null;
                $review->auditor_approval = null;
                $review->document_id = null;
                $review->issuer = null;
                $review->review_note = null;
            } elseif ($op === 'approve') {
                abort_unless(! $client && $review->status === 'submitted' && $review->client_approval, 403);
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
            DB::table('iso_factor_events')->insert(['review_id' => $review->id, 'user_name' => $request->user()->name, 'action' => $op, 'snapshot' => json_encode($review->only(['answers', 'basis', 'source_profile_id', 'source_hash', 'status', 'client_approval', 'auditor_approval', 'auditor_changes', 'client_changes', 'review_note']), JSON_THROW_ON_ERROR), 'created_at' => now()]);
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'update', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => 'Ankieta czynników 4.1 — '.$op, 'route_name' => $request->route()->getName()]);
        });

        return redirect()->route($this->prefix($client).'show', [$audit, $profile])->with('success', 'Zapisano ankietę czynników 4.1.');
    }

    private function approval(Request $request): array
    {
        return ['user_id' => $request->user()->id, 'name' => $request->user()->name, 'at' => now()->toIso8601String()];
    }

    public function pdf(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit, $profile);
        $data = $request->validate(['lock_version' => 'required|integer|min:0', 'preview' => 'nullable|boolean']);
        $contents = DB::transaction(function () use ($request, $audit, $profile, $data) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $profile = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->access($request, $audit, $profile);
            $review = IsoFactorReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->lockForUpdate()->firstOrFail();
            abort_unless($review->lock_version === (int) $data['lock_version'], 409);
            abort_unless($profile->status === 'approved' && $profile->client_approval && $profile->auditor_approval && $review->source_hash === $this->questionnaire->hash($profile), 409, 'Profil zakładu zmienił się — ponownie zatwierdź ankietę.');
            abort_unless($review->status === 'approved' && $review->client_approval && $review->auditor_approval, 403);
            $document = $review->document_id ? IsoSectionDocument::find($review->document_id) : null;
            $contents = $document?->contents() ?: Pdf::loadView('audits.factors.pdf', ['review' => $review, 'profile' => $profile, 'questionnaire' => $this->questionnaire, 'facts' => $this->questionnaire->facts($profile)])->setPaper('a4')->output();
            if (! ($data['preview'] ?? false) && ! $document) {
                $document = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => '4-1', 'scope' => 'client', 'title' => IsoFactorReview::DOCUMENT_TITLE, 'description' => 'Zakład: '.($profile->answers['site.name']['value'] ?? '').'. Zatwierdzony przez klienta i audytora.', 'document_year' => now()->year, 'version_number' => '1.0', 'original_filename' => IsoFactorReview::DOCUMENT_TITLE.'.pdf', 'stored_path' => 'iso50001/client/'.$audit->id.'/4-1/'.Str::uuid().'.pdf', 'mime_type' => 'application/pdf', 'size' => strlen($contents), 'content_base64' => base64_encode($contents), 'uploaded_by' => $request->user()->id]);
                $review->document_id = $document->id;
                $review->save();
            }
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'download', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => IsoFactorReview::DOCUMENT_TITLE, 'route_name' => $request->route()->getName()]);

            return $contents;
        });
        if ($data['preview'] ?? false) {
            return response($contents, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition('inline', IsoFactorReview::DOCUMENT_TITLE.'.pdf', Str::ascii(IsoFactorReview::DOCUMENT_TITLE).'.pdf'), 'Cache-Control' => 'private, no-store']);
        }

        return redirect()->route($this->prefix($client).'show', [$audit, $profile])->with('success', 'PDF zapisano w Dokumentacji punktu 4.1.');
    }
}
