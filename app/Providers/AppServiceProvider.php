<?php

namespace App\Providers;

use App\Models\CompanySettings;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['created', 'updated', 'deleted', 'restored'] as $action) {
            Event::listen("eloquent.{$action}: *", function (string $eventName, array $models) use ($action): void {
                if (isset($models[0])) {
                    app(ActivityLogService::class)->recordModel($models[0], $action);
                }
            });
        }
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(ActivityLogService::class)->recordAuthentication($event->user, 'login');
            }
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(ActivityLogService::class)->recordAuthentication($event->user, 'logout');
            }
        });

        View::composer('*', function ($view): void {
            $view->with('appBrand', CompanySettings::query()->first());
        });
    }
}
