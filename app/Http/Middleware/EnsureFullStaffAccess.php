<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFullStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasAnyRole(['superadmin', 'admin', 'auditor_senior'])) {
            abort(403, 'Brak uprawnień do ustawień.');
        }

        return $next($request);
    }
}