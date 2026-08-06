<?php

namespace App\Http\Middleware;

use App\Models\CompanySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(
            CompanySettings::moduleIsEnabled($module),
            403,
            'Ten moduł jest wyłączony w konfiguracji aplikacji.'
        );

        return $next($request);
    }
}
