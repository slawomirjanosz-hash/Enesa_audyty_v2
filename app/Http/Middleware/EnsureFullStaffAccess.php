<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFullStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasRole('superadmin')
            && ! $user?->canAny([
                'settings.users.view',
                'settings.users.manage',
                'settings.roles.manage',
                'settings.company.manage',
                'settings.archive.view',
                'system.full_access',
            ])) {
            abort(403, 'Brak uprawnień do ustawień.');
        }

        return $next($request);
    }
}
