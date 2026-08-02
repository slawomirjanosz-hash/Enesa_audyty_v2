<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RegistrationController extends Controller
{
    public function showForm()
    {
        if ((CompanySettings::query()->value('welcome_page_mode') ?? 'audit') !== 'audit') {
            return redirect()->route('home');
        }

        return view('welcome');
    }

    public function register(Request $request)
    {
        $cleanNip = preg_replace('/[^0-9]/', '', $request->nip ?? '');
        $request->merge(['nip' => $cleanNip]);

        $data = $request->validate([
            'nip'        => ['required', 'digits:10', Rule::unique('companies', 'nip')],
            'name'       => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'address'    => ['nullable', 'string', 'max:255'],
            'city'       => ['nullable', 'string', 'max:100'],
        ], [
            'nip.unique'      => 'Firma z tym NIP jest juz zarejestrowana.',
            'nip.digits'      => 'NIP musi zawierac dokladnie 10 cyfr.',
            'email.unique'    => 'Uzytkownik z tym emailem juz istnieje.',
            'password.min'    => 'Haslo musi miec co najmniej 8 znakow.',
            'password.confirmed' => 'Hasla nie sa zgodne.',
        ]);

        DB::transaction(function () use ($data) {
            $company = Company::create([
                'nip'     => $data['nip'],
                'name'    => $data['name'],
                'address' => $data['address'] ?? null,
                'city'    => $data['city'] ?? null,
                'status'  => 'pending',
            ]);

            $user = User::create([
                'name'      => $data['first_name'] . ' ' . $data['last_name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'phone'     => $data['phone'] ?? null,
                'is_active' => false,
            ]);

            $user->assignRole('client_admin');

            DB::table('company_user')->insert([
                'company_id' => $company->id,
                'user_id'    => $user->id,
                'is_admin'   => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Nowa rejestracja firmy', [
                'company' => $company->name,
                'nip'     => $company->nip,
                'email'   => $user->email,
                'user'    => $user->name,
            ]);
        });

        return redirect()->route('home')
            ->with('success', 'Dziekujemy za rejestracje! Twoje konto czeka na akceptacje. Powiadomimy Cie emailem.');
    }
}
