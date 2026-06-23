<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasAnyRole(['client_admin', 'client_user'])) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
