<?php

namespace App\Services;

class IsoPlantCalculations
{
    public const FACTORS = [
        'energia elektryczna' => ['kWh' => 0.0036, 'MWh' => 3.6, 'GJ' => 1],
        'gaz ziemny' => ['m³' => 0.0395, 'kWh' => 0.0036, 'MWh' => 3.6, 'GJ' => 1],
        'ciepło sieciowe' => ['GJ' => 1, 'MWh' => 3.6, 'kWh' => 0.0036],
        'olej opałowy' => ['l' => 0.0387, 't' => 40.6],
        'LPG' => ['l' => 0.0246, 't' => 46], 'węgiel' => ['t' => 23], 'biomasa' => ['t' => 15],
        'biogaz' => ['m³' => 0.023, 'GJ' => 1], 'olej napędowy' => ['l' => 0.0386, 't' => 43],
        'benzyna' => ['l' => 0.0329, 't' => 44.3], 'CNG' => ['m³' => 0.0395, 't' => 48],
        'inny' => ['GJ' => 1, 'MWh' => 3.6, 'kWh' => 0.0036],
    ];

    public const ENERGY_UNITS = ['kWh' => 0.0036, 'MWh' => 3.6, 'GJ' => 1];

    public static function factors(): array
    {
        return array_map(fn ($units) => array_replace($units, self::ENERGY_UNITS), self::FACTORS);
    }

    public static function number(mixed $value): ?float
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }
        $text = str_replace([' ', "\u{00a0}", "\u{202f}", ','], ['', '', '', '.'], trim((string) $value));
        if (! preg_match('/^[+]?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/D', $text)) {
            return null;
        }
        $number = (float) $text;

        return is_finite($number) && $number >= 0 && $number <= 1000000000000 ? $number : null;
    }

    public function energy(mixed $rows, bool $unknown = false): array
    {
        if ($unknown) {
            return ['value' => null, 'errors' => [], 'detail' => 'Zużycie oznaczono jako nieznane. Nie podajemy sumy częściowej.'];
        }
        $total = 0.0;
        $errors = [];
        $count = 0;
        $position = 0;
        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            $line = is_int($index) ? $index + 1 : ++$position;
            if (! is_array($row)) {
                $errors[$index] = 'Wiersz '.$line.' — nieprawidłowe dane nośnika.';

                continue;
            }
            if (! collect($row)->except('id')->contains(fn ($value) => filled($value))) {
                continue;
            }
            $count++;
            $carrier = is_string($row['c0'] ?? null) ? trim($row['c0']) : '';
            $unit = is_string($row['c2'] ?? null) ? trim($row['c2']) : '';
            $quantity = self::number($row['c1'] ?? null);
            $factor = self::factors()[$carrier][$unit] ?? null;
            $reason = $quantity === null ? 'brak poprawnej ilości (liczba nieujemna, maks. 1 000 000 000 000)' : ($factor === null ? 'nieobsługiwane przeliczenie; wybierz obsługiwaną jednostkę energii lub zweryfikuj nośnik i jednostkę' : null);
            if ($reason !== null) {
                $errors[$index] = 'Wiersz '.$line.' — '.($carrier ?: 'brak nośnika').' / '.($unit ?: 'brak jednostki').': '.$reason.'.';
            } else {
                $total += $quantity * $factor;
            }
        }
        $value = ! $unknown && $count > 0 && ! $errors ? (float) sprintf('%.15g', $total / 1000) : null;
        $detail = $value !== null ? 'Suma wszystkich pozycji. Przeliczenie jednostek energii jest niezależne od nośnika. Wartości opałowe masy i objętości są orientacyjne i wymagają weryfikacji.' : implode(' ', $errors ?: [$unknown ? 'Zużycie oznaczono jako nieznane.' : 'Uzupełnij tabelę nośników.']).' Nie podajemy sumy częściowej.';

        return compact('value', 'errors', 'detail');
    }

    public function apply(array $definition, array $answers): array
    {
        if (($definition['version'] ?? '') !== '2.0') {
            return $answers;
        }
        $energy = $this->energy($answers['FAKT_NOSNIKI']['value'] ?? [], (bool) ($answers['FAKT_NOSNIKI']['unknown'] ?? false));
        $answers['ZUZYCIE_TJ'] = ['value' => $energy['value'], 'unknown' => false, 'detail' => $energy['detail'],
            'source' => 'FAKT_NOSNIKI; przeliczniki biblioteki audytorów 1.4'];
        $heat = $answers['FAKT_KOTLOWNIA']['value'] ?? null;
        $fuels = $answers['FAKT_CIEPLO_PALIWO']['value'] ?? [];
        if ($answers['FAKT_CIEPLO_PALIWO']['unknown'] ?? false) {
            $fuels = null;
        }
        $fossil = null;
        if ($heat === 'brak') {
            $fossil = 'nie';
        } elseif ($heat === 'siec') {
            $fossil = 'do potwierdzenia';
        } elseif (in_array($heat, ['wlasne', 'oba'])) {
            if (is_array($fuels) && array_intersect($fuels, ['gaz ziemny', 'olej', 'węgiel'])) {
                $fossil = 'tak';
            } elseif ($heat === 'oba') {
                $fossil = 'do potwierdzenia';
            } elseif (is_array($fuels) && count($fuels) && ! ($answers['FAKT_CIEPLO_PALIWO']['unknown'] ?? false)) {
                $fossil = 'nie';
            }
        }
        $answers['FAKT_CIEPLO_PALIWA'] = ['value' => $fossil, 'unknown' => false,
            'detail' => $fossil === 'do potwierdzenia' ? 'Paliwo ciepła sieciowego należy potwierdzić u dostawcy.' : null,
            'source' => 'FAKT_KOTLOWNIA; FAKT_CIEPLO_PALIWO'];

        return $answers;
    }

    public function energyChanged(array $definition, array $answers): bool
    {
        $before = $answers['ZUZYCIE_TJ']['value'] ?? null;
        $after = $this->apply($definition, $answers)['ZUZYCIE_TJ']['value'] ?? null;

        return ($before === null) !== ($after === null) || ($before !== null && $after !== null && (float) $before !== (float) $after);
    }

    public function energyNeedsReview(array $definition, array $answers): bool
    {
        if (($definition['version'] ?? '') !== '2.0') {
            return false;
        }

        return $this->energyChanged($definition, $answers) || $this->energy($answers['FAKT_NOSNIKI']['value'] ?? [], (bool) ($answers['FAKT_NOSNIKI']['unknown'] ?? false))['errors'] !== [];
    }
}
