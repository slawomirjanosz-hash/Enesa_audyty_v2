<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CompanyController extends Controller
{
    public function fetchGus(Request $request)
    {
        $nip = preg_replace('/[^0-9]/', '', $request->nip ?? '');

        if (strlen($nip) !== 10) {
            return response()->json(['error' => 'NIP musi mieć dokładnie 10 cyfr.'], 422);
        }

        try {
            $response = Http::timeout(10)
                ->get("https://wl-api.mf.gov.pl/api/search/nip/{$nip}", [
                    'date' => now()->format('Y-m-d'),
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Nie znaleziono firmy o podanym NIP w rejestrze MF.'], 404);
            }

            $subject = $response->json('result.subject');

            if (empty($subject)) {
                return response()->json(['error' => 'Brak danych dla podanego NIP.'], 404);
            }

            $name       = $subject['name'] ?? '';
            $rawAddress = $subject['residenceAddress'] ?? $subject['workingAddress'] ?? '';
            $address    = '';
            $city       = '';

            if ($rawAddress) {
                $lastComma = strrpos($rawAddress, ',');
                if ($lastComma !== false) {
                    $address  = trim(substr($rawAddress, 0, $lastComma));
                    $cityPart = trim(substr($rawAddress, $lastComma + 1));
                    // Strip Polish postcode pattern "00-000 " from city
                    $city = trim(preg_replace('/^\d{2}-\d{3}\s+/', '', $cityPart));
                } else {
                    $address = $rawAddress;
                }
            }

            return response()->json(compact('name', 'address', 'city'));

        } catch (\Exception $e) {
            return response()->json(['error' => 'Błąd połączenia z API. Spróbuj ponownie później.'], 503);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city'    => ['nullable', 'string', 'max:100'],
            'email'   => ['nullable', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'nip'     => ['nullable', 'digits:10', 'unique:companies,nip'],
        ]);

        Company::create(array_merge($data, ['status' => 'pending']));

        return redirect()->route('dashboard')
            ->with('success', 'Klient zosta\u0142 dodany.');
    }
}
