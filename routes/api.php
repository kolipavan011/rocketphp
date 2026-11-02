<?php

declare(strict_types=1);

use Rocket\Facades\Route;
use Rocket\Http\Request;

Route::get('/ping', fn () => json(['message' => 'pong']));

Route::get('/user', function (Request $request) {
    return json([
        'ip'        => $request->ip(),
        'secure'    => $request->isSecure(),
        'user_agent'=> $request->header('user-agent'),
    ]);
});

