<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientZoneSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('client_zone_company_id')) {
            return redirect()->route('client-zone.index')
                ->with('error', 'Wybierz najpierw firmę klienta.');
        }

        return $next($request);
    }
}
