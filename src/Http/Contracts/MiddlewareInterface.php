<?php

declare(strict_types=1);

namespace Rocket\Http\Contracts;

use Closure;
use Rocket\Http\Request;
use Rocket\Http\Response;

/**
 * Interface MiddlewareInterface
 *
 * Contract for HTTP middleware in the Rocketphp pipeline.
 */
interface MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response;
}

