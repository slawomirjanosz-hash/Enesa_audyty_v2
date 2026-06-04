<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
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
