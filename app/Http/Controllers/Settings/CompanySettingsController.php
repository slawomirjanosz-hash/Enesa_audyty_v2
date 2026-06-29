<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
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

        // Keep the owner Company record in sync
        $this->syncOwnerCompany();

        return redirect()->route('settings.company')
            ->with('success', 'Dane firmy zostały zapisane.');
    }

    public function syncOwner()
    {
        $this->syncOwnerCompany();

        return redirect()->route('settings.company')
            ->with('success', 'Firma właściciela została zsynchronizowana z bazą danych.');
    }

    private function syncOwnerCompany(): void
    {
        $settings = CompanySettings::first();
        if (!$settings) {
            return;
        }

        Company::updateOrCreate(
            ['is_owner' => true],
            [
                'name'    => $settings->name,
                'nip'     => $settings->nip,
                'email'   => $settings->email,
                'phone'   => $settings->phone,
                'address' => $settings->address,
                'city'    => $settings->city,
                'is_owner' => true,
            ]
        );
    }
}
