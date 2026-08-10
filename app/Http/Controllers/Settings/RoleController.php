<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /** These roles protect the application itself and are not editable here. */
    private const SYSTEM_ROLES = [
        'superadmin', 'admin', 'auditor_senior', 'auditor', 'client_admin', 'client_user',
    ];

    /** Only permissions enforced by the application are offered in this first, safe version. */
    private const AVAILABLE_PERMISSIONS = [
        'system.full_access' => [
            'label' => 'Pełny dostęp operacyjny',
            'description' => 'Dostęp do wszystkich bieżących modułów i danych operacyjnych: CRM, firmy, oferty, dokumenty oraz audyty. Nie daje dostępu do danych firmy właściciela ani do superadmina.',
        ],
    ];

    public function index()
    {
        $this->ensureRoleManager();

        $roles = Role::query()
            ->whereNotIn('name', self::SYSTEM_ROLES)
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return view('settings.roles.index', [
            'roles' => $roles,
            'availablePermissions' => self::AVAILABLE_PERMISSIONS,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureRoleManager();

        $request->merge([
            'name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name'))),
        ]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[\pL\pN][\pL\pN ._()&\/-]*$/u',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Role::query()->whereRaw('LOWER(name) = LOWER(?)', [$value])->exists()) {
                        $fail('Rola o tej nazwie już istnieje.');
                    }
                },
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ], [
            'name.regex' => 'Nazwa roli może zawierać litery, cyfry, spacje oraz typowe znaki, np. Kierownik Projektu.',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $this->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('settings.roles.index')->with('success', 'Rola została utworzona. Możesz ją teraz przypisać użytkownikowi.');
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureRoleManager();
        $this->ensureCustomRole($role);

        $request->merge([
            'name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name'))),
        ]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[\pL\pN][\pL\pN ._()&\/-]*$/u',
                function (string $attribute, mixed $value, \Closure $fail) use ($role): void {
                    if (Role::query()
                        ->whereKeyNot($role->id)
                        ->whereRaw('LOWER(name) = LOWER(?)', [$value])
                        ->exists()) {
                        $fail('Rola o tej nazwie już istnieje.');
                    }
                },
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ], [
            'name.regex' => 'Nazwa roli może zawierać litery, cyfry, spacje oraz typowe znaki, np. Kierownik Projektu.',
        ]);

        $role->update(['name' => $data['name']]);
        $this->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('settings.roles.index')->with('success', 'Nazwa i uprawnienia roli zostały zapisane.');
    }

    public function destroy(Role $role)
    {
        $this->ensureRoleManager();
        $this->ensureCustomRole($role);

        $assignedUsers = $role->users()->count();
        $role->delete();

        $message = 'Rola została usunięta.';
        if ($assignedUsers > 0) {
            $message .= " Użytkownicy bez roli: {$assignedUsers}. Są nadal widoczni na liście i można przypisać im inną rolę.";
        }

        return redirect()->route('settings.roles.index')->with('success', $message);
    }

    private function syncPermissions(Role $role, array $permissionNames): void
    {
        $names = collect($permissionNames)
            ->filter(fn (string $name) => array_key_exists($name, self::AVAILABLE_PERMISSIONS))
            ->values();

        $permissions = $names->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        $role->syncPermissions($permissions);
    }

    private function ensureRoleManager(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['superadmin', 'admin']), 403, 'Tylko administrator może zarządzać własnymi rolami.');
    }

    private function ensureCustomRole(Role $role): void
    {
        abort_if(in_array($role->name, self::SYSTEM_ROLES, true), 403, 'Ról systemowych nie można zmieniać w tym miejscu.');
    }
}
