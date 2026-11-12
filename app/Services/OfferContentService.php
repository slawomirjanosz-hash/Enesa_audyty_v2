<?php

namespace App\Services;

use App\Models\Offer;
use DOMDocument;
use Illuminate\Validation\ValidationException;

class OfferContentService
{
    public function signatureFor(Offer $offer): ?array
    {
        $signer = $offer->signatureUser;
        if (! $signer?->signature_data) {
            return null;
        }

        $raw = base64_decode($signer->signature_data, true);
        $size = $raw === false ? false : @getimagesizefromstring($raw);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 16000000
            || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }
        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return null;
        }

        $scale = min(1, 180 / $size[0], 64 / $size[1]);
        $width = max(1, (int) round($size[0] * $scale));
        $height = max(1, (int) round($size[1] * $scale));
        $small = imagecreatetruecolor($width, $height);
        imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));
        imagecopyresampled($small, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        ob_start();
        imagepng($small);
        $bytes = ob_get_clean();

        return ['name' => $signer->name, 'bytes' => $bytes, 'source' => 'data:image/png;base64,'.base64_encode($bytes), 'width' => $width, 'height' => $height];
    }

    public function sectionPhotos(array $section): array
    {
        $photos = [];
        foreach (array_slice((array) ($section['photos'] ?? []), 0, 4) as $source) {
            $photo = $this->photo($source);
            if ($photo !== null) {
                $photos[] = $photo;
            }
        }

        return $photos;
    }

    public function validateSectionPhotos(?array $priceSections, ?array $textSections): void
    {
        $count = 0;
        foreach (array_merge($priceSections ?? [], $textSections ?? []) as $section) {
            $photos = $section['photos'] ?? [];
            if (! is_array($photos) || count($photos) > 4 || ($count += count($photos)) > 8) {
                throw ValidationException::withMessages(['text_sections' => 'Możesz dodać do 4 zdjęć w sekcji i 8 w ofercie.']);
            }
            foreach ($photos as $source) {
                $photo = $this->photo($source);
                if ($photo === null || @imagecreatefromstring($photo['bytes']) === false) {
                    throw ValidationException::withMessages(['text_sections' => 'Nie można odczytać zdjęcia w sekcji. Dodaj je ponownie.']);
                }
            }
        }
    }

    private function photo(mixed $source): ?array
    {
        $prefix = 'data:image/jpeg;base64,';
        if (! is_string($source) || strlen($source) > 140000 || ! str_starts_with($source, $prefix)) {
            return null;
        }

        $bytes = base64_decode(substr($source, strlen($prefix)), true);
        // PHPWord also accepts file/archive sources; never let image bytes select those modes.
        $size = $bytes === false || str_contains($bytes, 'zip://') || str_ends_with(strtolower($bytes), '.php')
            ? false : @getimagesizefromstring($bytes);
        if ($size === false || $size[2] !== IMAGETYPE_JPEG || min($size[0], $size[1]) < 1 || max($size[0], $size[1]) > 1500) {
            return null;
        }
        $scale = min(1, 570 / $size[0], 250 / $size[1]);

        return ['source' => $prefix.base64_encode($bytes), 'bytes' => $bytes, 'width' => max(1, (int) round($size[0] * $scale)), 'height' => max(1, (int) round($size[1] * $scale))];
    }

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
        $html = preg_replace_callback(
            '/<(p|br|strong|b|em|i|u|ul|ol|li|h2|h3|table|thead|tbody|tr|th|td)\b([^>]*)>/i',
            function (array $matches): string {
                $tag = strtolower($matches[1]);

                if (in_array($tag, ['th', 'td'], true)
                    && preg_match('/style\s*=\s*["\'][^"\']*width\s*:\s*([0-9]+(?:\.[0-9]+)?)px/i', $matches[2], $widthMatch)) {
                    $width = min(2000, max(40, (int) round((float) $widthMatch[1])));

                    return sprintf('<%s style="width: %dpx">', $tag, $width);
                }

                return '<'.$tag.'>';
            },
            $html
        );

        return trim($html);
    }

    public function wordHtml(?string $html): string
    {
        $html = $this->cleanHtml($html);
        if ($html === '') {
            return '';
        }

        // PHPWord parses XML, while editor content is HTML (including bare br tags).
        // Parse as HTML first so line breaks, entities and unclosed tags become XML.
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>',
                LIBXML_NONET
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $result = '';
        foreach ($document->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $result .= $document->saveXML($node);
        }

        return $result;
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
            if (array_key_exists('photos', $section)) {
                $normalized[array_key_last($normalized)]['photos'] = $section['photos'];
            }
            if (($section['collapsed'] ?? false) === true) {
                $normalized[array_key_last($normalized)]['collapsed'] = true;
            }
        }

        return $normalized;
    }
}
