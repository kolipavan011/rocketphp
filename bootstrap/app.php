<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Rocket\Database\DatabaseServiceProvider;
use Rocket\Foundation\Application;
use Rocket\Foundation\Kernel;
use Rocket\Routing\RouteServiceProvider;
use Rocket\Security\SecurityServiceProvider;
use Rocket\View\ViewServiceProvider;

$app = new Application(dirname(__DIR__));

// Register Framework & Application Service Providers
$app->register(new RouteServiceProvider($app));
$app->register(new ViewServiceProvider($app));
$app->register(new DatabaseServiceProvider($app));
$app->register(new SecurityServiceProvider($app));
$app->register(new AppServiceProvider($app));

// Register HTTP Kernel singleton
$app->singleton('kernel', function () use ($app) {
    return new Kernel($app);
});

// Register Vite Asset Manager singleton
$app->singleton('vite', function () use ($app) {
    return new \Rocket\Foundation\Vite($app);
});
$app->alias('vite', \Rocket\Foundation\Vite::class);

return $app;

