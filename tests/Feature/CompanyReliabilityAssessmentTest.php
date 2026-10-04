<?php

use App\Services\CompanyReliabilityAssessment;

test('KRS bankruptcy announcement and registered name trigger red even with healthy finances', function () {
    $lookup = ['checked_at' => now()->toIso8601String(), 'krs' => ['state' => 'checked', 'name' => 'Test', 'section6' => [
        'postepowanieUpadlosciowe' => [['informacjaOOgloszeniuUpadlosci' => ['data' => '20.03.2026', 'sygnatura' => 'GL1G/GU/1066/2025'], 'opisZakonczeniaProcesuUpadlosci' => []]],
    ]]];
    $service = app(CompanyReliabilityAssessment::class);
    $finances = [['year' => now()->year - 1, 'revenue' => 1000, 'profit' => 100, 'equity' => 500, 'liabilities' => 100]];
    $result = $service->assess($lookup, $finances);
    expect($result['status'])->toBe('red');
    expect(collect($result['checks'])->firstWhere('label', 'Upadłość — KRS')['message'])->toContain('20.03.2026', 'GL1G/GU/1066/2025');
    $lookup['checked_at'] = now()->subHour()->toIso8601String();
    expect($service->assess($lookup, $finances)['status'])->toBe('red');
    $lookup['krs']['section6']['postepowanieUpadlosciowe'][0]['opisZakonczeniaProcesuUpadlosci'] = ['data' => '01.04.2026'];
    expect($service->assess($lookup, $finances)['status'])->toBe('yellow');
    $lookup['krs']['name'] = 'BIURO INŻYNIERSKIE IEC SPÓŁKA Z OGRANICZONĄ ODPOWIEDZIALNOŚCIĄ W UPADŁOŚCI';
    expect($service->assess($lookup, $finances)['status'])->toBe('red');
    $lookup['krs']['state'] = 'identity_mismatch';
    expect($service->assess($lookup, $finances)['status'])->toBe('yellow');
});

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
