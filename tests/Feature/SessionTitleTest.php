<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Js;

test('expired session titles use safely encoded installation branding in every layout', function (string $layout) {
    $source = file_get_contents(resource_path('views/layouts/'.$layout.'.blade.php'));
    preg_match('/document\.title = (.*Sesja wygasła.*);/', $source, $matches);

    expect($matches)->toHaveCount(2);

    foreach (['INTROL — ENERGOMONTAŻ', "Firma 'Test' \"A\" </script>\nNowa"] as $brandName) {
        $rendered = Blade::render($matches[1], ['brandName' => $brandName]);
        expect($rendered)->toBe((string) Js::from('⚠ Sesja wygasła — '.$brandName))
            ->not->toContain('</script>');
    }
})->with(['app', 'client', 'client-zone']);

test('staff layout restores its original title after signing in again', function () {
    $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($source)->toContain('const _sessionOriginalTitle = document.title;')
        ->toContain('document.title = _sessionOriginalTitle;');
});
