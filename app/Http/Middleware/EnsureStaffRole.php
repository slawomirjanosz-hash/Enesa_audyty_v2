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
        if (! $request->user()?->hasAnyRole(['superadmin', 'admin', 'auditor_senior', 'auditor'])) {
            abort(403, 'Brak uprawnień do panelu wewnętrznego.');
        }

        return $next($request);
    }
}
