<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Rocket\Http\Contracts\MiddlewareInterface;
use Rocket\Http\Request;
use Rocket\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        // Pre-processing logic...

        $response = $next($request);

        // Post-processing logic...

        return $response;
    }
}