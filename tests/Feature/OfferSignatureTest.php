<?php

use App\Models\Company;
use App\Models\Offer;
use App\Models\User;
use Smalot\PdfParser\Parser;

test('only the owner can opt in to their profile signature and offer exports respect the choice', function () {
    $signer = User::factory()->create(['name' => 'Jan Podpisujący']);
    $editor = User::factory()->create();
    $signer->assignRole('admin');
    $editor->assignRole('admin');
    $company = Company::create(['name' => 'Klient podpisu', 'company_type' => 'client']);
    $offer = Offer::create(['company_id' => $company->id, 'offer_number' => 'OF-SIGN', 'status' => 'w_toku']);
    $data = ['company_id' => $company->id, 'offer_number' => 'OF-SIGN', 'status' => 'w_toku', 'liczba_wyjazdow' => 1, 'liczba_noc' => 0, 'liczba_osob' => 1, 'stawka_noc' => 0];

    $this->actingAs($signer)->get(route('offers.edit', $offer))->assertOk()->assertDontSee('name="use_signature"', false);
    $this->put(route('offers.update', $offer), $data + ['use_signature' => '1'])->assertSessionHasErrors('use_signature');
    $image = imagecreatetruecolor(500, 140);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);
    imagestring($image, 5, 80, 52, 'J. Podpisujacy', imagecolorallocate($image, 30, 45, 130));
    ob_start();
    imagepng($image);
    $signer->update(['signature_data' => base64_encode(ob_get_clean()), 'signature_mime' => 'image/png']);

    $this->get(route('offers.edit', $offer))->assertOk()->assertSee('name="use_signature" value="1"', false);
    $this->put(route('offers.update', $offer), $data + ['use_signature' => '1'])->assertSessionHasNoErrors();
    expect($offer->fresh()->signature_user_id)->toBe($signer->id);
    $this->actingAs($editor)->put(route('offers.update', $offer), $data + ['use_signature' => '0'])->assertSessionHasNoErrors();
    expect($offer->fresh()->signature_user_id)->toBe($signer->id);

    $pdf = $this->get(route('offers.pdf', $offer))->assertOk()->getContent();
    $signed = (new Parser)->parseContent($pdf);
    expect($signed->getText())->toContain($signer->name);
    $word = $this->get(route('offers.download-word', $offer))->assertOk()->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    expect($zip->open($word))->toBeTrue();
    expect($zip->getFromName('word/document.xml'))->toContain($signer->name, '<v:imagedata');
    $zip->close();
    if ($dir = getenv('OFFER_SIGNATURE_PREVIEW_DIR')) {
        file_put_contents($dir.'/signature.pdf', $pdf);
        copy($word, $dir.'/signature.docx');
    }

    $this->post(route('offers.clone', $offer), ['mode' => 'offer'])->assertRedirect();
    expect(Offer::latest('id')->first()->signature_user_id)->toBeNull();
    $this->actingAs($signer)->put(route('offers.update', $offer), $data + ['use_signature' => '0'])->assertSessionHasNoErrors();
    expect($offer->fresh()->signature_user_id)->toBeNull();
    $unsigned = (new Parser)->parseContent($this->get(route('offers.pdf', $offer))->assertOk()->getContent());
    expect($unsigned->getText())->not->toContain($signer->name);
    expect(count($signed->getObjectsByType('XObject', 'Image')))->toBeGreaterThan(count($unsigned->getObjectsByType('XObject', 'Image')));
});
