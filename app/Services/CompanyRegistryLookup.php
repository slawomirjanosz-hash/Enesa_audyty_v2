<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CompanyRegistryLookup
{
    public function lookup(Company $company, ?string $krs): array
    {
        $nip = Company::normalizeNip($company->nip);
        $result = ['checked_at' => now()->toIso8601String(), 'nip' => $nip,
            'vat' => ['state' => 'unavailable'], 'krs' => ['state' => 'unavailable']];
        if (preg_match('/^\d{10}$/', (string) $nip)) {
            $vat = $this->fetch('https://wl-api.mf.gov.pl/api/search/nip/'.$nip, ['date' => now()->format('Y-m-d')]);
            $subject = data_get($vat, 'result.subject');
            if (is_array($subject) && Company::normalizeNip($subject['nip'] ?? null) === $nip) {
                $result['vat'] = ['state' => 'checked', 'name' => $subject['name'] ?? '',
                    'status' => $subject['statusVat'] ?? 'Brak danych', 'krs' => $subject['krs'] ?? null,
                    'request_id' => data_get($vat, 'result.requestId'), 'source' => 'https://www.podatki.gov.pl/wykaz-podatnikow-vat-wyszukiwarka/'];
                $krs = $krs ?: ($subject['krs'] ?? null);
            }
        }
        if (preg_match('/^\d{10}$/', (string) $krs)) {
            $data = $this->fetch('https://api-krs.ms.gov.pl/api/krs/OdpisAktualny/'.$krs, ['rejestr' => 'P', 'format' => 'json']);
            $registeredNip = Company::normalizeNip(data_get($data, 'odpis.dane.dzial1.danePodmiotu.identyfikatory.nip'));
            if ($registeredNip && $registeredNip === $nip) {
                $result['krs'] = ['state' => 'checked', 'number' => $krs,
                    'registered_on' => $this->registrationDate(data_get($data, 'odpis.naglowekA.dataRejestracjiWKRS')),
                    'name' => data_get($data, 'odpis.dane.dzial1.danePodmiotu.nazwa'),
                    'section4' => data_get($data, 'odpis.dane.dzial4'),
                    'section6' => data_get($data, 'odpis.dane.dzial6'),
                    'source' => 'https://prs.ms.gov.pl/krs'];
            } elseif ($registeredNip) {
                $result['krs'] = ['state' => 'identity_mismatch'];
            }
        }

        return $result;
    }

    private function registrationDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3])) {
            return null;
        }

        return $parts[3].'-'.$parts[2].'-'.$parts[1];
    }

    private function fetch(string $url, array $query): ?array
    {
        $key = 'reliability:registry:'.hash('sha256', $url.json_encode($query));
        if (Cache::has($key)) {
            return Cache::get($key);
        }
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(8)->withoutRedirecting()->get($url, $query);
            $data = $response->successful() ? $response->json() : null;
            if (is_array($data)) {
                Cache::put($key, $data, now()->addMinutes(15));

                return $data;
            }
        } catch (ConnectionException $e) {
            // Registry outages are unknown results, never proof that a company is safe.
        }

        return null;
    }
}
