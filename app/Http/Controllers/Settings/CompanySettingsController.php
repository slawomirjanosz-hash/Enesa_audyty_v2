<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanySettings;
use Illuminate\Http\Request;

class CompanySettingsController extends Controller
{
    public function index()
    {
        $company = CompanySettings::first();

        return view('settings.company.index', compact('company'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'tagline'  => ['nullable', 'string', 'max:255'],
            'email'    => ['nullable', 'email', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'address'  => ['nullable', 'string', 'max:255'],
            'city'     => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'nip'      => ['nullable', 'string', 'size:10'],
            'website'  => ['nullable', 'url', 'max:255'],
        ]);

        CompanySettings::updateOrCreate(['id' => 1], $data);

        return redirect()->route('settings.company')
            ->with('success', 'Dane firmy zostały zapisane.');
    }
}
