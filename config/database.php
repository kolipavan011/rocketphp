<?php

declare(strict_types=1);

use Rocket\Foundation\Env;

return [
    'default' => Env::get('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => Env::get('DB_DATABASE', dirname(__DIR__) . '/database/database.sqlite'),
        ],

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => Env::get('DB_HOST', '127.0.0.1'),
            'port'      => Env::get('DB_PORT', 3306),
            'database'  => Env::get('DB_DATABASE', 'rocketphp'),
            'username'  => Env::get('DB_USERNAME', 'root'),
            'password'  => Env::get('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
        ],

        'pgsql' => [
            'driver'    => 'pgsql',
            'host'      => Env::get('DB_HOST', '127.0.0.1'),
            'port'      => Env::get('DB_PORT', 5432),
            'database'  => Env::get('DB_DATABASE', 'rocketphp'),
            'username'  => Env::get('DB_USERNAME', 'postgres'),
            'password'  => Env::get('DB_PASSWORD', ''),
        ],
    ],
];

