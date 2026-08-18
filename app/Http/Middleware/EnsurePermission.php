<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        $sensitive = ['settings.company.manage', 'settings.roles.manage', 'settings.users.manage'];
        $fullAccessAllowed = count(array_intersect($permissions, $sensitive)) === 0;
        $availablePermissionNames = app(PermissionRegistrar::class)
            ->getPermissions()
            ->pluck('name');
        $availablePermissions = collect($permissions)
            ->filter(fn (string $permission): bool => $availablePermissionNames->contains($permission))
            ->all();

        try {
            $hasPermission = $user && $availablePermissions !== [] && (
                $user->hasRole('superadmin')
                || $user->canAny($availablePermissions)
                || ($fullAccessAllowed && $user->can('system.full_access'))
            );
        } catch (PermissionDoesNotExist) {
            $hasPermission = false;
        }

        abort_unless(
            $hasPermission,
            403,
            'Nie masz uprawnień do wykonania tej operacji.'
        );

        return $next($request);
    }
}
