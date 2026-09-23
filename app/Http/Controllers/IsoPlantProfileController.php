<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\CompanySettings;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Services\AuditorAccessService;
use App\Services\IsoPlantQuestionnaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;

class IsoPlantProfileController extends Controller
{
    public function __construct(private readonly IsoPlantQuestionnaire $questionnaire) {}

    private function access(Request $request, Audit $audit, bool $write = false): bool
    {
        $client = $request->routeIs('client.*');
        if ($client) {
            $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();
        } else {
            abort_unless(app(AuditorAccessService::class)->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);
            if ($write) {
                abort_unless($request->user()->can('audits.manage'), 403);
            }
        }
        abort_unless($audit->surveys()->whereHas('auditType', fn ($q) => $q->where('slug', 'iso50001'))->exists(), 404);

        return $client;
    }

    private function checkProfile(Audit $audit, IsoPlantProfile $profile): void
    {
        abort_unless($profile->audit_id === $audit->id && DB::table('iso_plant_sites')->where('id', $profile->site_id)->where('company_id', $audit->company_id)->exists(), 404);
    }

    private function prefix(bool $client): string
    {
        return $client ? 'client.audits.plant-profile.' : 'audits.plant-profile.';
    }

    public function index(Request $request, Audit $audit)
    {
        $client = $this->access($request, $audit);

        return view('audits.plant-profile.index', [
            'audit' => $audit, 'client' => $client, 'prefix' => $this->prefix($client),
            'canWrite' => $client || $request->user()->can('audits.manage'),
            'sites' => DB::table('iso_plant_sites')->where('company_id', $audit->company_id)->orderBy('name')->get(),
            'profiles' => IsoPlantProfile::where('audit_id', $audit->id)->get()->sortByDesc('id'),
        ]);
    }

    public function create(Request $request, Audit $audit)
    {
        $client = $this->access($request, $audit, true);
        $data = $request->validate(['site_id' => 'nullable|integer', 'name' => 'required_without:site_id|nullable|string|max:200', 'copy_latest' => 'nullable|boolean']);
        $profile = DB::transaction(function () use ($audit, $data, $request) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            if (! empty($data['site_id'])) {
                $site = DB::table('iso_plant_sites')->where('company_id', $audit->company_id)->where('id', $data['site_id'])->first();
                abort_unless($site, 404);
                $existing = IsoPlantProfile::where('audit_id', $audit->id)->where('site_id', $site->id)->latestPerSite()->first();
                if ($existing) {
                    return $existing;
                }
            } else {
                $siteId = DB::table('iso_plant_sites')->insertGetId(['company_id' => $audit->company_id, 'name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);
                $site = DB::table('iso_plant_sites')->find($siteId);
            }
            $answers = [];
            foreach (['organization.name' => $audit->company->name, 'organization.tax_id' => $audit->company->nip, 'site.name' => $site->name] as $key => $value) {
                $answers[$key] = ['value' => $value, 'unknown' => false, 'detail' => null, 'source' => 'Karta klienta / nazwa zakładu', 'updated_by' => $request->user()->id, 'updated_at' => now()->toIso8601String()];
            }
            $previous = ($data['copy_latest'] ?? false) ? IsoPlantProfile::where('site_id', $site->id)->where('status', 'approved')->orderByDesc('id')->first() : null;
            $definition = $this->questionnaire->definition();
            if ($previous && ($previous->definition['version'] ?? null) !== $definition['version']) {
                $definition['legacy_groups'] = $previous->definition['groups'];
            }
            $profile = IsoPlantProfile::create(['audit_id' => $audit->id, 'site_id' => $site->id, 'revision' => 1, 'lock_version' => 0, 'status' => 'editing', 'as_of_date' => $previous?->as_of_date ?? today(), 'definition' => $definition, 'answers' => $previous?->answers ?? $answers]);
            $this->record($request, $profile, 'create');

            return $profile;
        });

        return redirect()->route($this->prefix($client).'show', [$audit, $profile]);
    }

    public function show(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit);
        $this->checkProfile($audit, $profile);

        return view('audits.plant-profile.show', [
            'audit' => $audit, 'profile' => $profile, 'client' => $client, 'prefix' => $this->prefix($client),
            'canWrite' => $client || $request->user()->can('audits.manage'),
            'canClientApprove' => $client && $request->user()->hasRole('client_admin'),
            'isLatest' => ! IsoPlantProfile::where('audit_id', $audit->id)->where('site_id', $profile->site_id)->where('revision', '>', $profile->revision)->exists(),
            'questionnaire' => $this->questionnaire,
            'events' => DB::table('iso_plant_events')->where('profile_id', $profile->id)->orderByDesc('id')->limit(50)->get(['action', 'user_name', 'created_at']),
        ]);
    }

    public function update(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit, true);
        $this->checkProfile($audit, $profile);
        $data = $request->validate(['lock_version' => 'required|integer|min:0', 'operation' => ['required', Rule::in(['save', 'submit', 'approve', 'return', 'revise', 'withdraw'])], 'as_of_date' => 'nullable|date_format:Y-m-d', 'answers' => 'nullable|array|max:100', 'note' => 'nullable|string|max:3000']);
        $profile = DB::transaction(function () use ($request, $audit, $profile, $client, $data) {
            Audit::whereKey($audit->id)->lockForUpdate()->firstOrFail();
            $current = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->lock_version === (int) $data['lock_version'], 409, 'Profil zmienił się w innym oknie. Odśwież stronę przed ponownym zapisem.');
            $op = $data['operation'];
            if ($op === 'revise') {
                abort_unless($current->status === 'approved', 409);
                $latest = IsoPlantProfile::where('audit_id', $audit->id)->where('site_id', $current->site_id)->latestPerSite()->firstOrFail();
                if ($latest->id !== $current->id) {
                    return $latest;
                }
                $next = IsoPlantProfile::create(['audit_id' => $audit->id, 'site_id' => $current->site_id, 'revision' => $current->revision + 1, 'lock_version' => 0, 'status' => 'editing', 'as_of_date' => $current->as_of_date, 'definition' => $current->definition, 'answers' => $current->answers]);
                $this->record($request, $next, 'revise');

                return $next;
            }
            abort_if(IsoPlantProfile::where('audit_id', $audit->id)->where('site_id', $current->site_id)->where('revision', '>', $current->revision)->exists(), 409, 'Istnieje nowsza wersja profilu. Otwórz ją przed wprowadzeniem zmian.');
            if (in_array($op, ['save', 'submit'])) {
                abort_unless(! $client || in_array($current->status, ['editing', 'returned', 'auditor_corrected']), 409, 'Aby edytować, wycofaj zatwierdzenie lub utwórz nową wersję.');
                if ($op === 'submit') {
                    abort_unless($client && $request->user()->hasRole('client_admin'), 403);
                }
                $request->validate(['answers' => 'required|array', 'as_of_date' => 'required|date_format:Y-m-d', 'complete_form' => 'required|accepted'], ['complete_form.required' => 'Nie dotarł cały formularz. Zmniejsz liczbę wierszy i spróbuj ponownie — dotychczasowy zapis pozostał bez zmian.']);
                $answers = $this->questionnaire->normalize($data['answers'], $current->definition, $current->answers, $request->user()->id, $op === 'submit');
                $changed = $current->as_of_date->format('Y-m-d') !== $data['as_of_date'];
                $changeField = $client ? 'client_changes' : 'auditor_changes';
                $changes = $current->$changeField ?? [];
                // Initial client input is not a correction. Track only the review exchange.
                $trackChanges = ! $client || $current->auditor_changes || $current->client_changes || in_array($current->status, ['auditor_corrected', 'returned']);
                if ($trackChanges && $changed) {
                    $changes['_as_of_date'] = ['before' => $changes['_as_of_date']['before'] ?? $current->as_of_date->format('Y-m-d'), 'after' => $data['as_of_date'], 'by' => $request->user()->name, 'at' => now()->toIso8601String()];
                }
                foreach ($answers as $key => $answer) {
                    $questionChanged = false;
                    foreach (['value', 'unknown', 'detail', 'source'] as $field) {
                        $before = $current->answers[$key][$field] ?? ($field === 'unknown' ? false : null);
                        $after = $answer[$field] ?? null;
                        $questionChanged = $questionChanged || ($before === null) !== ($after === null) || $before != $after;
                    }
                    $changed = $changed || $questionChanged;
                    if ($trackChanges && $questionChanged) {
                        $changes[$key] = ['before' => $changes[$key]['before'] ?? ($current->answers[$key] ?? []), 'after' => $answer, 'by' => $request->user()->name, 'at' => now()->toIso8601String()];
                    }
                }
                if (! $client && ! $changed) {
                    return $current;
                }
                if (! $client && in_array($current->status, ['submitted', 'approved'])) {
                    $this->record($request, $current, 'before_correction');
                    if ($current->document_id) {
                        IsoSectionDocument::whereKey($current->document_id)->update(['description' => 'Wersja historyczna — zastąpiona korektą audytora. Profil #'.$current->id]);
                    }
                }
                $current->$changeField = $changes ?: null;
                if ($op === 'submit') {
                    $current->auditor_changes = null;
                }
                $current->answers = $answers;
                $current->as_of_date = $data['as_of_date'];
                $current->status = $op === 'submit' ? 'submitted' : (! $client || $current->status === 'auditor_corrected' ? 'auditor_corrected' : 'editing');
                $current->client_approval = $op === 'submit' ? $this->approval($request) : null;
                $current->auditor_approval = null;
                $current->review_note = null;
                $current->document_id = null;
                $current->issuer = null;
                if (! $client) {
                    $op = 'auditor_correction';
                }
            } elseif ($op === 'approve') {
                abort_unless(! $client && $current->status === 'submitted' && $current->client_approval, 403);
                abort_if(($current->client_approval['user_id'] ?? null) === $request->user()->id, 403, 'Przegląd musi zatwierdzić inna osoba niż przedstawiciel klienta.');
                $request->validate(['note' => 'required|string|max:3000']);
                $current->status = 'approved';
                $current->client_changes = null;
                $current->auditor_changes = null;
                $current->auditor_approval = $this->approval($request);
                $current->review_note = $data['note'];
                $issuer = CompanySettings::first();
                $current->issuer = ['name' => $issuer?->name, 'logo' => $issuer?->logoDataUri()];
            } elseif ($op === 'return') {
                abort_unless(! $client && $current->status === 'submitted', 403);
                $request->validate(['note' => 'required|string|max:3000']);
                $current->status = 'returned';
                $current->review_note = $data['note'];
                $current->client_approval = null;
            } elseif ($op === 'withdraw') {
                abort_unless($client && $request->user()->hasRole('client_admin') && $current->status === 'submitted', 403);
                $current->status = 'editing';
                $current->client_approval = null;
            }
            $current->lock_version++;
            $current->save();
            $this->record($request, $current, $op);

            return $current;
        });

        return redirect()->route($this->prefix($client).'show', [$audit, $profile])->with('success', 'Zapisano profil zakładu.');
    }

    private function approval(Request $request): array
    {
        return ['user_id' => $request->user()->id, 'name' => $request->user()->name, 'at' => now()->toIso8601String()];
    }

    private function record(Request $request, IsoPlantProfile $profile, string $action): void
    {
        DB::table('iso_plant_events')->insert(['profile_id' => $profile->id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name, 'action' => $action, 'snapshot' => json_encode($profile->only(['answers', 'as_of_date', 'revision', 'lock_version', 'status', 'client_approval', 'auditor_approval', 'review_note', 'auditor_changes', 'client_changes']), JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'update', 'auditable_type' => Audit::class, 'auditable_id' => $profile->audit_id, 'subject_label' => 'Profil zakładu #'.$profile->id.' — '.$action, 'route_name' => $request->route()->getName()]);
    }

    public function pdf(Request $request, Audit $audit, IsoPlantProfile $profile)
    {
        $client = $this->access($request, $audit);
        $this->checkProfile($audit, $profile);
        $data = $request->validate(['lock_version' => 'required|integer|min:0', 'preview' => 'nullable|boolean']);
        $result = DB::transaction(function () use ($request, $audit, $profile, $data) {
            $current = IsoPlantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->lock_version === (int) $data['lock_version'], 409);
            abort_unless($current->status === 'approved' && $current->client_approval && $current->auditor_approval, 403, 'PDF wymaga zatwierdzenia przez klienta i audytora.');
            $document = $current->document_id ? IsoSectionDocument::find($current->document_id) : null;
            $contents = $document?->contents();
            $filename = IsoPlantProfile::DOCUMENT_TITLE.'.pdf';
            if (! $contents) {
                $contents = Pdf::loadView('audits.plant-profile.pdf', ['profile' => $current, 'questionnaire' => $this->questionnaire])->setPaper('a4')->output();
            }
            if (! ($data['preview'] ?? false) && ! $document) {
                $document = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => 'intro', 'scope' => 'client', 'title' => IsoPlantProfile::DOCUMENT_TITLE, 'description' => 'Zakład: '.($current->answers['site.name']['value'] ?? '').'. Zatwierdzony przez klienta i audytora. Profil #'.$current->id, 'document_year' => $current->as_of_date->year, 'version_number' => $current->revision.'.0', 'original_filename' => $filename, 'stored_path' => 'iso50001/client/'.$audit->id.'/intro/'.Str::uuid().'.pdf', 'mime_type' => 'application/pdf', 'size' => strlen($contents), 'content_base64' => base64_encode($contents), 'uploaded_by' => $request->user()->id]);
                $current->document_id = $document->id;
                $current->save();
            }
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'download', 'auditable_type' => Audit::class, 'auditable_id' => $audit->id, 'subject_label' => $filename, 'route_name' => $request->route()->getName()]);

            return [$contents, $filename];
        });
        if ($data['preview'] ?? false) {
            return response($result[0], 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition('inline', $result[1], Str::ascii($result[1])), 'Cache-Control' => 'private, no-store']);
        }

        return redirect()->route($this->prefix($client).'show', [$audit, $profile])->with('success', 'PDF zapisano w dokumentacji klienta we Wstępie do ISO.');
    }
}
