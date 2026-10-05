<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class FinancialPdfText
{
    public function pages(string $path): array
    {
        $process = new Process([(string) config('services.financial_pdf.pdftotext', 'pdftotext'), '-layout', '-enc', 'UTF-8', '-f', '1', '-l', '120', $path, '-']);
        $process->setTimeout(15);
        try {
            $process->mustRun();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'Nie udało się odczytać tekstu PDF. Plik pozostaje załącznikiem.']);
        }
        $text = $process->getOutput();
        if (strlen($text) > 4_000_000 || mb_strlen(trim($text)) < 200) {
            throw ValidationException::withMessages(['file' => 'PDF nie ma czytelnego tekstu lub jest zbyt duży. Skany wymagają OCR; AI nie zostało uruchomione.']);
        }

        return array_combine(range(1, count(explode("\f", $text))), explode("\f", $text));
    }

    public function select(array $pages): array
    {
        $scores = [];
        foreach ($pages as $number => $text) {
            $text = preg_replace('/[ \t]{2,}/u', '  ', $text);
            $pages[$number] = $text;
            $score = 0;
            foreach (['kapitał własny', 'kapitały własne', 'zobowiązania', 'przychody', 'zysk netto', 'strata netto', 'wynik netto', 'rachunek zysków', 'sprawozdanie z sytuacji', 'sprawozdanie z całkowitych'] as $word) {
                $score += mb_stripos($text, $word) !== false ? 1 : 0;
            }
            $heading = mb_strtolower(preg_replace('/\s+/u', ' ', mb_substr($text, 0, 1100)));
            if (preg_match('/sprawozdanie z sytuacji finansowej|sprawozdanie z zysków|sprawozdanie z zyków|rachunek zysków i strat|bilans/u', $heading)) {
                $score += 30;
            }
            if (preg_match('/kapitał własny\s+[\d (]|zysk\s*\(strata\)\s*netto|przychody ze sprzedaży/u', mb_strtolower($text))) {
                $score += 12;
            }
            if (preg_match('/spis treści|polityk[ai] rachunkowości/u', $heading)) {
                $score -= 30;
            }
            if ($score >= 2) {
                $scores[$number] = $score;
            }
        }
        arsort($scores);
        $selected = [];
        // Include identification, accounting basis and unit declarations.
        foreach (array_unique([1, 2, 3, ...array_keys(array_slice($scores, 0, 5, true))]) as $page) {
            if (isset($pages[$page])) {
                $selected[$page] = mb_substr($pages[$page], 0, 6500);
            }
        }
        ksort($selected);
        $remaining = 32000;
        foreach ($selected as $page => $text) {
            $selected[$page] = mb_substr($text, 0, $remaining);
            $remaining -= mb_strlen($selected[$page]);
        }

        return array_filter($selected);
    }
}
