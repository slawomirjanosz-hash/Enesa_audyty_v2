<?php

namespace App\Services\Enms;

/** A pilot allowlist is additional to, never a replacement for, normal access scopes. */
final class Rollout
{
    public static function auditIds(array $ids): array
    {
        return array_values(array_intersect(array_map('intval', $ids), array_map('intval', config('enms.audit_ids', []))));
    }

    public static function enabled(int $auditId): bool
    {
        return self::auditIds([$auditId]) !== [];
    }
}
