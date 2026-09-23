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

    public function apply(array $definition, array $answers): array
    {
        if (($definition['version'] ?? '') !== '2.0') {
            return $answers;
        }
        $rows = $answers['FAKT_NOSNIKI']['value'] ?? [];
        $gj = 0;
        $complete = is_array($rows) && count($rows) > 0 && ! ($answers['FAKT_NOSNIKI']['unknown'] ?? false);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $factor = self::FACTORS[$row['c0'] ?? ''][$row['c2'] ?? ''] ?? null;
            if ($factor === null || ! is_numeric($row['c1'] ?? null)) {
                $complete = false;

                continue;
            }
            $gj += (float) $row['c1'] * $factor;
        }
        $answers['ZUZYCIE_TJ'] = ['value' => $complete ? round($gj / 1000, 6) : null, 'unknown' => false,
            'detail' => $complete ? 'Suma wszystkich pozycji. Wartości opałowe orientacyjne z biblioteki audytorów; wymagają weryfikacji.' : 'Uzupełnij wszystkie nośniki, ilości i obsługiwane jednostki. Nie podajemy sumy częściowej.',
            'source' => 'FAKT_NOSNIKI; przeliczniki biblioteki audytorów 1.4'];
        $heat = $answers['FAKT_KOTLOWNIA']['value'] ?? null;
        $fuels = $answers['FAKT_CIEPLO_PALIWO']['value'] ?? [];
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
}
