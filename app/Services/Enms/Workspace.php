<?php

namespace App\Services\Enms;

use App\Models\Audit;
use App\Models\IsoPlantProfile;
use App\Models\User;
use App\Services\AuditorAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Every read and write starts with an authorized tenant, audit and site. */
final class Workspace
{
    private array $recordCache = [];

    private function __construct(
        public readonly Audit $audit,
        public readonly IsoPlantProfile $profile,
        public readonly User $actor,
        public readonly bool $client,
        public readonly bool $writable,
    ) {}

    public static function for(User $actor, Audit $audit, IsoPlantProfile $profile, bool $client): self
    {
        if ($client) {
            $actor->companies()->whereKey($audit->company_id)->firstOrFail();
        } else {
            abort_unless(app(AuditorAccessService::class)->canViewCompany($actor, $audit->company_id, 'can_view_audits'), 403);
        }
        abort_unless(Rollout::enabled($audit->id), 404);
        abort_unless($profile->audit_id === $audit->id && DB::table('iso_plant_sites')->where('id', $profile->site_id)->where('company_id', $audit->company_id)->exists(), 404);
        abort_unless($audit->surveys()->whereHas('auditType', fn ($q) => $q->where('slug', 'iso50001'))->exists(), 404);

        return new self($audit, $profile, $actor, $client, $client || $actor->can('audits.manage'));
    }

    public function query()
    {
        return DB::table('enms_workspaces')->where('company_id', $this->audit->company_id)->where('audit_id', $this->audit->id)->where('site_id', $this->profile->site_id);
    }

    public function revision(): int
    {
        return (int) ($this->query()->value('revision') ?? 0);
    }

    public function records(?string $kind = null, ?int $atRevision = null): array
    {
        $workspace = $this->query()->first();
        if (! $workspace) {
            return [];
        }
        $cacheKey = $atRevision === null ? 'current-'.$workspace->revision : 'history-'.$atRevision;
        $cacheable = DB::transactionLevel() === 0;
        if ($cacheable && isset($this->recordCache[$cacheKey])) {
            return $kind ? array_filter($this->recordCache[$cacheKey], fn ($r) => $r['kind'] === $kind) : $this->recordCache[$cacheKey];
        }
        $query = DB::table('enms_records as r')->join('enms_record_versions as v', 'v.record_id', '=', 'r.id')
            ->where('r.workspace_id', $workspace->id);
        if ($atRevision === null) {
            $query->whereColumn('v.version', 'r.current_version');
        } else {
            $query->where('v.workspace_revision', '<=', $atRevision);
        }

        // PDF snapshots contain large JSON. Sort in memory to avoid MySQL filesort's JSON buffer limit.
        $records = $query->select('r.id', 'r.kind', 'v.symbol', 'v.version', 'v.payload', 'v.library_version', 'v.scope_version', 'v.actor_id', 'v.created_at', 'v.workspace_revision')
            ->get()->sortByDesc('workspace_revision')->unique('id')->mapWithKeys(function ($row) {
                unset($row->workspace_revision);

                return [$row->id => array_merge((array) $row, ['payload' => json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR)])];
            })->all();
        // Keep a single immutable snapshot in memory, not every revision of a long import.
        $this->recordCache = $cacheable ? [$cacheKey => $records] : [];

        return $kind ? array_filter($records, fn ($r) => $r['kind'] === $kind) : $records;
    }

    public function record(string $id, ?string $kind = null): array
    {
        $row = $this->records($kind)[$id] ?? null;
        abort_unless($row, 404);

        return $row;
    }

    /** Caller supplies validated domain data; versions and audit identity never come from HTTP input. */
    public function append(int $expectedRevision, string $kind, ?string $id, string $symbol, array $payload, string $libraryVersion, string $scopeVersion, ?string $authorizationId = null): array
    {
        abort_unless($this->writable, 403);
        abort_unless(IsoPlantProfile::whereKey($this->profile->id)->latestPerSite()->exists(), 409, 'Otwórz aktualny profil zakładu.');

        return DB::transaction(function () use ($expectedRevision, $kind, $id, $symbol, $payload, $libraryVersion, $scopeVersion, $authorizationId) {
            // The parent lock also serializes creation of the first workspace.
            $currentAudit = Audit::whereKey($this->audit->id)->lockForUpdate()->firstOrFail();
            abort_unless($currentAudit->company_id === $this->audit->company_id, 409, 'Przypisanie audytu do firmy zmieniło się. Otwórz audyt ponownie.');
            $workspace = $this->query()->lockForUpdate()->first();
            abort_unless((int) ($workspace->revision ?? 0) === $expectedRevision, 409, 'Dane zmieniły się. Odśwież widok przed zapisem.');
            if (! $workspace) {
                $workspaceId = DB::table('enms_workspaces')->insertGetId(['company_id' => $this->audit->company_id, 'audit_id' => $this->audit->id, 'site_id' => $this->profile->site_id, 'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $workspaceId = $workspace->id;
            }
            if ($id) {
                $record = DB::table('enms_records')->where('workspace_id', $workspaceId)->where('kind', $kind)->where('id', $id)->first();
                abort_unless($record, 404);
                $version = $record->current_version + 1;
            } else {
                $id = (string) Str::uuid();
                $version = 1;
                // Display numbering is unique within the organization; UUID references never change.
                DB::table('companies')->where('id', $this->audit->company_id)->lockForUpdate()->firstOrFail();
                $baseSymbol = $symbol;
                $suffix = 2;
                while (DB::table('enms_records')->where('company_id', $this->audit->company_id)->where('kind', $kind)->where('symbol', $symbol)->exists()) {
                    $symbol = mb_substr($baseSymbol, 0, 85).'-'.$suffix++;
                }
                DB::table('enms_records')->insert(['id' => $id, 'company_id' => $this->audit->company_id, 'workspace_id' => $workspaceId, 'kind' => $kind, 'symbol' => $symbol, 'current_version' => 0]);
            }
            $revision = $expectedRevision + 1;
            DB::table('enms_record_versions')->insert(['record_id' => $id, 'version' => $version, 'symbol' => $symbol, 'workspace_revision' => $revision, 'library_version' => $libraryVersion, 'scope_version' => $scopeVersion, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'actor_id' => $this->actor->id, 'created_at' => now()]);
            DB::table('enms_journal')->insert(['workspace_id' => $workspaceId, 'record_id' => $id, 'record_version' => $version, 'workspace_revision' => $revision, 'actor_id' => $this->actor->id, 'action' => $version === 1 ? 'created' : 'revised', 'authorization_id' => $authorizationId, 'details' => json_encode(['kind' => $kind, 'library_version' => $libraryVersion, 'scope_version' => $scopeVersion, 'role' => $this->client ? 'client' : 'auditor', 'before_version' => $version - 1, 'after_version' => $version, 'reason' => $payload['reason'] ?? $payload['uzasadnienie'] ?? null], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            DB::table('enms_records')->where('workspace_id', $workspaceId)->where('id', $id)->update(['current_version' => $version, 'symbol' => $symbol]);
            $this->query()->update(['revision' => $revision, 'updated_at' => now()]);

            return $this->record($id, $kind);
        });
    }

    public function history(): array
    {
        $workspace = $this->query()->first();

        return $workspace ? DB::table('enms_journal')->where('workspace_id', $workspace->id)->orderByDesc('id')->get()->map(fn ($r) => (array) $r)->all() : [];
    }

    public function denial(string $operation, string $targetId, string $grantId, string $reason): void
    {
        $workspace = $this->query()->first();
        if (! $workspace) {
            return;
        }
        DB::table('enms_journal')->insert(['workspace_id' => $workspace->id, 'record_id' => $targetId, 'record_version' => null, 'workspace_revision' => $workspace->revision, 'actor_id' => $this->actor->id, 'action' => 'denied', 'authorization_id' => $grantId, 'details' => json_encode(['operation' => $operation, 'reason' => $reason], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
