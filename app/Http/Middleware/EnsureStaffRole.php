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

        $hasStaffRole = $user?->getRoleNames()
            ->contains(fn (string $role) => ! in_array($role, ['client_admin', 'client_user'], true));

        if (! $hasStaffRole) {
            abort(403, 'Brak uprawnień do panelu wewnętrznego.');
        }

        return $next($request);
    }
}
