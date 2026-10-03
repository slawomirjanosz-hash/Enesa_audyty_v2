<?php

use App\Models\CompanySettings;
use App\Models\HrBusinessTrip;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;

test('standard travel order fits A4 and keeps employee signature away from authorisation and payment', function () {
    $trip = new HrBusinessTrip(['purpose' => 'Serwis i protokół końcowy', 'departure_at' => '2026-08-19 08:30', 'outbound_arrival_at' => '2026-08-19 11:00', 'return_departure_at' => '2026-08-20 13:00', 'return_at' => '2026-08-20 16:30', 'origin' => 'Gliwice', 'destination' => 'Nowa Dęba', 'vehicle_type' => 'private', 'vehicle_name' => 'Samochód osobowy', 'registration_number' => 'SG9991W', 'distance_km' => 576, 'km_rate' => 1.15, 'mileage_amount' => 662.4, 'diet_amount' => 90, 'toll_cost' => 72, 'total_amount' => 824.4, 'outbound_travel_hours' => 2.5, 'return_travel_hours' => 3.5]);
    $trip->id = 123;
    $trip->setRelation('user', User::factory()->make(['name' => 'Jan Testowy']));
    $image = UploadedFile::fake()->image('mark.png', 300, 80);
    $dataUri = 'data:image/png;base64,'.base64_encode(file_get_contents($image->getRealPath()));
    $data = ['trip' => $trip, 'company' => new CompanySettings(['name' => 'Firma testowa', 'address' => 'Ulica Testowa 1', 'city' => 'Cieszyn', 'nip' => '0000000000']), 'logo' => $dataUri, 'employeeSignature' => $dataUri];
    $html = view('hr.trip-pdf', $data)->render();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);
    $signature = $xpath->query('//img[@alt="Podpis pracownika"]');
    expect($signature->length)->toBe(1);
    expect($signature->item(0)->parentNode->parentNode->textContent)->toContain('potwierdzenie odbycia wyjazdu');
    expect($html)->not->toContain('Data wystawienia', 'Utworzono', '1,1500');
    $pdf = Pdf::loadView('hr.trip-pdf', $data)->setPaper('a4');
    $pdf->output();
    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
});

test('travel print escapes notes and does not invent missing journey times', function () {
    $trip = new HrBusinessTrip(['departure_at' => '2026-10-03 08:00', 'vehicle_type' => 'company', 'purpose' => '<script>test</script>', 'notes' => '<img src="external">']);
    $trip->setRelation('user', null);
    $html = view('hr.trip-pdf', ['trip' => $trip, 'company' => null, 'logo' => null])->render();
    expect($html)->toContain('&lt;script&gt;', '&lt;img', 'nie dotyczy (samochód służbowy)', 'nie podano')
        ->not->toContain('<script>test', '<img src="external">', '0,00 godz.', 'alt="Podpis pracownika"');
});
