<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanySettings;
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

        $roles = Role::query()->withCount('users')->with('permissions')->get()
            ->sortBy(fn (Role $role) => sprintf(
                '%02d-%s',
                ($position = array_search($role->name, RolePermissionCatalog::SYSTEM_ROLES, true)) === false ? 99 : $position,
                $role->name
            ))->values();

        $permissionGroups = $this->visiblePermissionGroups();

        return view('settings.roles.index', [
            'roles' => $roles,
            'permissionGroups' => $permissionGroups,
            'systemRoles' => RolePermissionCatalog::SYSTEM_ROLES,
            'protectedRoles' => RolePermissionCatalog::PROTECTED_ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureRoleManager();
        $data = $this->validateRole($request);
        $role = Role::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'guard_name' => 'web',
        ]);
        $this->syncPermissions($role, $this->visiblePermissionNames($data['permissions'] ?? []));

        return redirect()->route('settings.roles.index')->with('success', 'Rola została utworzona.');
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureRoleManager();
        $isSystemRole = in_array($role->name, RolePermissionCatalog::SYSTEM_ROLES, true);
        $data = $this->validateRole($request, $role, $isSystemRole);

        if (! $isSystemRole) {
            $role->update(['name' => $data['name']]);
        }
        $role->update(['display_name' => $data['display_name']]);

        if ($role->name === 'superadmin') {
            $this->syncPermissions($role, RolePermissionCatalog::names());
        } elseif (! in_array($role->name, ['client_admin', 'client_user'], true)) {
            $visibleNames = $this->visiblePermissionNames();
            $hiddenExistingNames = $role->permissions->pluck('name')->diff($visibleNames)->all();
            $selectedVisibleNames = collect($data['permissions'] ?? [])->intersect($visibleNames)->all();
            $this->syncPermissions($role, array_merge($hiddenExistingNames, $selectedVisibleNames));
        }

        return redirect()->route('settings.roles.index')->with('success', 'Nazwa i uprawnienia roli zostały zapisane.');
    }

    public function destroy(Role $role)
    {
        $this->ensureRoleManager();
        abort_if(
            in_array($role->name, RolePermissionCatalog::PROTECTED_ROLES, true),
            403,
            'Tej roli nie można usunąć, ponieważ jest niezbędna do działania systemu.'
        );
        abort_if(
            $role->users()->exists(),
            422,
            'Najpierw przypisz użytkownikom inną rolę. Usunąć można wyłącznie rolę bez użytkowników.'
        );

        $role->delete();

        return redirect()->route('settings.roles.index')->with('success', 'Rola została usunięta.');
    }

    private function validateRole(Request $request, ?Role $role = null, bool $systemRole = false): array
    {
        $request->merge([
            'name' => $systemRole
                ? $role?->name
                : preg_replace('/\s+/u', ' ', trim((string) $request->input('name'))),
            'display_name' => preg_replace('/\s+/u', ' ', trim((string) $request->input(
                'display_name',
                $role?->display_name ?? ($role ? RolePermissionCatalog::roleLabel($role->name) : $request->input('name'))
            ))),
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
            'display_name' => [
                'required', 'string', 'max:60',
                'regex:/^[\pL\pN][\pL\pN ._()&\/-]*$/u',
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(RolePermissionCatalog::names())],
        ], [
            'name.regex' => 'Nazwa techniczna zawiera niedozwolone znaki.',
            'display_name.regex' => 'Nazwa wyświetlana zawiera niedozwolone znaki.',
        ]);
    }

    private function syncPermissions(Role $role, array $permissionNames): void
    {
        $permissions = collect($permissionNames)->intersect(RolePermissionCatalog::names())
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

    private function visiblePermissionGroups(): array
    {
        $settings = CompanySettings::query()->first();

        return collect(RolePermissionCatalog::groups())
            ->filter(fn (array $group, string $key) => in_array($key, ['settings', 'advanced'], true)
                || ($settings?->moduleEnabled($key) ?? true))
            ->all();
    }

    private function visiblePermissionNames(array $submitted = []): array
    {
        $visible = collect($this->visiblePermissionGroups())
            ->flatMap(fn (array $group) => array_keys($group['permissions']))
            ->values();

        return $submitted === [] ? $visible->all() : $visible->intersect($submitted)->all();
    }
}
