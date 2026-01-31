<?php

namespace EliteDevSquad\SidecarLaravel\Providers;

use EliteDevSquad\SidecarLaravel\Http\Middleware\{FakeClockMiddleware, SidecarMiddleware};
use EliteDevSquad\SidecarLaravel\Sidecar;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class SidecarServiceProvider extends BaseServiceProvider
{
    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__.'/../../resources/config/devsquad-sidecar.php' => config_path('devsquad-sidecar.php'),
        ], 'devsquad-sidecar');

        $this->loadRoutesFrom(__DIR__.'/../../resources/routes.php');

        $this->app->singleton(
            'devsquad-sidecar',
            fn () => new Sidecar() // @codeCoverageIgnore
        );

        $router->aliasMiddleware('devsquad-sidecar-auth', SidecarMiddleware::class);

        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', FakeClockMiddleware::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../resources/config/devsquad-sidecar.php',
            'devsquad-sidecar'
        );
    }
}
