<?php

use App\Models\IsoPlantProfile;
use App\Services\IsoDocumentNaming;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

test('site document names start with the exact site and retain the document type', function () {
    expect(IsoDocumentNaming::title('Zakład w Zabrzu', '4.1 Kontekst organizacji'))->toBe('Zakład w Zabrzu — 4.1 Kontekst organizacji');
    $type = '1 Wstęp ISO Charakterystyka zakładu i podstawy wdrożenia EnMS';
    $title = IsoDocumentNaming::title(str_repeat('Żółć ', 40), $type);
    expect(mb_strlen($title))->toBeLessThanOrEqual(240)->and($title)->toEndWith(' — '.$type);
    expect(IsoDocumentNaming::filename($title))->toEndWith('.pdf');
    $profile = new IsoPlantProfile;
    $profile->id = 17;
    expect(IsoDocumentNaming::title($profile, 'Profil'))->toBe('Profil zakładu #17 — Profil');
});

test('site document download names preserve Polish letters and cannot inject headers or paths', function () {
    $title = IsoDocumentNaming::title("Zakład / A\\B \"100%\"\r\nDruga linia", 'Profil zakładu');
    $filename = IsoDocumentNaming::filename($title);
    expect($filename)->toBe('Zakład _ A_B _100__ Druga linia — Profil zakładu.pdf');
    $header = HeaderUtils::makeDisposition('inline', $filename, Str::ascii($filename));
    expect($header)->toContain("filename*=utf-8''", 'Zak%C5%82ad')->not->toContain("\r", "\n");
});
