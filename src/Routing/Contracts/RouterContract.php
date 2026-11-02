<?php

declare(strict_types=1);

namespace Rocket\Routing\Contracts;

use Closure;
use Rocket\Http\Request;
use Rocket\Http\Response;

interface RouterContract
{
    public function get(string $uri, array|callable|string $action): RouteContract;
    public function post(string $uri, array|callable|string $action): RouteContract;
    public function put(string $uri, array|callable|string $action): RouteContract;
    public function patch(string $uri, array|callable|string $action): RouteContract;
    public function delete(string $uri, array|callable|string $action): RouteContract;
    public function options(string $uri, array|callable|string $action): RouteContract;
    public function any(string $uri, array|callable|string $action): RouteContract;
    public function match(array $methods, string $uri, array|callable|string $action): RouteContract;
    public function group(array $attributes, Closure $routes): void;
    public function resource(string $name, string $controller): void;
    public function dispatch(Request $request): Response;
    public function compile(): array;
    public function loadCompiled(array $cache): void;
    public function url(string $name, array $parameters = []): string;
}

