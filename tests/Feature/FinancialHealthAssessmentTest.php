<?php

use App\Services\FinancialHealthAssessment;

test('financial health marks loss capital weakness and negative equity with explicit severity', function () {
    $service = app(FinancialHealthAssessment::class);
    $row = ['year' => now()->year - 1, 'revenue' => 1000, 'profit' => 100, 'equity' => 200, 'liabilities' => 800];
    expect($service->assess([$row])['state'])->toBe('clear');
    $row['profit'] = -1;
    expect($service->assess([$row])['periods'][0]['fields']['profit'])->toBe('warning');
    $row['equity'] = -1;
    expect($service->assess([$row])['state'])->toBe('risk');
    $row['equity'] = 0;
    expect($service->assess([$row])['periods'][0]['fields']['equity'])->toBe('warning');
    $row['profit'] = 100;
    $row['equity'] = 80;
    expect($service->assess([$row])['periods'][0]['fields']['liabilities'])->toBe('warning');
    $row['equity'] = 100;
    $row['liabilities'] = 900;
    expect($service->assess([$row])['state'])->toBe('clear');
});

test('financial health compares consecutive years without division by zero and distinguishes missing data', function () {
    $service = app(FinancialHealthAssessment::class);
    $row = ['year' => now()->year - 1, 'revenue' => 800, 'profit' => 50, 'equity' => 160, 'liabilities' => 100];
    $previous = ['year' => now()->year - 2, 'revenue' => 1000, 'profit' => 100, 'equity' => 200, 'liabilities' => 100];
    $result = $service->assess([$previous, $row]);
    expect($result['state'])->toBe('warning');
    expect($result['periods'][1]['fields'])->toMatchArray(['revenue' => 'warning', 'profit' => 'warning', 'equity' => 'warning']);
    $previous['profit'] = 0;
    $previous['revenue'] = 0;
    $previous['equity'] = 0;
    expect($service->assess([$row, $previous])['state'])->toBe('clear');
    $row['profit'] = null;
    expect($service->assess([$row])['state'])->toBe('unknown');
    expect($service->assess([])['state'])->toBe('unknown');
    $row['profit'] = 50;
    $row['year'] = now()->year - 3;
    expect($service->assess([$row])['state'])->toBe('unknown');
    expect($service->assess([$previous, $previous])['state'])->toBe('unknown');
});
