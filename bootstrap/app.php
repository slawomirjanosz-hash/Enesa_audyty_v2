<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: ['/logout']);

        $middleware->alias([
            'auth'               => \App\Http\Middleware\Authenticate::class,
            'client.role'        => \App\Http\Middleware\EnsureClientRole::class,
            'client.admin'       => \App\Http\Middleware\EnsureClientAdmin::class,
            'client.zone.session' => \App\Http\Middleware\EnsureClientZoneSession::class,
            'staff.role'         => \App\Http\Middleware\EnsureStaffRole::class,
            'full.staff'         => \App\Http\Middleware\EnsureFullStaffAccess::class,
            'superadmin.only'    => \App\Http\Middleware\EnsureSuperAdmin::class,
            'app.module'         => \App\Http\Middleware\EnsureAppModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
