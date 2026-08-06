<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('superadmin')) {
            abort(403, 'Tylko superadmin może zarządzać danymi firmy właściciela.');
        }

        return $next($request);
    }
}
