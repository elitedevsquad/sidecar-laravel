<?php

namespace EliteDevSquad\Sidecar\Providers;

use EliteDevSquad\Sidecar\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\Sidecar\Sidecar;
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
            function () {
                return new Sidecar(); // @codeCoverageIgnore
            }
        );

        $router->aliasMiddleware('devsquad-sidecar-auth', SidecarMiddleware::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../resources/config/devsquad-sidecar.php',
            'devsquad-sidecar'
        );
    }
}
