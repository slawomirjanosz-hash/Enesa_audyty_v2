<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $company = $request->routeIs('client-zone.*')
            ? Company::find(session('client_zone_company_id'))
            : $request->user()?->companies()->first();

        abort_unless($company?->moduleEnabled($module), 403, 'Ten moduł został wyłączony dla Twojej firmy.');

        return $next($request);
    }
}
