<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GoogleMapsService
{
    public function calculateRoute(string $origin, string $destination): array
    {
        $key = config('services.google.maps_key');
        if (! $key) {
            $this->routeError('Brak konfiguracji Google Maps. Administrator musi ustawić klucz API dla tras.');
        }

        try {
            $response = Http::connectTimeout(4)->timeout(12)->withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration',
            ])->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
                'origin' => ['address' => $origin],
                'destination' => ['address' => $destination],
                'travelMode' => 'DRIVE',
                'routingPreference' => 'TRAFFIC_UNAWARE',
                'languageCode' => 'pl-PL',
                'units' => 'METRIC',
            ]);
        } catch (ConnectionException) {
            $this->routeError('Nie można połączyć się z Google Maps. Spróbuj ponownie za chwilę.');
        }

        if (! $response->successful()) {
            // Do not log addresses, request headers, API keys or raw provider errors.
            Log::warning('Google Routes request failed', ['http_status' => $response->status()]);
            $this->routeError(match ($response->status()) {
                401, 403 => 'Google odrzuca dostęp do wyliczania tras. Administrator musi sprawdzić klucz, uprawnienia Routes API i rozliczenia w Google Cloud.',
                429 => 'Osiągnięto limit zapytań Google Maps. Spróbuj ponownie później.',
                400 => 'Google nie może wyliczyć trasy. Sprawdź adres początkowy i docelowy; jeśli problem się powtarza, zgłoś go administratorowi.',
                default => 'Usługa Google Maps jest chwilowo niedostępna. Spróbuj ponownie później.',
            });
        }

        $distance = $response->json('routes.0.distanceMeters');
        $duration = $response->json('routes.0.duration');
        if (! is_numeric($distance) || (float) $distance <= 0
            || ! is_string($duration) || ! preg_match('/^([0-9]+(?:\.[0-9]+)?)s$/D', $duration, $matches)
            || (float) $matches[1] <= 0 || (float) $matches[1] > 3599964) {
            $this->routeError('Nie znaleziono poprawnej trasy samochodowej. Podaj dokładniejsze adresy.');
        }

        return [
            'distance_km' => round((float) $distance / 1000, 1),
            'hours' => round((float) $matches[1] / 3600, 2),
        ];
    }

    public function suggestions(string $query): array
    {
        $key = config('services.google.maps_key');
        if (! $key) {
            return [];
        }

        try {
            $response = Http::connectTimeout(3)->timeout(8)->withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'suggestions.placePrediction.text.text',
            ])->post('https://places.googleapis.com/v1/places:autocomplete', [
                'input' => $query, 'includedRegionCodes' => ['pl'], 'languageCode' => 'pl',
            ]);
            $suggestions = $response->successful()
                ? collect($response->json('suggestions', []))->pluck('placePrediction.text.text')->filter()->values()
                : collect();
            if ($suggestions->isEmpty()) {
                $legacy = Http::connectTimeout(3)->timeout(8)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                    'input' => $query, 'components' => 'country:pl', 'language' => 'pl', 'key' => $key,
                ]);
                if ($legacy->successful()) {
                    $suggestions = collect($legacy->json('predictions', []))->pluck('description')->filter()->values();
                }
            }

            return $suggestions->filter(fn ($value) => is_string($value))->take(8)->all();
        } catch (ConnectionException) {
            // Legacy URLs contain the API key: never report the connection exception.
            return [];
        }
    }

    private function routeError(string $message): never
    {
        throw ValidationException::withMessages(['route' => $message]);
    }
}
