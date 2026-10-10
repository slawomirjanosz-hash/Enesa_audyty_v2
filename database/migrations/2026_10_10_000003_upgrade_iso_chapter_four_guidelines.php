<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $requirements = json_decode(file_get_contents(resource_path('iso50001/requirements-v2.json')), true, 512, JSON_THROW_ON_ERROR)['wymagania'];
        foreach (['factor', 'stakeholder', 'system'] as $kind) {
            DB::table('iso_'.$kind.'_reviews')->orderBy('id')->chunkById(100, function ($reviews) use ($kind, $requirements) {
                foreach ($reviews as $row) {
                    DB::transaction(function () use ($kind, $row, $requirements) {
                        $table = 'iso_'.$kind.'_reviews';
                        $events = 'iso_'.$kind.'_events';
                        $row = DB::table($table)->where('id', $row->id)->lockForUpdate()->first();
                        if (DB::table($events)->where('review_id', $row->id)->where('action', 'guidelines_2026_10')->exists()) {
                            return;
                        }
                        DB::table($events)->insert(['review_id' => $row->id, 'user_name' => 'System', 'action' => 'guidelines_2026_10', 'snapshot' => json_encode((array) $row, JSON_THROW_ON_ERROR), 'created_at' => now()]);
                        $updates = ['status' => 'editing', 'client_approval' => null, 'auditor_approval' => null, 'document_id' => null,
                            'source_hash' => hash('sha256', 'guidelines_2026_10:reapproval-required'), 'lock_version' => $row->lock_version + 1,
                            'review_note' => trim(($row->review_note ?? '')."\nNowe wytyczne 4.1–4.4: sprawdź zachowane odpowiedzi, uzupełnij nowe pola i ponownie zatwierdź ankietę."), 'updated_at' => now()];
                        $documents = [$row->document_id];
                        if ($kind === 'factor') {
                            $answers = json_decode($row->answers ?? '{}', true, 512, JSON_THROW_ON_ERROR);
                            $oldFactors = array_column(json_decode(file_get_contents(resource_path('iso50001/factors-v1.4.json')), true, 512, JSON_THROW_ON_ERROR)['czynniki'], null, 'kod');
                            $newFactors = array_column(json_decode(file_get_contents(resource_path('iso50001/factors-v2.json')), true, 512, JSON_THROW_ON_ERROR)['czynniki'], null, 'kod');
                            foreach (['KTX-ZR-01', 'KTX-ZR-02'] as $code) {
                                if (($answers['factors'][$code]['tresc'] ?? null) === $oldFactors[$code]['tresc']) {
                                    $answers['factors'][$code]['tresc'] = $newFactors[$code]['tresc'];
                                }
                                unset($answers['factors'][$code]['decyzja']);
                            }
                            $updates['answers'] = json_encode($answers, JSON_THROW_ON_ERROR);
                        }
                        if ($kind === 'stakeholder') {
                            $answers = json_decode($row->answers ?? '{}', true, 512, JSON_THROW_ON_ERROR);
                            $legacy = $answers['parties'] ?? [];
                            $answers['legacy_parties'] = $legacy;
                            $answers['parties'] = [];
                            $counts = array_count_values(array_column($requirements, 'strona_kod'));
                            foreach ($requirements as $requirement) {
                                $source = $requirement['strona_kod'];
                                if (($counts[$source] ?? 0) === 1 && isset($legacy[$source])) {
                                    $answer = $legacy[$source];
                                    unset($answer['decyzja']);
                                    $answers['parties'][$requirement['id']] = $answer;
                                }
                            }
                            $updates['answers'] = json_encode($answers, JSON_THROW_ON_ERROR);
                            $updates['basis'] = null;
                            $updates['compliance_register'] = null;
                            $updates['compliance_document_id'] = null;
                            $documents[] = $row->compliance_document_id;
                        }
                        DB::table('iso_section_documents')->whereIn('id', array_filter($documents))->update(['description' => 'Dokument historyczny — wytyczne 4.1–4.4 zostały zaktualizowane; wymagane ponowne zatwierdzenie ankiety.']);
                        DB::table($table)->where('id', $row->id)->update($updates);
                    });
                }
            });
        }
    }

    public function down(): void
    {
        // Deliberately do not restore outdated approvals or overwrite answers edited after migration.
    }
};
