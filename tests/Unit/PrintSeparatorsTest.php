<?php

test('print templates do not add decorative horizontal separators', function () {
    $root = dirname(__DIR__, 2);
    foreach (glob($root.'/resources/views/offers/*pdf.blade.php') as $path) {
        $template = file_get_contents($path);
        expect($template)->not->toContain('header-line', 'footer-sep');
        preg_match('/\.sec-hdr-tbl\s*\{([^}]+)\}/', $template, $heading);
        expect($heading[1])->not->toContain('border-bottom');
        // Preserve useful table rules and the signature space.
        expect($template)->toContain('.price-tbl td', '.sign-line-div');
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views')) as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'pdf.blade.php')) {
            continue;
        }
        $template = file_get_contents($file->getPathname());
        expect($template)->not->toMatch('/\.(?:header|meta|foot|qa)\s*\{[^}]*border-(?:top|bottom):[^;}]*(?:solid)/');
    }
    expect(file_get_contents($root.'/app/Http/Controllers/OfferController.php'))
        ->not->toContain('borderBottomSize', 'borderBottomColor');
});
