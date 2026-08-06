<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    /** Restrict internal ENESA tools to staff accounts. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasAnyRole(['superadmin', 'admin', 'auditor_senior', 'auditor'])
            && ! $user?->getAllPermissions()->contains('name', 'system.full_access')) {
            abort(403, 'Brak uprawnień do panelu wewnętrznego.');
        }

        return $next($request);
    }
}
