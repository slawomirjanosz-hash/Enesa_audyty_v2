<?php

test('public application entry points prohibit search indexing without blocking visitors', function () {
    foreach (['/', '/login', '/rejestracja', '/up'] as $path) {
        $this->get($path)->assertSuccessful()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
});

test('protected pages still require authentication and prohibit search indexing', function () {
    $this->get('/dashboard')->assertRedirect(route('login'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('robots allows crawlers to discover noindex instead of hiding the directive', function () {
    $robots = file_get_contents(public_path('robots.txt'));
    expect($robots)->toContain('User-agent: *')->not->toContain('Disallow: /');
});
