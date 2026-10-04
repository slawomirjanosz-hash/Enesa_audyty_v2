<?php

namespace App\Support;

class FinancialAmount
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_scalar($value) || is_bool($value)) {
            return $value;
        }
        $text = trim((string) $value);
        $text = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $text);
        $text = preg_replace('/zł$/ui', '', $text);
        $text = str_replace(',', '.', $text);

        return $text === '' ? null : $text;
    }

    public static function display(mixed $value): string
    {
        $number = self::normalize($value);
        if ($number === null) {
            return '';
        }
        if (! is_scalar($number) || ! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', (string) $number, $parts)) {
            return is_scalar($value) ? (string) $value : '';
        }
        $integer = ltrim($parts[2], '0') ?: '0';

        return $parts[1].preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $integer).','.str_pad($parts[3] ?? '', 2, '0').' zł';
    }
}
