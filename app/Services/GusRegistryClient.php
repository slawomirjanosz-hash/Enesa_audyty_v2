<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class GusRegistryClient
{
    private const ENDPOINT = 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc';

    /** @return array<string, string> */
    public function lookup(string $nip): array
    {
        $key = trim((string) config('services.gus.key'));
        if ($key === '') {
            throw new RuntimeException('GUS is not configured.');
        }

        $sid = $this->call('Zaloguj', '<pKluczUzytkownika>'.$this->escape($key).'</pKluczUzytkownika>');
        if (! preg_match('/^[a-zA-Z0-9]{20}$/D', $sid)) {
            throw new RuntimeException('GUS login failed.');
        }

        try {
            $result = $this->call('DaneSzukajPodmioty', '<pParametryWyszukiwania xmlns:d="http://CIS/BIR/PUBL/2014/07/DataContract"><d:Nip>'.$this->escape($nip).'</d:Nip></pParametryWyszukiwania>', $sid);
            $xml = $this->xml($result);
            $matches = [];
            foreach ($xml->dane as $row) {
                if (trim((string) $row->Nip) === $nip && trim((string) $row->Nazwa) !== '' && strlen(trim((string) $row->Regon)) === 9) {
                    $matches[] = $row;
                }
            }
            if (count($matches) !== 1) {
                throw new RuntimeException('GUS did not return one matching company.');
            }
            $row = $matches[0];
            $building = $this->part((string) $row->NrNieruchomosci);
            $flat = $this->part((string) $row->NrLokalu);
            $street = $this->part((string) $row->Ulica);
            $city = trim((string) $row->Miejscowosc);

            return [
                'name' => trim((string) $row->Nazwa),
                'address' => trim(($street !== '' ? $street : $city).' '.$building.($flat !== '' ? '/'.$flat : '')),
                'postcode' => trim((string) $row->KodPocztowy),
                'city' => $city,
                'source' => 'gus',
                'source_label' => 'GUS / REGON',
            ];
        } finally {
            try {
                $this->call('Wyloguj', '<pIdentyfikatorSesji>'.$this->escape($sid).'</pIdentyfikatorSesji>', $sid);
            } catch (\Throwable) {
                // A failed logout must not discard a successful lookup or expose the session.
            }
        }
    }

    private function call(string $operation, string $body, ?string $sid = null): string
    {
        $action = 'http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/'.$operation;
        $envelope = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://www.w3.org/2005/08/addressing"><s:Header><a:Action s:mustUnderstand="1">'.$action.'</a:Action><a:To s:mustUnderstand="1">'.self::ENDPOINT.'</a:To></s:Header><s:Body><'.$operation.' xmlns="http://CIS/BIR/PUBL/2014/07">'.$body.'</'.$operation.'></s:Body></s:Envelope>';
        $response = Http::connectTimeout(3)->timeout(10)
            ->withHeaders($sid ? ['sid' => $sid] : [])
            ->withBody($envelope, 'application/soap+xml; charset=utf-8; action="'.$action.'"')
            ->post(self::ENDPOINT);
        if (! $response->successful()) {
            throw new RuntimeException('GUS request failed.');
        }
        // BIR can wrap its SOAP envelope in a multipart/related (MTOM) response.
        if (! preg_match('/<([\w.-]+:)?Envelope\b[\s\S]*?<\/\1Envelope>/', $response->body(), $match)) {
            throw new RuntimeException('Invalid GUS response.');
        }
        $xml = $this->xml($match[0]);
        $nodes = $xml->xpath('//*[local-name()="'.$operation.'Result"]');
        if (! $nodes || count($nodes) !== 1) {
            throw new RuntimeException('Missing GUS result.');
        }

        return trim((string) $nodes[0]);
    }

    private function xml(string $value): SimpleXMLElement
    {
        if (stripos($value, '<!DOCTYPE') !== false || stripos($value, '<!ENTITY') !== false) {
            throw new RuntimeException('Unsafe GUS XML.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($value, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw new RuntimeException('Invalid GUS XML.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function part(string $value): string
    {
        return trim($value) === '-' ? '' : trim($value);
    }
}
