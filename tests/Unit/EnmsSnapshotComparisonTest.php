<?php

use App\Services\Enms\SnapshotComparison;

test('enms snapshot comparison ignores object order but preserves semantic differences', function ($stored, $current, bool $equal) {
    expect(SnapshotComparison::same($stored, $current))->toBe($equal);
})->with([
    'nested objects in lists' => [['version' => 1, 'value' => [['name' => 'Zakład', 'active' => false]]], ['value' => [['active' => false, 'name' => 'Zakład']], 'version' => 1], true],
    'missing key' => [['value' => null], [], false],
    'null and zero' => [['value' => null], ['value' => 0], false],
    'integer and string' => [['version' => 1], ['version' => '1'], false],
    'integer and float' => [['value' => 1], ['value' => 1.0], false],
    'approval changed' => [['approved' => false], ['approved' => true], false],
    'version changed' => [['version' => 1], ['version' => 2], false],
    'list order changed' => [['value' => ['A', 'B']], ['value' => ['B', 'A']], false],
    'list became object' => [[0 => 'A', 1 => 'B'], [1 => 'A', 2 => 'B'], false],
    'nulls' => [null, null, true],
]);
