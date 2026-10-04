<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\CompanySettings;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Models\IsoSystemReview;
use App\Services\AuditorAccessService;
use App\Services\IsoSystemQuestionnaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class IsoSystemReviewController extends Controller
{
    public function __construct(private readonly IsoSystemQuestionnaire $questionnaire) {}

    private function access(Request $request, Audit $audit, IsoPlantProfile $profile, string $section, bool $write = false): bool
    {
        abort_unless(isset(IsoSystemReview::TITLES[$section]), 404);
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
        return $client ? 'client.audits.system.' : 'audits.system.';
    }

    public function show(Request $request, Audit $audit, IsoPlantProfile $profile, string $section)
    {
        $client = $this->access($request, $audit, $profile, $section);
        $sources = $this->questionnaire->sources($profile, $section);
        $review = IsoSystemReview::firstOrNew(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => $section], ['status' => 'editing', 'lock_version' => 0]);
        $answers = old('answers', $review->answers ?? $this->questionnaire->seed($section, $sources));
        if (! is_array($answers)) {
            $answers = [];
        }
        $stale = $review->exists && $review->source_hash !== $sources['hash'];

        return view('audits.system.show', ['audit' => $audit, 'profile' => $profile, 'section' => $section, 'client' => $client, 'review' => $review, 'answers' => $answers, 'sources' => $sources, 'stale' => $stale,
            'title' => IsoSystemReview::TITLES[$section], 'prefix' => $this->prefix($client), 'service' => $this->questionnaire,
            'sections' => $this->questionnaire->sections($section, $answers, $sources), 'progress' => $this->questionnaire->progress($section, $answers, $sources),
            'canWrite' => $client || $request->user()->can('audits.manage'), 'canClientApprove' => $client && $request->user()->hasRole('client_admin'),
            'events' => $review->exists ? DB::table('iso_system_events')->where('review_id', $review->id)->orderByDesc('id')->limit(50)->get() : collect()]);
    }

    public function update(Request $request, Audit $audit, IsoPlantProfile $profile, string $section)
    {
        $client = $this->access($request, $audit, $profile, $section, true);
        $data = $request->validate(['operation' => ['required', Rule::in(['save', 'submit', 'approve', 'return', 'withdraw'])], 'lock_version' => 'required|integer|min:0', 'source_hash' => 'required|string|size:64', 'note' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($request, $audit, $profile, $section, $client, $data) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $profile = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->access($request, $audit, $profile, $section, true);
            $sources = $this->questionnaire->sources($profile, $section);
            abort_unless(hash_equals($sources['hash'], $data['source_hash']), 409, 'Źródła zmieniły się — odśwież ankietę.');
            $review = IsoSystemReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->where('section', $section)->lockForUpdate()->first() ?? new IsoSystemReview(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'section' => $section, 'status' => 'editing', 'lock_version' => 0]);
            abort_unless($review->lock_version === (int) $data['lock_version'], 409, 'Ankieta zmieniła się w innym oknie.');
            $stale = $review->exists && $review->source_hash !== $sources['hash'];
            $op = $data['operation'];
            $approval = ['user_id' => $request->user()->id, 'name' => $request->user()->name, 'at' => now()->toIso8601String()];
            if (in_array($op, ['save', 'submit'])) {
                abort_unless(! $client || $stale || in_array($review->status, ['editing', 'returned', 'auditor_corrected']), 409);
                abort_if($op === 'submit' && (! $client || ! $request->user()->hasRole('client_admin')), 403);
                $input = $request->validate(['answers' => 'required|array', 'complete_form' => 'required|accepted'])['answers'];
                try {
                    $answers = $this->questionnaire->normalize($section, $input, $sources, $op === 'submit');
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => ['answers.'.$key => $messages])->all());
                }
                // Consultant-only checklist entries cannot be changed by a client.
                $answers['CHECK'] = $review->answers['CHECK'] ?? [];
                if (! $client && $section === '4-4') {
                    foreach ($this->questionnaire->schema($section)['lista_kontrolna'] as $i => $item) {
                        if ($item['auto'] === null) {
                            $check = $request->validate(["answers.CHECK.$i.stan" => ['nullable', Rule::in(['tak', 'nie', 'nie_dotyczy'])], "answers.CHECK.$i.uwagi" => 'nullable|string|max:3000']);
                            $answers['CHECK'][$i] = data_get($check, "answers.CHECK.$i", []);
                        }
                    }
                }
                $before = $review->answers ?? [];
                $changed = $before !== $answers;
                if ($changed || $stale) {
                    $track = ! $client || $review->auditor_changes || $review->client_changes || $review->auditor_approval;
                    if ($track) {
                        $field = $client ? 'client_changes' : 'auditor_changes';
                        $changes = $review->$field ?? [];
                        foreach (array_unique([...array_keys(Arr::dot($before)), ...array_keys(Arr::dot($answers))]) as $key) {
                            if (data_get($before, $key) !== data_get($answers, $key)) {
                                $changes[$key] = ['before' => $changes[$key]['before'] ?? data_get($before, $key), 'after' => data_get($answers, $key), 'by' => $request->user()->name];
                            }
                        }
                        $review->$field = $changes ?: null;
                    }
                    if ($review->document_id) {
                        IsoSectionDocument::whereKey($review->document_id)->update(['description' => 'Dokument historyczny — ankieta lub dane źródłowe zostały zmienione.']);
                    }
                    $review->document_id = null;
                    $review->client_approval = $review->auditor_approval = null;
                    $review->status = $client ? 'editing' : 'auditor_corrected';
                }
                $review->answers = $answers;
                if ($op === 'submit') {
                    $review->status = 'submitted';
                    $review->client_approval = $approval;
                }
            } else {
                abort_if($stale, 409, 'Źródła zmieniły się. Zapisz ankietę i zatwierdź ponownie.');
                if ($op === 'approve') {
                    abort_unless(! $client && $review->status === 'submitted' && $review->client_approval && $review->client_approval['user_id'] !== $request->user()->id, 403);
                    $this->questionnaire->normalize($section, $review->answers, $sources, true);
                    if ($section === '4-4') {
                        foreach ($this->questionnaire->schema($section)['lista_kontrolna'] as $i => $item) {
                            if ($item['auto'] === null && ! filled(data_get($review->answers, "CHECK.$i.stan"))) {
                                throw ValidationException::withMessages(['checklist' => 'Uzupełnij ocenę konsultanta w liście kontrolnej.']);
                            }
                        }
                    }
                    $review->status = 'approved';
                    $review->auditor_approval = $approval;
                    $review->client_changes = $review->auditor_changes = null;
                    $brand = CompanySettings::first();
                    $review->issuer = ['name' => $brand?->name, 'logo' => $brand?->logoDataUri()];
                } elseif ($op === 'return') {
                    abort_unless(! $client && $review->status === 'submitted', 403);
                    $review->review_note = $request->validate(['note' => 'required|string|max:3000'])['note'];
                    $review->client_approval = null;
                    $review->status = 'returned';
                } else {
                    abort_unless($client && $request->user()->hasRole('client_admin') && $review->status === 'submitted', 403);
                    $review->client_approval = null;
                    $review->status = 'editing';
                }
            }
            $review->source_hash = $sources['hash'];
            $review->source_snapshot = $sources;
            $review->lock_version++;
            $review->save();
            DB::table('iso_system_events')->insert(['review_id' => $review->id, 'user_name' => $request->user()->name, 'action' => $op, 'snapshot' => json_encode($review->only(['answers', 'status', 'source_hash', 'lock_version', 'client_approval', 'auditor_approval', 'review_note']), JSON_THROW_ON_ERROR), 'created_at' => now()]);
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'update', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => 'Ankieta '.$section.' — '.$op, 'route_name' => $request->route()->getName()]);
        });

        return redirect()->route($this->prefix($client).'show', [$audit, $profile, $section])->with('success', 'Zapisano ankietę.');
    }

    public function pdf(Request $request, Audit $audit, IsoPlantProfile $profile, string $section)
    {
        $client = $this->access($request, $audit, $profile, $section, ! $request->boolean('preview'));
        $data = $request->validate(['lock_version' => 'required|integer', 'preview' => 'nullable|boolean']);
        $title = IsoSystemReview::TITLES[$section];
        $contents = DB::transaction(function () use ($request, $audit, $profile, $section, $data, $title) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $profile = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->access($request, $audit, $profile, $section);
            $sources = $this->questionnaire->sources($profile, $section);
            $review = IsoSystemReview::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->where('section', $section)->lockForUpdate()->firstOrFail();
            abort_unless($review->lock_version === (int) $data['lock_version'] && $review->source_hash === $sources['hash'] && $sources['ready'], 409, 'Źródła zmieniły się — wymagane ponowne zatwierdzenia.');
            abort_unless($review->status === 'approved' && $review->client_approval && $review->auditor_approval, 403);
            $document = $review->document_id ? IsoSectionDocument::find($review->document_id) : null;
            $contents = $document?->contents() ?: Pdf::loadView('audits.system.pdf', ['title' => $title, 'review' => $review, 'profile' => $profile, 'section' => $section, 'sources' => $sources, 'service' => $this->questionnaire, 'sections' => $this->questionnaire->sections($section, $review->answers, $sources)])->setPaper('a4')->output();
            if (! ($data['preview'] ?? false) && ! $document) {
                $document = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => $section, 'scope' => 'client', 'title' => $title, 'description' => 'Zakład: '.($profile->answers['site.name']['value'] ?? '').'. Zatwierdzony przez klienta i audytora.', 'document_year' => now()->year, 'version_number' => '1.0', 'original_filename' => $title.'.pdf', 'stored_path' => 'iso50001/client/'.$audit->id.'/'.$section.'/'.Str::uuid().'.pdf', 'mime_type' => 'application/pdf', 'size' => strlen($contents), 'content_base64' => base64_encode($contents), 'uploaded_by' => $request->user()->id]);
                $review->document_id = $document->id;
                $review->save();
            }
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'download', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => $title, 'route_name' => $request->route()->getName()]);

            return $contents;
        });
        if ($data['preview'] ?? false) {
            return response($contents, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition('inline', $title.'.pdf', Str::ascii($title).'.pdf'), 'Cache-Control' => 'private, no-store']);
        }

        return redirect()->route($this->prefix($client).'show', [$audit, $profile, $section])->with('success', 'PDF zapisano w Dokumentacji punktu.');
    }
}
