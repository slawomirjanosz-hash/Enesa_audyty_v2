<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        $sensitive = ['settings.company.manage', 'settings.roles.manage', 'settings.users.manage'];
        $fullAccessAllowed = count(array_intersect($permissions, $sensitive)) === 0;

        abort_unless(
            $user && (
                $user->hasRole('superadmin')
                || $user->canAny($permissions)
                || ($fullAccessAllowed && $user->can('system.full_access'))
            ),
            403,
            'Nie masz uprawnień do wykonania tej operacji.'
        );

        return $next($request);
    }
}
