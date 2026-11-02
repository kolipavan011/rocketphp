<?php

declare(strict_types=1);

use Rocket\Foundation\Env;

return [
    'name' => Env::get('APP_NAME', 'Rocketphp'),
    'env'  => Env::get('APP_ENV', 'development'),
    'debug'=> Env::get('APP_DEBUG', true),
    'url'  => Env::get('APP_URL', 'http://127.0.0.1:8000'),
    'key'  => Env::get('APP_KEY', 'base64:4v7g7F9W1eM+vK3QvL/bXkL1jZ2a0B3d8C5eF7gH9jI='),

    'providers' => [
        Rocket\Routing\RouteServiceProvider::class,
        Rocket\View\ViewServiceProvider::class,
        Rocket\Database\DatabaseServiceProvider::class,
        Rocket\Security\SecurityServiceProvider::class,
        App\Providers\AppServiceProvider::class,
    ],
];

