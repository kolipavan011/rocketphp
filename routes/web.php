<?php

declare(strict_types=1);

use Rocket\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'name'    => 'Rocketphp',
        'version' => \Rocket\Foundation\Application::VERSION,
        'php'     => PHP_VERSION,
    ]);
});

Route::get('/health', function () {
    return json([
        'status'    => 'healthy',
        'framework' => 'Rocketphp',
        'time'      => date('c'),
    ]);
});

