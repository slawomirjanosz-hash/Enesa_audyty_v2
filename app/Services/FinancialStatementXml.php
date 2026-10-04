<?php

namespace App\Services;

use App\Models\Company;
use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;

class FinancialStatementXml
{
    public function parse(string $xml, string $nip, string $source): array
    {
        if (strlen($xml) > 20 * 1024 * 1024 || str_contains($xml, "\0") || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            $this->fail('XML zawiera niedozwoloną deklarację lub przekracza limit rozmiaru.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument;
            if (! $doc->loadXML($xml, LIBXML_NONET) || $doc->doctype) {
                $this->fail('Niepoprawny plik XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($doc);
        $roots = $xpath->query('//*[local-name()="JednostkaInna" or local-name()="JednostkaMala"]');
        if ($roots->length !== 1) {
            $this->fail('Automatyczny odczyt wymaga jednego sprawozdania XML JednostkaInna lub JednostkaMala. Inne formaty pozostają załącznikami.');
        }
        $root = $roots->item(0);
        $small = $root->localName === 'JednostkaMala';
        $read = function (string $path) use ($xpath, $root): ?string {
            $query = './'.implode('/', array_map(fn ($part) => '*[local-name()="'.$part.'"]', explode('/', $path)));
            $nodes = $xpath->query($query, $root);

            return $nodes->length === 1 ? trim($nodes->item(0)->textContent) : null;
        };
        $introduction = 'WprowadzenieDoSprawozdaniaFinansowego';
        if ($small) {
            $introductions = $xpath->query('./*[local-name()="WprowadzenieDoSprawozdaniaFinansowegoJednostkaMala" or local-name()="WprowadzenieDoSprawozdaniaFinansowegoJednostkaInna"]', $root);
            if ($introductions->length !== 1) {
                $this->fail('Brak jednoznacznego wprowadzenia do sprawozdania.');
            }
            $introduction = $introductions->item(0)->localName;
        }
        $nipField = $introduction === 'WprowadzenieDoSprawozdaniaFinansowegoJednostkaMala' ? 'P_1C' : 'P_1D';
        $fileNip = Company::normalizeNip($read($introduction.'/P_1/'.$nipField));
        if (! preg_match('/^\d{10}$/', $nip) || $fileNip !== $nip) {
            $this->fail('NIP w sprawozdaniu nie zgadza się z NIP firmy. Nie uzupełniono pól.');
        }
        $unit = $read('Naglowek/KodSprawozdania');
        $unitPrefix = $small ? 'SprFinJednostkaMala' : 'SprFinJednostkaInna';
        $scale = match ($unit) {
            $unitPrefix.'WZlotych' => 1,
            $unitPrefix.'WTysiacach' => 1000,
            default => null,
        };
        if ($scale === null) {
            $this->fail('Nie rozpoznano jednostki kwot. Nie można bezpiecznie przeliczyć danych.');
        }
        $start = $read('Naglowek/OkresOd');
        $end = $read('Naglowek/OkresDo');
        foreach ([$start, $end] as $date) {
            if (! $date || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                $this->fail('Nie rozpoznano okresu sprawozdawczego.');
            }
        }
        if ($start > $end || (int) substr($end, 0, 4) < 2000 || (int) substr($end, 0, 4) > now()->year) {
            $this->fail('Niepoprawny okres sprawozdawczy.');
        }
        $balanceNodes = $xpath->query($small ? './*[local-name()="BilansJednostkaMala" or local-name()="BilansJednostkaInna"]' : './*[local-name()="Bilans"]', $root);
        $incomeNodes = $xpath->query($small ? './*[local-name()="RZiSJednostkaMala" or local-name()="RZiSJednostkaInna"]' : './*[local-name()="RZiS"]', $root);
        if ($balanceNodes->length !== 1 || $incomeNodes->length !== 1) {
            $this->fail('Brak jednoznacznego bilansu lub rachunku zysków i strat.');
        }
        $balance = $balanceNodes->item(0)->localName;
        $income = $incomeNodes->item(0)->localName;
        $simplified = $income === 'RZiSJednostkaMala';
        $variants = $xpath->query('./*', $incomeNodes->item(0));
        $variant = $variants->length === 1 ? $variants->item(0)->localName : null;
        $profit = match ($variant) {
            'RZiSKalk' => $simplified ? 'L' : 'O',
            'RZiSPor' => $simplified ? 'J' : 'L',
            default => null,
        };
        if (! $profit) {
            $this->fail('Nieobsługiwany wariant rachunku zysków i strat.');
        }
        $paths = ['revenue' => $income.'/'.$variant.'/A', 'profit' => $income.'/'.$variant.'/'.$profit,
            'equity' => $balance.'/Pasywa/Pasywa_A', 'liabilities' => $balance.'/Pasywa/Pasywa_B'];
        $rows = [];
        foreach (['KwotaA', 'KwotaB'] as $index => $column) {
            // For a non-calendar period we cannot safely infer the comparative year.
            if ($index === 1 && ($start !== substr($end, 0, 4).'-01-01' || substr($end, 5) !== '12-31')) {
                continue;
            }
            $row = ['year' => (int) substr($end, 0, 4) - $index,
                'source' => $source.'; '.$start.' — '.$end.($index ? '; dane porównawcze — potwierdź rok poprzedni' : '; rok bieżący').'; '.$column.'; '.($scale === 1000 ? 'tys. PLN przeliczone na PLN' : 'PLN').'; przychody: RZiS '.$variant.' A; zobowiązania: bilans B (z rezerwami i rozliczeniami międzyokresowymi)'];
            foreach ($paths as $field => $path) {
                $value = $read($path.'/'.$column);
                if ($value === null || $value === '') {
                    $row[$field] = null;

                    continue;
                }
                if (! preg_match('/^-?\d{1,15}(?:\.\d{1,2})?$/', $value) || abs((float) $value * $scale) > 999999999999999) {
                    $this->fail('Niepoprawna lub zbyt duża kwota w '.$field.'.');
                }
                [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
                $cents = ((int) $whole * 100 + (int) str_pad($fraction, 2, '0')) * $scale;
                $row[$field] = (str_starts_with($value, '-') && $cents > 0 ? '-' : '').intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
            }
            if (count(array_filter(array_intersect_key($row, $paths), fn ($v) => $v !== null)) > 0) {
                $rows[] = $row;
            }
        }
        if (! $rows) {
            $this->fail('Nie znaleziono kwot do uzupełnienia.');
        }

        return $rows;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
