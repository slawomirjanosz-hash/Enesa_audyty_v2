<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const MANAGED_ROLES = ['superadmin', 'admin', 'auditor_senior', 'auditor'];

    public function index()
    {
        $users = User::with('roles')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::MANAGED_ROLES))
            ->orderByDesc('created_at')
            ->paginate(15);

        $roles = Role::whereIn('name', self::MANAGED_ROLES)->get();

        $archivedStaff = User::onlyTrashed()
            ->with('roles')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'auditor', 'auditor_senior', 'superadmin']))
            ->orderByDesc('deleted_at')
            ->get();
        $archivedClients = User::onlyTrashed()
            ->with('roles')
            ->with('companies')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))
            ->orderByDesc('deleted_at')
            ->get();
        $orphanUsers = User::whereDoesntHave('companies')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))
            ->with('roles')
            ->orderByDesc('created_at')
            ->get();
        $companies = Company::active()->orderBy('name')->get();

        return view('settings.users.index', compact('users', 'roles', 'archivedStaff', 'archivedClients', 'orphanUsers', 'companies'));
    }

    public function create()
    {
        return redirect()->route('settings.users.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'role'     => ['required', Rule::in(['admin', 'auditor_senior', 'auditor'])],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'name.required'  => 'Imię i nazwisko jest wymagane.',
            'email.required' => 'Adres e-mail jest wymagany.',
            'email.email'    => 'Podaj prawidłowy adres e-mail.',
            'email.unique'   => 'Ten adres e-mail jest już zajęty.',
            'role.required'  => 'Wybierz rolę użytkownika.',
            'role.in'        => 'Wybrana rola jest nieprawidłowa.',
            'password.min'   => 'Hasło musi mieć co najmniej 8 znaków.',
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make($data['password'] ?? str()->random(16)),
            'is_active' => true,
        ]);

        Role::findOrCreate($data['role']);
        $user->assignRole($data['role']);

        return redirect()->route('settings.users.index')
            ->with('success', 'Użytkownik został utworzony.');
    }

    public function show(string $id)
    {
        return redirect()->route('settings.users.index');
    }

    public function edit(string $id)
    {
        return redirect()->route('settings.users.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => ['nullable', 'string', 'max:30'],
            'role'     => ['required', Rule::in(['admin', 'auditor_senior', 'auditor'])],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'name.required'  => 'Imię i nazwisko jest wymagane.',
            'email.required' => 'Adres e-mail jest wymagany.',
            'email.email'    => 'Podaj prawidłowy adres e-mail.',
            'email.unique'   => 'Ten adres e-mail jest już zajęty.',
            'role.required'  => 'Wybierz rolę użytkownika.',
            'role.in'        => 'Wybrana rola jest nieprawidłowa.',
            'password.min'   => 'Hasło musi mieć co najmniej 8 znaków.',
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? $user->phone;

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (!$user->hasRole('superadmin')) {
            Role::findOrCreate($data['role']);
            $user->syncRoles([$data['role']]);
        }

        return redirect()->route('settings.users.index')
            ->with('success', 'Dane użytkownika zaktualizowane.');
    }

    public function destroy(User $user)
    {
        if ($user->hasRole('superadmin')) {
            return redirect()->route('settings.users.index')
                ->with('error', 'Nie można usunąć superadmina.');
        }

        if ($user->companies()->exists()) {
            return redirect()->route('settings.users.index')
                ->with('error', 'Nie można trwale usunąć użytkownika przypisanego do firmy.');
        }

        $user->delete();

        return redirect()->route('settings.users.index')
            ->with('success', 'Użytkownik został zarchiwizowany.');
    }

    public function restore(User $user)
    {
        $user->restore();

        return redirect()->back()->with('success', 'Użytkownik został przywrócony.');
    }

    public function forceDestroy(User $user)
    {
        if ($user->hasRole('superadmin')) {
            return redirect()->route('settings.users.index')
                ->with('error', 'Nie można usunąć superadmina.');
        }

        if ($user->companies()->exists()) {
            return redirect()->route('settings.users.index')
                ->with('error', 'Nie można usunąć użytkownika przypisanego do firmy.');
        }

        $name = $user->name;
        $user->forceDelete();

        return redirect()->back()->with('success', "Użytkownik $name został trwale usunięty.");
    }

    public function assignToCompany(Request $request, User $user)
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'role'       => ['required', Rule::in(['client_admin', 'client_user'])],
        ]);

        $company = Company::findOrFail($data['company_id']);

        if ($user->trashed()) {
            $user->restore();
        }

        Role::findOrCreate($data['role']);
        $user->syncRoles([$data['role']]);

        $company->users()->syncWithoutDetaching([
            $user->id => [
                'is_admin' => $data['role'] === 'client_admin',
            ],
        ]);

        return redirect()->back()->with('success', 'Użytkownik został przypisany do firmy.');
    }

    public function assignCompany(Request $request, User $user)
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $company = Company::findOrFail($data['company_id']);

        if (!$user->hasAnyRole(['client_admin', 'client_user'])) {
            Role::findOrCreate('client_user');
            $user->assignRole('client_user');
        }

        $company->users()->syncWithoutDetaching([
            $user->id => [
                'is_admin' => false,
            ],
        ]);

        return redirect()->back()->with('success', 'Użytkownik został przypisany do firmy.');
    }
}
