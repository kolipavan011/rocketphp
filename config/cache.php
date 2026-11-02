<?php

declare(strict_types=1);

use Rocket\Foundation\Env;

return [
    'default' => Env::get('CACHE_DRIVER', 'file'),

    'stores' => [
        'array' => [
            'driver' => 'array',
        ],

        'file' => [
            'driver' => 'file',
            'path'   => dirname(__DIR__) . '/storage/cache/data',
        ],
    ],
];

