<?php

use App\Services\CompanyReliabilityAssessment;

test('automatic checks distinguish empty departments missing departments and registry entries', function () {
    $service = app(CompanyReliabilityAssessment::class);
    $lookup = ['checked_at' => now()->toIso8601String(), 'vat' => ['state' => 'checked', 'status' => 'Czynny'], 'krs' => ['state' => 'checked', 'section4' => [], 'section6' => []]];
    $result = $service->assess($lookup, []);
    expect($result['checks'][1]['state'])->toBe('clear');
    expect($result['checks'][2]['state'])->toBe('clear');
    expect($result['status'])->toBe('yellow');
    unset($lookup['krs']['section4']);
    $lookup['krs']['section6'] = ['polaczeniePodzialPrzeksztalcenie' => [['data' => '2020-01-01']]];
    $result = $service->assess($lookup, []);
    expect($result['checks'][1]['state'])->toBe('unknown');
    expect($result['checks'][2]['state'])->toBe('warning');
    $lookup['checked_at'] = now()->subMinutes(31)->toIso8601String();
    expect($service->assess($lookup, [])['checks'][2]['state'])->toBe('unknown');
});

test('negative equity is a risk while missing data never becomes a clean overall result', function () {
    $service = app(CompanyReliabilityAssessment::class);
    $row = ['year' => now()->year - 1, 'revenue' => 1000, 'profit' => 20, 'equity' => -1, 'liabilities' => 100];
    expect($service->assess(null, [$row])['status'])->toBe('red');
    $row['equity'] = 100;
    expect($service->assess(null, [$row])['status'])->toBe('yellow');
    expect($service->assess(null, [])['status'])->toBe('yellow');
});
