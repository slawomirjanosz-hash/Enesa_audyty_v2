<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function fetchGus(Request $request)
    {
        $nip = preg_replace('/[^0-9]/', '', $request->nip ?? '');

        if (strlen($nip) !== 10) {
            return response()->json(['error' => 'NIP musi miec dokladnie 10 cyfr.'], 422);
        }

        // Placeholder — integracja z GUS do implementacji
        return response()->json([
            'name'    => 'Firma testowa Sp. z o.o.',
            'address' => 'ul. Testowa 1',
            'city'    => 'Warszawa',
        ]);
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
