<?php

namespace App\Services\Enms;

/** Compare decoded JSON snapshots without depending on database object key order. */
final class SnapshotComparison
{
    public static function same(mixed $stored, mixed $current): bool
    {
        return self::ordered($stored) === self::ordered($current);
    }

    private static function ordered(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        // Object member order is insignificant; list order and scalar types are not.
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::ordered(...), $value);
    }
}
