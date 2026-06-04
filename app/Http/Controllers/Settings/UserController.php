<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
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

        return view('settings.users.index', compact('users', 'roles'));
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
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make($data['password'] ?? str()->random(16)),
            'is_active' => true,
        ]);

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
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? $user->phone;

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (!$user->hasRole('superadmin')) {
            $user->syncRoles([$data['role']]);
        }

        return redirect()->route('settings.users.index')
            ->with('success', 'Dane użytkownika zaktualizowane.');
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);

        if ($user->hasRole('superadmin')) {
            return redirect()->route('settings.users.index')
                ->with('error', 'Nie można usunąć superadmina.');
        }

        $user->delete();

        return redirect()->route('settings.users.index')
            ->with('success', 'Użytkownik został usunięty.');
    }
}
