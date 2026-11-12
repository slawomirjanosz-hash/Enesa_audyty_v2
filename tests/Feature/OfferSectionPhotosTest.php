<?php

use App\Models\Company;
use App\Models\Offer;
use App\Models\User;
use Smalot\PdfParser\Parser;

test('photos stay with their sections through saves and both document exports', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Klient zdjęć', 'company_type' => 'client']);
    $makePhoto = function (int $width, int $height, array $rgb): string {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        ob_start();
        imagejpeg($image);

        return 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
    };
    $wide = $makePhoto(1400, 500, [27, 89, 115]);
    $tall = $makePhoto(400, 1200, [178, 110, 20]);
    $data = [
        'company_id' => $company->id, 'offer_number' => 'OF-PHOTOS', 'status' => 'w_toku',
        'liczba_wyjazdow' => 1, 'liczba_noc' => 0, 'liczba_osob' => 1, 'stawka_noc' => 0,
        'text_sections' => json_encode([['name' => 'Zdjęcie instalacji', 'content' => '', 'placement' => 'before_price', 'photos' => [$wide]]]),
        'price_sections' => json_encode([['name' => 'Wariant A', 'photos' => [$tall], 'rows' => []]]),
    ];
    $this->actingAs($admin)->post(route('offers.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $offer = Offer::where('offer_number', 'OF-PHOTOS')->firstOrFail();
    expect($offer->text_sections[0]['photos'])->toBe([$wide]);
    expect($offer->price_sections[0]['photos'])->toBe([$tall]);
    $this->get(route('offers.edit', $offer))->assertOk()->assertSee('offer-section-photos.js');

    $invalid = array_replace($data, ['text_sections' => json_encode([['photos' => ['https://example.test/photo.jpg']]])]);
    $this->put(route('offers.update', $offer), $invalid)->assertSessionHasErrors('text_sections');
    expect($offer->fresh()->text_sections[0]['photos'])->toBe([$wide]);

    $pdf = $this->get(route('offers.pdf', ['offer' => $offer, 'sections_only' => true]))->assertOk()->getContent();
    $parsed = (new Parser)->parseContent($pdf);
    expect($parsed->getText())->toContain('Zdjęcie instalacji', 'Wariant A');
    expect(count($parsed->getObjectsByType('XObject', 'Image')))->toBeGreaterThanOrEqual(2);

    $word = $this->get(route('offers.download-word', $offer))->assertOk()->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    expect($zip->open($word))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    expect($xml)->toContain('Zdjęcie instalacji');
    expect(substr_count($xml, '<v:imagedata'))->toBeGreaterThanOrEqual(2);
    expect($xml)->toContain('width:428pt; height:153pt', 'width:62pt; height:188pt');

    if ($dir = getenv('OFFER_PHOTO_PREVIEW_DIR')) {
        file_put_contents($dir.'/photos.pdf', $pdf);
        copy($word, $dir.'/photos.docx');
    }

    $data['text_sections'] = json_encode([['name' => 'Zdjęcie instalacji', 'photos' => []]]);
    $this->put(route('offers.update', $offer), $data)->assertSessionHasNoErrors()->assertRedirect();
    expect($offer->fresh()->text_sections[0]['photos'])->toBe([]);
    expect($offer->fresh()->price_sections[0]['photos'])->toBe([$tall]);
});
