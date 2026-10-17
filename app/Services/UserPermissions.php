<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\User;
use App\Support\RolePermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class UserPermissions
{
    public function initialize(User $user): void
    {
        if ($user->individual_permissions) {
            return;
        }
        DB::transaction(function () use ($user): void {
            $user->syncPermissions($user->getAllPermissions());
            $user->forceFill(['individual_permissions' => true])->save();
        });
    }

    public function groups(User $manager): array
    {
        $settings = CompanySettings::first();

        return collect(RolePermissionCatalog::groups())
            ->filter(fn (array $group, string $key) => in_array($key, ['settings', 'advanced'], true)
                || ($settings?->moduleEnabled($key) ?? ($key !== 'cylinders')))
            ->map(function (array $group) use ($manager) {
                $group['permissions'] = array_filter($group['permissions'],
                    fn (string $name) => $manager->hasRole('superadmin')
                        || $manager->can('settings.roles.manage') || $manager->checkPermissionTo($name), ARRAY_FILTER_USE_KEY);

                return $group;
            })->filter(fn (array $group) => $group['permissions'] !== [])->all();
    }

    public function editableNames(User $manager): array
    {
        return collect($this->groups($manager))->flatMap(fn ($group) => array_keys($group['permissions']))->values()->all();
    }

    public function apply(User $user, User $manager, array $selected): void
    {
        $this->validateSelection($manager, $selected, $user->hasAnyRole(['client_admin', 'client_user']));
        $editable = $this->editableNames($manager);
        $this->initialize($user);
        $before = $user->permissions->pluck('name')->all();
        $preserved = $user->permissions->pluck('name')->diff($editable);
        $permissions = $preserved->merge($selected)->unique()->map(fn ($name) => Permission::findOrCreate($name, 'web'));
        $user->syncPermissions($permissions);
        app(ActivityLogService::class)->recordUserPermissions($user, $before, $permissions->pluck('name')->all());
    }

    public function validateSelection(User $manager, array $selected, bool $client): void
    {
        $editable = $this->editableNames($manager);
        if (array_diff($selected, $editable) !== []) {
            throw ValidationException::withMessages(['permissions' => 'Nie możesz nadawać tych uprawnień.']);
        }
        if ($client && $selected !== []) {
            throw ValidationException::withMessages(['permissions' => 'Konto klienta nie może otrzymać wewnętrznych uprawnień pracownika.']);
        }
    }
}
