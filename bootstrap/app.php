<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureAppModuleEnabled;
use App\Http\Middleware\EnsureClientAdmin;
use App\Http\Middleware\EnsureClientRole;
use App\Http\Middleware\EnsureClientZoneSession;
use App\Http\Middleware\EnsureFullStaffAccess;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureStaffRole;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SecurityHeaders;
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
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'auth' => Authenticate::class,
            'client.role' => EnsureClientRole::class,
            'client.admin' => EnsureClientAdmin::class,
            'client.zone.session' => EnsureClientZoneSession::class,
            'staff.role' => EnsureStaffRole::class,
            'full.staff' => EnsureFullStaffAccess::class,
            'superadmin.only' => EnsureSuperAdmin::class,
            'app.module' => EnsureAppModuleEnabled::class,
            'app.permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
