<?php

test('deployment starts concurrent workers without reload and warms runtime caches', function () {
    $root = dirname(__DIR__, 2);
    $railpack = json_decode(file_get_contents($root.'/railpack.json'), true, 512, JSON_THROW_ON_ERROR);
    $nixpacks = file_get_contents($root.'/nixpacks.toml');
    preg_match('/^cmd = \'(.*)\'$/m', $nixpacks, $matches);
    foreach ([$railpack['deploy']['startCommand'], $matches[1]] as $command) {
        expect($command)->toContain('PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-2}')
            ->toContain('php artisan serve --no-reload')
            ->toContain('php artisan config:cache && php artisan route:cache && php artisan view:cache')
            ->toContain('PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}:/app/deploy/php"')
            ->toContain('--port=${PORT:-8000}');
    }
});
