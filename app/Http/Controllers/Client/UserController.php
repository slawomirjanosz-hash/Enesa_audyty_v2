<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\NewClientUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $company = auth()->user()->companies->first();

        if (! $company) {
            return redirect()->route('client.dashboard')
                ->with('error', 'Twoje konto nie jest przypisane do żadnej firmy.');
        }

        $users         = $company->users()->get();
        $archivedUsers = $company->archivedUsers()->get();

        return view('client.users', compact('company', 'users', 'archivedUsers'));
    }

    public function store(Request $request)
    {
        $company = auth()->user()->companies->first();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'is_admin'   => ['required', 'boolean'],
        ]);

        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser) {
            // Already in this company
            if ($company->users()->where('users.id', $existingUser->id)->exists()) {
                return back()->withInput()
                    ->withErrors(['email' => 'Ten użytkownik jest już przypisany do Twojej firmy.']);
            }

            // Exists in system but not in company — attach them
            $roleName = $data['is_admin'] ? 'client_admin' : 'client_user';
            Role::findOrCreate($roleName);
            $existingUser->syncRoles([$roleName]);

            $company->users()->attach($existingUser->id, ['is_admin' => (bool) $data['is_admin']]);

            return redirect()->route('client.users')
                ->with('success', 'Istniejący użytkownik został dodany do Twojej firmy.');
        }

        // New user — create account and send email
        $temporaryPassword = Str::random(10);

        $user = User::create([
            'name'      => $data['first_name'] . ' ' . $data['last_name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make($temporaryPassword),
            'is_active' => true,
        ]);

        $roleName = $data['is_admin'] ? 'client_admin' : 'client_user';
        Role::findOrCreate($roleName);
        $user->assignRole($roleName);

        $company->users()->attach($user->id, ['is_admin' => (bool) $data['is_admin']]);

        Mail::to($user->email)->send(new NewClientUser($user, $company, $temporaryPassword));

        return redirect()->route('client.users')
            ->with('success', 'Użytkownik został dodany i otrzymał dane dostępowe mailem.');
    }

    public function destroy(User $user)
    {
        $company = auth()->user()->companies->first();

        if (! $company->users()->where('users.id', $user->id)->exists()) {
            return redirect()->route('client.users')
                ->with('error', 'Nie masz uprawnień do usunięcia tego użytkownika.');
        }

        if ($user->id === auth()->id()) {
            return redirect()->route('client.users')
                ->with('error', 'Nie możesz usunąć własnego konta.');
        }

        // Soft-delete in pivot — keeps the record for archive view
        DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        return redirect()->route('client.users')
            ->with('success', 'Użytkownik został zarchiwizowany.');
    }

    public function permanentDelete(User $user)
    {
        $company = auth()->user()->companies->first();

        if (! $company->archivedUsers()->where('users.id', $user->id)->exists()) {
            return redirect()->route('client.users')
                ->with('error', 'Nie znaleziono zarchiwizowanego użytkownika.');
        }

        DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->delete();

        return redirect()->route('client.users')
            ->with('success', 'Użytkownik został trwale usunięty z firmy.');
    }
}
