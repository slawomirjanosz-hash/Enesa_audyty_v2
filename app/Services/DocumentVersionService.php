<?php

namespace App\Services;

use App\Models\IsoSectionDocument;
use Illuminate\Support\Facades\DB;

class DocumentVersionService
{
    public function next(int $auditId, string $section, string $field, string $value): string
    {
        if (! in_array($field, ['mime_type', 'title'], true)) {
            throw new \InvalidArgumentException('Nieprawidłowy typ serii dokumentów.');
        }
        $key = hash('sha256', json_encode([$auditId, $section, $field, $value]));

        return DB::transaction(function () use ($key, $auditId, $section, $field, $value) {
            $initial = IsoSectionDocument::where('audit_id', $auditId)->where('section_id', $section)
                ->where($field, $value)->pluck('version_number')->map(fn ($version) => (int) $version)->max() ?? 0;
            DB::table('document_version_counters')->insertOrIgnore(['series_key' => $key, 'last_version' => $initial]);
            $counter = DB::table('document_version_counters')->where('series_key', $key);
            $current = (int) (clone $counter)->lockForUpdate()->value('last_version');
            $next = max($current, $initial) + 1;
            $counter->update(['last_version' => $next]);

            return $next.'.0';
        }, 5);
    }
}
