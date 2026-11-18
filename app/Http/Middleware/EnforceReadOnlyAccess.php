<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnforceReadOnlyAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->hasFullReadOnlyAccess() || $request->routeIs('logout', 'client-zone.impersonate', 'client-zone.stop')) {
            return $next($request);
        }

        abort_unless($request->isMethodSafe() && ! $request->routeIs('projects.protocols.copy'), 403,
            'Masz pełny dostęp tylko do odczytu. Zapisywanie i zmienianie danych jest zablokowane.');

        // Some GET screens mark tasks or notifications as read.
        DB::beginTransaction();
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store');
            if ($response instanceof StreamedResponse && $callback = $response->getCallback()) {
                $response->setCallback(function () use ($callback): void {
                    DB::beginTransaction();
                    try {
                        $callback();
                    } finally {
                        DB::rollBack();
                    }
                });
            }

            return $response;
        } finally {
            DB::rollBack();
        }
    }
}
