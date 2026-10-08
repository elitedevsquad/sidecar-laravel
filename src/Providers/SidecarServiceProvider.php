<?php

namespace EliteDevSquad\SidecarLaravel\Providers;

use EliteDevSquad\SidecarLaravel\Console\{CatalogCommand, ClearActivityCommand};
use EliteDevSquad\SidecarLaravel\{FakeClock, Sidecar};
use EliteDevSquad\SidecarLaravel\Http\Middleware\{FakeClockMiddleware, SidecarInjectJsMiddleware, SidecarMiddleware};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\{Event, Queue};
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class SidecarServiceProvider extends BaseServiceProvider
{
    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__.'/../../resources/config/devsquad-sidecar.php' => config_path('devsquad-sidecar.php'),
        ], 'devsquad-sidecar');

        if ($this->app->isProduction()) {
            return; // @codeCoverageIgnore
        }

        $this->loadRoutesFrom(__DIR__.'/../../resources/routes.php');

        $this->app->singleton(
            'devsquad-sidecar',
            fn () => new Sidecar() // @codeCoverageIgnore
        );

        $router->aliasMiddleware('devsquad-sidecar-auth', SidecarMiddleware::class);

        $kernel = $this->app->make(Kernel::class);

        $kernel->appendMiddlewareToGroup('web', FakeClockMiddleware::class);
        $kernel->appendMiddlewareToGroup('web', SidecarInjectJsMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CatalogCommand::class, ClearActivityCommand::class]);

            FakeClock::applyFromCache();

            Queue::before(function (): void {
                FakeClock::refreshFromCache();
                FakeClock::syncDatabase();
            });
        }

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            FakeClock::syncDatabase($event->connection);
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('sidecar:activity:clear')
                ->daily()
                ->when(fn (): bool => filter_var(
                    config('devsquad-sidecar.activity_cleanup_schedule', true),
                    FILTER_VALIDATE_BOOL
                ));
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../resources/config/devsquad-sidecar.php',
            'devsquad-sidecar'
        );
    }
}
