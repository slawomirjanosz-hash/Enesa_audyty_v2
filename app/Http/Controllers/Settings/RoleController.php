<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\RolePermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $this->ensureRoleManager();

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions')
            ->get()
            ->sortBy(fn (Role $role) => sprintf(
                '%02d-%s',
                ($position = array_search($role->name, RolePermissionCatalog::SYSTEM_ROLES, true)) === false ? 99 : $position,
                $role->name
            ))
            ->values();

        return view('settings.roles.index', [
            'roles' => $roles,
            'permissionGroups' => RolePermissionCatalog::groups(),
            'systemRoles' => RolePermissionCatalog::SYSTEM_ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureRoleManager();
        $data = $this->validateRole($request);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $this->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('settings.roles.index')
            ->with('success', 'Rola została utworzona i jest dostępna przy dodawaniu oraz edycji użytkownika.');
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureRoleManager();
        $isSystemRole = in_array($role->name, RolePermissionCatalog::SYSTEM_ROLES, true);
        $data = $this->validateRole($request, $role, $isSystemRole);

        if (! $isSystemRole) {
            $role->update(['name' => $data['name']]);
        }

        if ($role->name === 'superadmin') {
            $this->syncPermissions($role, RolePermissionCatalog::names());
        } elseif (! in_array($role->name, ['client_admin', 'client_user'], true)) {
            $this->syncPermissions($role, $data['permissions'] ?? []);
        }

        return redirect()->route('settings.roles.index')
            ->with('success', 'Rola i jej uprawnienia zostały zapisane.');
    }

    public function destroy(Role $role)
    {
        $this->ensureRoleManager();
        abort_if(
            in_array($role->name, RolePermissionCatalog::SYSTEM_ROLES, true),
            403,
            'Roli systemowej nie można usunąć, ale możesz zmienić jej uprawnienia.'
        );

        $assignedUsers = $role->users()->count();
        $role->delete();

        $message = 'Rola została usunięta.';
        if ($assignedUsers > 0) {
            $message .= " Użytkownicy bez roli: {$assignedUsers}. Możesz przypisać im inną rolę na liście użytkowników.";
        }

        return redirect()->route('settings.roles.index')->with('success', $message);
    }

    private function validateRole(Request $request, ?Role $role = null, bool $systemRole = false): array
    {
        $request->merge([
            'name' => $systemRole
                ? $role?->name
                : preg_replace('/\s+/u', ' ', trim((string) $request->input('name'))),
        ]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                'regex:/^[\pL\pN][\pL\pN ._()&\/-]*$/u',
                function (string $attribute, mixed $value, \Closure $fail) use ($role): void {
                    if (Role::query()->whereKeyNot($role?->id)->whereRaw('LOWER(name) = LOWER(?)', [$value])->exists()) {
                        $fail('Rola o tej nazwie już istnieje.');
                    }
                },
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(RolePermissionCatalog::names())],
        ], [
            'name.regex' => 'Nazwa roli może zawierać litery, cyfry, spacje oraz typowe znaki, np. Kierownik Projektu.',
            'name.unique' => 'Rola o tej nazwie już istnieje.',
        ]);
    }

    private function syncPermissions(Role $role, array $permissionNames): void
    {
        $permissions = collect($permissionNames)
            ->intersect(RolePermissionCatalog::names())
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        $role->syncPermissions($permissions);
    }

    private function ensureRoleManager(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->hasRole('superadmin') || $user?->can('settings.roles.manage'),
            403,
            'Nie masz uprawnień do zarządzania rolami.'
        );
    }
}
