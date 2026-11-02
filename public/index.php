<?php

declare(strict_types=1);

define('ROCKET_START', microtime(true));

// 1. Register Composer Autoloader
require __DIR__ . '/../vendor/autoload.php';

// 2. Initialize Application
/** @var \Rocket\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

// 3. Resolve HTTP Kernel and Handle Request
/** @var \Rocket\Foundation\Contracts\KernelContract $kernel */
$kernel = $app->make('kernel');

$request = \Rocket\Http\Request::capture();
$response = $kernel->handle($request);

// 4. Send Response
$response->send();

// 5. Terminate Request Cycle (cleanup scoped container, flush workers)
$kernel->terminate($request, $response);

