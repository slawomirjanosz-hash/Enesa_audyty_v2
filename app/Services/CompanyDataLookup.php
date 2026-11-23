<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CompanyDataLookup
{
    /** @return array<string, string> */
    public function lookup(string $nip): array
    {
        $gusConfigured = trim((string) config('services.gus.key')) !== '';
        if ($gusConfigured) {
            try {
                return app(GusRegistryClient::class)->lookup($nip);
            } catch (\Throwable) {
                // Never log provider payloads, API keys or sessions. The fallback is labelled explicitly.
            }
        }
        $response = Http::connectTimeout(3)->timeout(10)->get("https://wl-api.mf.gov.pl/api/search/nip/{$nip}", ['date' => now()->format('Y-m-d')]);
        if ($response->serverError() || $response->status() === 429) {
            throw new RuntimeException('Registry unavailable.');
        }
        $subject = $response->successful() ? $response->json('result.subject') : null;
        abort_unless(is_array($subject) && ! empty($subject['name']), 404, 'Nie znaleziono firmy o podanym NIP w rejestrze MF.');
        if (! empty($subject['nip']) && Company::normalizeNip($subject['nip']) !== $nip) {
            throw new RuntimeException('Registry returned a different NIP.');
        }
        $raw = trim((string) ($subject['residenceAddress'] ?? '')) ?: trim((string) ($subject['workingAddress'] ?? ''));
        $address = $raw;
        $city = $postcode = '';
        if (preg_match('/^(.*?)(?:,\s*|\s+|^)(\d{2}-\d{3})\s+(.+)$/u', $raw, $matches)) {
            $address = trim($matches[1], " ,\t\n\r");
            $postcode = $matches[2];
            $city = trim($matches[3]);
        } elseif (($comma = strrpos($raw, ',')) !== false) {
            $address = trim(substr($raw, 0, $comma));
            $city = trim(substr($raw, $comma + 1));
        }
        $address = preg_replace('/\s+-$/u', '', $address) ?? $address;

        return [
            'name' => (string) $subject['name'],
            'address' => $address,
            'city' => $city,
            'postcode' => $postcode,
            'source' => 'mf',
            'source_label' => 'Ministerstwo Finansów — wykaz VAT',
            'warning' => $gusConfigured ? 'GUS jest niedostępny lub nie zwrócił jednoznacznych danych. Użyto rejestru MF; sprawdź adres przed zapisem.' : 'Dane pochodzą z wykazu VAT MF. Sprawdź adres przed zapisem.',
        ];
    }
}
