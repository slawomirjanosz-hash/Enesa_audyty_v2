<?php

namespace App\Http\Controllers;

use App\Mail\ClientAccepted;
use App\Mail\ClientRegistered;
use App\Mail\NewClientUser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

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

            $name = $subject['name'] ?? '';
            $rawAddress = $subject['residenceAddress'] ?? $subject['workingAddress'] ?? '';
            $address = '';
            $city = '';

            if ($rawAddress) {
                $lastComma = strrpos($rawAddress, ',');
                if ($lastComma !== false) {
                    $address = trim(substr($rawAddress, 0, $lastComma));
                    $cityPart = trim(substr($rawAddress, $lastComma + 1));
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

    public function show(Company $company)
    {
        $company->load([
            'audits.auditType',
            'offers',
            'users.roles',
        ]);

        $stats = [
            'audits_count' => $company->audits->count(),
            'offers_count' => $company->offers->count(),
            'users_count' => $company->users->count(),
        ];

        return view('companies.show', compact('company', 'stats'));
    }

    public function accept(Company $company)
    {
        if ($company->status === 'pending') {
            // Validate that company has a client_admin user
            if ($company->users()->wherePivot('is_admin', true)->doesntExist()) {
                return redirect()->back()->with('error', 'Firma musi mieć przypisanego głównego użytkownika przed akceptacją.');
            }

            $company->update(['status' => 'active']);

            $company->loadMissing('users.roles');

            $clientAdmin = $company->users()->wherePivot('is_admin', true)->first();

            if ($clientAdmin) {
                Mail::to($clientAdmin->email)->send(new ClientAccepted($company));
            }
        }

        return redirect()->route('companies.show', $company)
            ->with('success', 'Klient został zaakceptowany');
    }

    public function storeUser(Request $request, Company $company)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'role'       => ['required', Rule::in(['client_admin', 'client_user'])],
            'password'   => ['required', 'string', 'min:8'],
        ]);

        // Check for existing user (including soft-deleted)
        $existing = User::withTrashed()->where('email', $data['email'])->first();

        if ($existing) {
            if ($existing->trashed()) {
                // Restore soft-deleted user and attach to company
                DB::transaction(function () use ($existing, $data, $company) {
                    $existing->restore();
                    $existing->syncRoles([$data['role']]);
                    if (!$company->users()->where('user_id', $existing->id)->exists()) {
                        $company->users()->attach($existing->id, [
                            'is_admin' => $data['role'] === 'client_admin',
                        ]);
                    }
                });

                return redirect()->route('companies.show', $company)
                    ->with('success', 'Konto użytkownika zostało przywrócone i przypisane do firmy.');
            }

            // Active user exists — offer force-assign
            return redirect()->route('companies.show', $company)
                ->withErrors(['email' => 'Użytkownik z tym emailem już istnieje. Jeśli jesteś administratorem możesz go przypisać do tej firmy.'])
                ->with('can_force_assign', $data['email']);
        }

        $plainPassword = $data['password'];
        $user = null;

        DB::transaction(function () use ($data, $company, &$user, $plainPassword) {
            $user = User::create([
                'name'      => trim($data['first_name'] . ' ' . $data['last_name']),
                'email'     => $data['email'],
                'phone'     => $data['phone'] ?? null,
                'password'  => Hash::make($plainPassword),
                'is_active' => true,
            ]);

            $user->assignRole($data['role']);

            $company->users()->attach($user->id, [
                'is_admin' => $data['role'] === 'client_admin',
            ]);
        });

        if ($user?->email) {
            Mail::to($user->email)->send(new NewClientUser($user, $company, $plainPassword));
        }

        return redirect()->route('companies.show', $company)
            ->with('success', 'Użytkownik został dodany do firmy.');
    }

    public function assignExisting(Request $request, Company $company)
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->firstOrFail();

        if (!$company->users()->where('user_id', $user->id)->exists()) {
            $company->users()->attach($user->id, ['is_admin' => false]);
        }

        return redirect()->route('companies.show', $company)
            ->with('success', 'Istniejący użytkownik został przypisany do firmy.');
    }

    public function destroyUser(Request $request, Company $company, User $user)
    {
        $company->users()->detach($user->id);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Użytkownik został odpięty od firmy. Konto użytkownika zostało zachowane.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'nip' => ['nullable', 'digits:10', Rule::unique('companies', 'nip')->whereNull('archived_at')],
        ]);

        $company = Company::create(array_merge($data, ['status' => 'pending']));

        Mail::to(env('ADMIN_EMAIL', 'proximalumine@gmail.com'))
            ->send(new ClientRegistered($company, $request->user() ?? auth()->user()));

        return redirect()->route('dashboard')
            ->with('success', 'Klient został dodany.');
    }

    public function destroy(Company $company)
    {
        // If company is already archived, hard-delete it
        if ($company->archived_at) {
            // First, hard-delete the pivot records
            DB::table('company_user')
                ->where('company_id', $company->id)
                ->delete();
            
            // Then hard-delete the company
            $company->delete();
            
            return redirect()->route('settings.users.index', ['tab' => 'firmy'])
                ->with('success', 'Zarchiwizowana firma została trwale usunięta.');
        }
        
        // Otherwise, archive the company
        $company->update([
            'archived_at' => now(),
            'status' => 'archived',
        ]);
        
        // Soft-delete all user-company relationships (mark pivot as deleted)
        DB::table('company_user')
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        return redirect()->route('dashboard')
            ->with('success', 'Firma została zarchiwizowana i przesunięta do archiwum.');
    }

    public function restore(Company $company)
    {
        $company->update([
            'archived_at' => null,
            'status' => 'active',
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Firma została przywrócona.');
    }
}
