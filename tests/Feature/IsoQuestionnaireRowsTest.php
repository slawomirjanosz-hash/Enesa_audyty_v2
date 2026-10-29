<?php

use App\Models\IsoFactorReview;
use Illuminate\Support\Facades\Blade;

test('ISO consultant collections load one handler regardless of partial order', function () {
    foreach ([['iso-report-summary-fields', 'iso-conclusions-fields'], ['iso-conclusions-fields', 'iso-report-summary-fields']] as $partials) {
        $html = Blade::render('@include($first) @include($second)', [
            'first' => 'audits.partials.'.$partials[0],
            'second' => 'audits.partials.'.$partials[1],
            'reportRoot' => 'consultant', 'reportData' => [], 'reportChoices' => [],
        ]);
        expect(substr_count($html, '/js/iso-report-summary.js'))->toBe(1);
        if (getenv('ISO_ROWS_QA')) {
            file_put_contents(base_path('tmp/iso-summary-rows.html'), $html);
        }
    }
});

test('ISO SWOT text and conclusion collection have distinct ids without changing stored names', function () {
    [$audit, $client, $staff, $profile] = factorFixture();
    $this->actingAs($client)->post(route('client.audits.factors.update', [$audit, $profile]), factorInput($profile))->assertSessionHasNoErrors();
    $html = $this->actingAs($staff)->get(route('audits.factors.show', [$audit, $profile]))->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@id="swot-conclusions"]')->length)->toBe(1)
        ->and($xpath->query('//div[@id="swot-conclusions"]')->length)->toBe(1)
        ->and($xpath->query('//textarea[@id="swot-text-conclusions"][@name="swot[conclusions]"]')->length)->toBe(1);
    $this->actingAs($client)->get(route('client.audits.factors.show', [$audit, $profile]))->assertOk()->assertDontSee('data-swot-form', false);
    expect(IsoFactorReview::sole()->client_approval)->not->toBeNull();
    if (getenv('ISO_ROWS_QA')) {
        file_put_contents(base_path('tmp/iso-swot-rows.html'), $html);
    }
});
