<?php

declare(strict_types=1);

namespace Rocket\Routing;

use Rocket\Foundation\ServiceProvider;
use Rocket\Routing\Contracts\RouterContract;

/**
 * Class RouteServiceProvider
 *
 * Bootstraps the application routing services and route definitions.
 */
class RouteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('router', function () {
            return new Router($this->app);
        });

        $this->app->alias('router', Router::class);
        $this->app->alias('router', RouterContract::class);
    }

    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        // Load web routes with 'web' middleware group
        $webRoutes = $this->app->basePath('routes/web.php');
        if (file_exists($webRoutes)) {
            $router->group(['middleware' => 'web'], function () use ($webRoutes) {
                require $webRoutes;
            });
        }

        // Load api routes with 'api' middleware group and '/api' prefix
        $apiRoutes = $this->app->basePath('routes/api.php');
        if (file_exists($apiRoutes)) {
            $router->group(['prefix' => 'api', 'middleware' => 'api'], function () use ($apiRoutes) {
                require $apiRoutes;
            });
        }
    }
}

