<?php

declare(strict_types=1);

namespace Rocket\Facades;

use Rocket\Routing\Contracts\RouteContract;
use Closure;

/**
 * Class Route
 *
 * Facade for the Rocketphp Router.
 *
 * @method static RouteContract get(string $uri, array|callable|string $action)
 * @method static RouteContract post(string $uri, array|callable|string $action)
 * @method static RouteContract put(string $uri, array|callable|string $action)
 * @method static RouteContract patch(string $uri, array|callable|string $action)
 * @method static RouteContract delete(string $uri, array|callable|string $action)
 * @method static RouteContract any(string $uri, array|callable|string $action)
 * @method static void group(array $attributes, Closure $routes)
 * @method static void resource(string $name, string $controller)
 * @method static string url(string $name, array $parameters = [])
 */
class Route extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'router';
    }
}

