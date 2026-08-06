<?php

namespace App\Services;

use App\Models\Offer;

class OfferContentService
{
    public function hidePrices(Offer $offer): void
    {
        $offer->setAttribute('kwota_netto', null);
        $offer->setAttribute('price_sections', null);
        $offer->setAttribute('show_unit_prices', false);
        $offer->setAttribute('delegations', null);
        $offer->setAttribute('content_payment', null);
    }

    public function cleanHtml(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $html = preg_replace_callback(
            '/<ol>(.*?)<\/ol>/s',
            function ($matches) {
                $inner = $matches[1];
                $isOrdered = preg_match('/data-list="ordered"/i', $inner) === 1;
                $inner = preg_replace('/<li[^>]*>/i', '<li>', $inner);
                $inner = preg_replace('/<span[^>]*class="ql-ui"[^>]*>.*?<\/span>/s', '', $inner);

                return ($isOrdered ? '<ol>' : '<ul>').$inner.($isOrdered ? '</ol>' : '</ul>');
            },
            $html
        );

        $html = preg_replace('/\s+class="[^"]*"/', '', $html);
        $html = preg_replace('/\s+contenteditable="[^"]*"/', '', $html);
        $html = preg_replace('/<p>\s*<br\s*\/?>\s*<\/p>/i', '', $html);
        $html = preg_replace('/<p>\s*<\/p>/i', '', $html);
        $html = preg_replace('/<span[^>]*ql-ui[^>]*>.*?<\/span>/s', '', $html);
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><table><thead><tbody><tr><th><td>');
        $html = preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li|h2|h3|table|thead|tbody|tr|th|td)\b[^>]*>/i', '<$1>', $html);

        return trim($html);
    }

    public function normalizeTextSections(mixed $sections): ?array
    {
        if (! is_array($sections)) {
            return null;
        }

        $normalized = [];
        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            $name = trim(strip_tags((string) ($section['name'] ?? '')));
            $content = $this->cleanHtml((string) ($section['content'] ?? ''));
            $placement = $section['placement'] ?? ($index < 2 ? 'before_price' : 'after_price');

            $normalized[] = [
                'name' => mb_substr($name !== '' ? $name : 'Sekcja oferty', 0, 120),
                'content' => $content,
                'placement' => in_array($placement, ['before_price', 'after_price'], true)
                    ? $placement
                    : ($index < 2 ? 'before_price' : 'after_price'),
            ];
        }

        return $normalized;
    }
}
