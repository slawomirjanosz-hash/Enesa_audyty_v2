<?php

namespace App\Services;

use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IsoFactorDocument
{
    public function editions(IsoFactorReview $review)
    {
        if (! $review->exists) {
            return collect();
        }

        return IsoSectionDocument::where('audit_id', $review->audit_id)->where('section_id', '4-1')->orderByDesc('id')->get()
            ->filter(fn ($document) => ($document->generation_metadata['review_id'] ?? null) === $review->id || $document->id === $review->document_id);
    }

    public function retainLegacyAssociation(IsoFactorReview $review): void
    {
        if (! $review->document_id) {
            return;
        }
        $document = IsoSectionDocument::whereKey($review->document_id)->where('audit_id', $review->audit_id)->where('section_id', '4-1')->first();
        if ($document && ! $document->generation_metadata) {
            $document->update(['generation_metadata' => ['schema' => 'legacy-pdf', 'review_id' => $review->id, 'site_id' => $review->site_id]]);
        }
    }

    public function fingerprint(IsoFactorReview $review, IsoPlantProfile $profile): string
    {
        return hash('sha256', json_encode([$review->answers, $review->swot, $review->source_hash, app(IsoFactorQuestionnaire::class)->facts($profile)], JSON_THROW_ON_ERROR));
    }

    public function body(IsoFactorReview $review, IsoPlantProfile $profile, bool $private = true, ?IsoSectionDocument $edition = null): string
    {
        return view('audits.factors.document', ['review' => $review, 'profile' => $profile, 'private' => $private, 'edition' => $edition,
            'questionnaire' => app(IsoFactorQuestionnaire::class), 'facts' => app(IsoFactorQuestionnaire::class)->facts($profile),
            'registry' => app(IsoFactorConclusions::class)->registry($review), 'fingerprint' => $this->fingerprint($review, $profile)])->render();
    }

    /** Caller holds the audit/review locks; failed PDF generation rolls back publication. */
    public function publish(IsoFactorReview $review, IsoPlantProfile $profile, User $user): IsoSectionDocument
    {
        if ($review->document_id && ($existing = IsoSectionDocument::find($review->document_id))) {
            return $existing;
        }
        $questionnaire = app(IsoFactorQuestionnaire::class);
        $facts = $questionnaire->facts($profile);
        $questionnaire->normalize($review->answers, $facts, true);
        $rows = app(IsoFactorConclusions::class)->registry($review);
        if (! $rows) {
            throw ValidationException::withMessages(['swot.wnioski' => 'Uzupełnij co najmniej jeden pełny wniosek.']);
        }
        app(IsoFactorConclusions::class)->validate($rows, array_merge(array_column($questionnaire->schema()['czynniki'], 'kod'), array_column($review->answers['custom'] ?? [], 'id')), true);
        $decisions = [];
        foreach ($rows as $row) {
            foreach ($row['czynniki'] as $code) {
                if (isset($decisions[$code]) && $decisions[$code] !== trim($row['decyzja'])) {
                    throw ValidationException::withMessages(['swot.wnioski' => "$code: sprzeczne rozstrzygnięcia w rejestrze wniosków."]);
                }
                $decisions[$code] = trim($row['decyzja']);
            }
        }
        if (($facts['ZUZYCIE_TJ'] ?? null) === null) {
            throw ValidationException::withMessages(['document' => 'Niepełny bilans energii: uzupełnij profil przed wydaniem dokumentu zawierającego kwalifikację zużycia.']);
        }
        $metadata = ['schema' => '4.1/3', 'review_id' => $review->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id,
            'input_fingerprint' => $this->fingerprint($review, $profile), 'client_approval' => $review->client_approval,
            'auditor_approval' => $review->auditor_approval, 'issuer' => $review->issuer, 'published_at' => now()->toIso8601String(), 'published_by' => $user->name];
        $edition = IsoSectionDocument::create(['audit_id' => $review->audit_id, 'section_id' => '4-1', 'scope' => 'client', 'title' => IsoFactorReview::DOCUMENT_TITLE,
            'description' => 'Wydanie D-EnMS-KON-01. Zakład: '.($profile->answers['site.name']['value'] ?? ''), 'document_year' => now()->year,
            'version_number' => (string) ($this->editions($review)->count() + 1), 'original_filename' => 'D-EnMS-KON-01.pdf',
            'stored_path' => 'iso50001/client/'.$review->audit_id.'/4-1/'.Str::uuid().'.pdf', 'mime_type' => 'application/pdf', 'size' => 0, 'uploaded_by' => $user->id, 'generation_metadata' => $metadata]);
        DB::table('iso_factor_events')->insert(['review_id' => $review->id, 'user_name' => $user->name, 'action' => 'publish', 'snapshot' => json_encode(['document_id' => $edition->id] + $metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        $body = $this->body($review, $profile, true, $edition);
        $contents = Pdf::loadView('audits.factors.pdf', compact('review', 'profile', 'body'))->setPaper('a4')->output();
        $edition->update(['generation_metadata' => $metadata + ['html' => $body], 'size' => strlen($contents), 'content_base64' => base64_encode($contents)]);
        $review->document_id = $edition->id;
        $review->save();

        return $edition;
    }
}
