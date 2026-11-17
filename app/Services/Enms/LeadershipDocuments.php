<?php

namespace App\Services\Enms;

use App\Models\Audit;
use App\Models\IsoSectionDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class LeadershipDocuments
{
    public const TYPES = ['raport' => ['R-EnMS-PRZ-01', 'Raport diagnostyczny'], 'zalacznik' => ['R-EnMS-PRZ-01-Z', 'Załącznik szczegółowy do raportu diagnostycznego'], 'karta' => ['D-EnMS-PRZ-01', 'Karta przywództwa i zaangażowania kierownictwa'], 'ankieta' => ['A-EnMS-PRZ-01', 'Wydruk ankiety']];

    public const GENERATOR = 'enms-native-1';

    public function __construct(private readonly LeadershipWorkflow $workflow) {}

    public function draft(Workspace $w, int $revision, string $hash, array $input, array $issuer): array
    {
        abort_if($w->client || ! $w->writable, 403);
        $data = Validator::make($input, ['type' => ['required', Rule::in(array_keys(self::TYPES))], 'version' => 'required|string|max:30', 'reason' => 'required|string|max:3000'])->validate();

        return DB::transaction(function () use ($w, $revision, $hash, $data, $issuer) {
            Audit::whereKey($w->audit->id)->lockForUpdate()->firstOrFail();
            $source = $this->workflow->sources($w);
            abort_unless($w->revision() === $revision && $source['hash'] === $hash, 409, 'Dane lub źródła zmieniły się.');
            $state = $this->workflow->status($w);
            if (in_array($data['type'], ['raport', 'zalacznik']) && ! $state['assessed']) {
                $this->workflow->invalid('Raport i załącznik wymagają aktualnego zakończenia oceny konsultanta.');
            }
            $snapshot = [
                'company' => $w->audit->company->name, 'site' => $w->profile->answers['site.name']['value'] ?? '',
                'records' => array_filter($w->records(), fn ($r) => ! in_array($r['kind'], ['document', 'document_draft', 'document_approval', 'approval', 'authorized_action'])),
                'sources' => $source, 'status' => $state, 'library' => $this->workflow->library->data(),
                'requirements' => $this->workflow->library->requirements(), 'positions' => $this->workflow->library->positions(), 'criteria' => $this->workflow->library->criteria(),
                'options' => $this->workflow->options($w), 'readiness' => $this->workflow->readiness($w), 'issuer' => $issuer,
                'author' => $w->actor->name, 'date' => now()->toIso8601String(), 'data_hash' => $this->workflow->assessmentHash($w),
                'limitations' => $this->limitations($w),
            ];
            $data += ['snapshot' => $snapshot, 'generator' => self::GENERATOR, 'data_hash' => $snapshot['data_hash']];

            return $w->append($revision, 'document_draft', null, 'PROJ-'.(count($w->records('document_draft')) + 1), $data, config('enms.library'), $source['scope_version']);
        });
    }

    /** Called only after all server-side authority steps, inside their transaction. */
    public function issue(Workspace $w, array $draft, string $grantId): array
    {
        abort_if($w->client, 403);
        $p = $draft['payload'];
        if ($p['data_hash'] !== $this->workflow->assessmentHash($w)) {
            $this->workflow->invalid('Dane zmieniły się po przygotowaniu projektu. Przygotuj aktualny projekt.');
        }
        if (collect($w->records('document'))->contains(fn ($r) => $r['payload']['type'] === $p['type'] && $r['payload']['version'] === $p['version'])) {
            $this->workflow->invalid('Wersja o tym numerze już została wydana.');
        }
        if (in_array($p['type'], ['raport', 'zalacznik']) && ! $this->workflow->status($w)['assessed']) {
            $this->workflow->invalid('Ocena konsultanta wymaga aktualizacji.');
        }
        $bytes = $this->render($p, true);
        $title = self::TYPES[$p['type']][0].' — '.self::TYPES[$p['type']][1];
        $document = IsoSectionDocument::create(['audit_id' => $w->audit->id, 'section_id' => '5-1', 'scope' => 'client', 'title' => $title, 'description' => 'Zakład: '.$p['snapshot']['site'].'. Wydanie kontrolowane '.$p['version'].'. Zatwierdzenie i wejście w życie rejestrowane osobno.', 'document_year' => now()->year, 'version_number' => $p['version'], 'original_filename' => self::TYPES[$p['type']][0].'-'.$p['version'].'.pdf', 'stored_path' => 'iso50001/client/'.$w->audit->id.'/5-1/'.Str::uuid().'.pdf', 'mime_type' => 'application/pdf', 'size' => strlen($bytes), 'content_base64' => base64_encode($bytes), 'uploaded_by' => $w->actor->id]);
        $p += ['file_hash' => hash('sha256', $bytes), 'file_size' => strlen($bytes), 'document_id' => $document->id, 'draft_id' => $draft['id'], 'issued_at' => now()->toIso8601String()];

        $record = $w->append($w->revision(), 'document', null, self::TYPES[$p['type']][0].'/'.$p['version'], $p, config('enms.library'), $draft['scope_version'], $grantId);
        DB::table('enms_document_files')->insert(['record_id' => $record['id'], 'content_base64' => base64_encode($bytes)]);

        return $record;
    }

    public function render(array $document, bool $issued = false): string
    {
        $snapshot = $document['snapshot'];
        $rows = fn (string $kind) => array_values(array_filter($snapshot['records'], fn ($r) => $r['kind'] === $kind));
        $one = fn (string $kind, string $field, string $id) => collect($rows($kind))->first(fn ($r) => ($r['payload'][$field] ?? null) === $id);
        $registers = $this->workflow->registers;

        return Pdf::loadView('audits.enms.pdf', compact('document', 'snapshot', 'rows', 'one', 'issued', 'registers'))->setPaper('a4')->output();
    }

    public function limitations(Workspace $w): array
    {
        $readiness = $this->workflow->readiness($w);
        $messages = array_merge($readiness['missing'], array_values($readiness['warnings']));
        foreach ($w->records('verification') as $verification) {
            $evidence = $w->record($verification['payload']['evidence_id'], 'evidence');
            foreach ($this->workflow->verificationMissing($verification['payload']) as $missing) {
                $messages[] = $evidence['symbol'].': '.$missing;
            }
        }
        foreach ($w->records('closure') as $closure) {
            $p = $closure['payload'];
            if ($p['stan'] !== 'zamkniete') {
                continue;
            }
            $label = $this->workflow->library->criteria()[$p['criterion_id']]['id'];
            if (! SnapshotComparison::same($p['basis'], $this->workflow->basis($w, $p['criterion_id']))) {
                $messages[] = "$label: podstawa zamknięcia zmieniła się; wymagane ponowne rozstrzygnięcie konsultanta.";
            } elseif ($p['partial_only'] ?? false) {
                $messages[] = "$label: zamknięte wyłącznie na podstawie pokryć częściowych.";
            }
        }
        foreach ($w->records('declaration') as $d) {
            $r = $this->workflow->library->requirement($d['payload']['requirement_id']);
            foreach ($d['payload']['citations'] ?? [] as $key => $citation) {
                $v = $this->workflow->one($w, 'citation_verification', 'citation_key', $r['id'].'/'.$key)['payload'] ?? [];
                $field = collect($r['pola'])->firstWhere('klucz', $key);
                if (($v['state'] ?? '') !== 'zweryfikowana' || ! SnapshotComparison::same($v['citation'] ?? null, $citation)) {
                    $messages[] = ($r['iso'] ?? $r['kod']).' — '.$field['etykieta'].': brak aktualnej weryfikacji merytorycznej cytatu. Zatwierdzenie dokumentu źródłowego nie zastępuje sprawdzenia cytatu.';
                }
            }
        }
        foreach ($w->records('deadline') as $deadline) {
            if ($deadline['payload']['origin'] !== 'zatwierdzony') {
                $messages[] = $deadline['symbol'].': termin '.$deadline['payload']['date'].' pozostaje propozycją; nie jest terminem obowiązującym.';
            }
        }

        return array_values(array_unique($messages));
    }

    public function bytes(array $record): string
    {
        $bytes = base64_decode(DB::table('enms_document_files')->where('record_id', $record['id'])->value('content_base64') ?? '', true);
        abort_unless($bytes !== false && hash_equals($record['payload']['file_hash'], hash('sha256', $bytes)), 409, 'Suma kontrolna wydanego pliku jest niezgodna. Nie odtworzono go automatycznie.');

        return $bytes;
    }
}
