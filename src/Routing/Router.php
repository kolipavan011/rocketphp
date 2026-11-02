<?php

declare(strict_types=1);

namespace Rocket\Routing;

use Closure;
use Rocket\Container\ContainerInterface;
use Rocket\Http\JsonResponse;
use Rocket\Http\Pipeline;
use Rocket\Http\Request;
use Rocket\Http\Response;
use Rocket\Routing\Controller;
use Rocket\Routing\Contracts\RouteContract;
use Rocket\Routing\Contracts\RouterContract;
use Rocket\Routing\Exceptions\MethodNotAllowedException;
use Rocket\Routing\Exceptions\RouteNotFoundException;
use InvalidArgumentException;

/**
 * Class Router
 *
 * High-performance HTTP Router with O(1) static matching,
 * combined regex variable routing, groups, and route compilation.
 */
class Router implements RouterContract
{
    /**
     * DI Container.
     */
    protected ContainerInterface $container;

    /**
     * All registered routes.
     *
     * @var array<int, Route>
     */
    protected array $routes = [];

    /**
     * Static route map for O(1) lookup.
     *
     * @var array<string, array<string, Route>>
     */
    protected array $staticRoutes = [];

    /**
     * Dynamic parameterized routes list.
     *
     * @var array<string, array<int, Route>>
     */
    protected array $dynamicRoutes = [];

    /**
     * Named routes repository.
     *
     * @var array<string, Route>
     */
    protected array $namedRoutes = [];

    /**
     * Active group attributes stack during route definition.
     *
     * @var array<int, array{prefix?: string, middleware?: array}>
     */
    protected array $groupStack = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function get(string $uri, array|callable|string $action): RouteContract
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    public function post(string $uri, array|callable|string $action): RouteContract
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, array|callable|string $action): RouteContract
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, array|callable|string $action): RouteContract
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, array|callable|string $action): RouteContract
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function options(string $uri, array|callable|string $action): RouteContract
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    public function any(string $uri, array|callable|string $action): RouteContract
    {
        return $this->addRoute(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $action);
    }

    public function match(array $methods, string $uri, array|callable|string $action): RouteContract
    {
        return $this->addRoute($methods, $uri, $action);
    }

    /**
     * Add a route with inherited group prefix and middleware.
     */
    protected function addRoute(array|string $methods, string $uri, array|callable|string $action): Route
    {
        $prefix = $this->getCurrentGroupPrefix();
        $fullUri = $prefix !== '' ? '/' . trim($prefix, '/') . '/' . trim($uri, '/') : $uri;
        $fullUri = '/' . trim($fullUri, '/');

        $route = new Route($methods, $fullUri, $action);

        $groupMiddlewares = $this->getCurrentGroupMiddleware();
        if (!empty($groupMiddlewares)) {
            $route->middleware($groupMiddlewares);
        }

        $this->routes[] = $route;

        foreach ($route->getMethods() as $method) {
            if ($route->isStatic()) {
                $this->staticRoutes[$method][$route->getUri()] = $route;
            } else {
                $this->dynamicRoutes[$method][] = $route;
            }
        }

        return $route;
    }

    /**
     * Define a group of routes.
     */
    public function group(array $attributes, Closure $routes): void
    {
        $this->groupStack[] = $attributes;
        $routes($this);
        array_pop($this->groupStack);
    }

    /**
     * Prefix route group helper.
     */
    public function prefix(string $prefix): static
    {
        $this->groupStack[] = ['prefix' => $prefix];
        return $this;
    }

    protected function getCurrentGroupPrefix(): string
    {
        $prefixes = [];
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix']) && $group['prefix'] !== '') {
                $prefixes[] = trim($group['prefix'], '/');
            }
        }
        return implode('/', $prefixes);
    }

    protected function getCurrentGroupMiddleware(): array
    {
        $middlewares = [];
        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $m = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $middlewares = array_merge($middlewares, $m);
            }
        }
        return $middlewares;
    }

    /**
     * Register RESTful resource routes.
     */
    public function resource(string $name, string $controller): void
    {
        $base = '/' . trim($name, '/');

        $this->get($base, [$controller, 'index'])->name("{$name}.index");
        $this->get("{$base}/create", [$controller, 'create'])->name("{$name}.create");
        $this->post($base, [$controller, 'store'])->name("{$name}.store");
        $this->get("{$base}/{id}", [$controller, 'show'])->name("{$name}.show");
        $this->get("{$base}/{id}/edit", [$controller, 'edit'])->name("{$name}.edit");
        $this->put("{$base}/{id}", [$controller, 'update'])->name("{$name}.update");
        $this->patch("{$base}/{id}", [$controller, 'update']);
        $this->delete("{$base}/{id}", [$controller, 'destroy'])->name("{$name}.destroy");
    }

    /**
     * Match and dispatch incoming request.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        [$route, $params] = $this->findRoute($method, $path);

        $request->setRouteParams($params);

        $action = $route->getAction();
        $controllerMiddlewares = [];

        if (is_array($action) && count($action) === 2) {
            $class = $action[0];
            $methodName = $action[1];
            if (is_string($class) && is_subclass_of($class, Controller::class)) {
                $controllerInstance = $this->container->make($class);
                $controllerMiddlewares = $controllerInstance->getMiddlewareForMethod($methodName);
            } elseif (is_object($class) && $class instanceof Controller) {
                $controllerMiddlewares = $class->getMiddlewareForMethod($methodName);
            }
        }

        // Resolve middleware list for this route
        $allMiddlewares = array_merge($route->getMiddleware(), $controllerMiddlewares);
        $middlewares = $this->resolveRouteMiddlewares($allMiddlewares);

        // Execute route pipeline
        return (new Pipeline($this->container))
            ->send($request)
            ->through($middlewares)
            ->then(fn (Request $req) => $this->runRoute($route, $req, $params));
    }

    /**
     * Find matching route or throw appropriate 404 / 405 exception.
     *
     * @return array{0: Route, 1: array<string, string>}
     */
    protected function findRoute(string $method, string $path): array
    {
        // 1. Direct O(1) static lookup
        if (isset($this->staticRoutes[$method][$path])) {
            return [$this->staticRoutes[$method][$path], []];
        }

        // 2. Dynamic parameterized lookup
        if (isset($this->dynamicRoutes[$method])) {
            foreach ($this->dynamicRoutes[$method] as $route) {
                if ($route->matches($method, $path)) {
                    return [$route, $route->extractParameters($path)];
                }
            }
        }

        // 3. Check for 405 Method Not Allowed across all routes
        $allowedMethods = [];
        foreach ($this->routes as $candidate) {
            if ($candidate->matchesPath($path)) {
                $allowedMethods = array_merge($allowedMethods, $candidate->getMethods());
            }
        }

        if (!empty($allowedMethods)) {
            $uniqueAllowed = array_values(array_unique($allowedMethods));
            throw new MethodNotAllowedException($uniqueAllowed, "HTTP method [{$method}] not allowed for URI [{$path}]. Allowed: " . implode(', ', $uniqueAllowed));
        }

        throw new RouteNotFoundException("Route [{$method} {$path}] not found.");
    }

    /**
     * Execute the matched route's action.
     */
    protected function runRoute(Route $route, Request $request, array $params): Response
    {
        $action = $route->getAction();

        // Merge Request and route parameters for dependency injection
        $parameters = array_merge($params, [
            'request' => $request,
            Request::class => $request,
        ]);

        $result = $this->container->call($action, $parameters);

        return $this->toResponse($result);
    }

    /**
     * Normalize any returned action value into an HTTP Response.
     */
    public function toResponse(mixed $response): Response
    {
        if ($response instanceof Response) {
            return $response;
        }

        if (is_array($response) || $response instanceof \JsonSerializable) {
            return new JsonResponse($response);
        }

        if ($response === null) {
            return new Response('', 204);
        }

        return new Response((string) $response);
    }

    /**
     * Expand route middlewares (resolves aliases and middleware groups).
     */
    protected function resolveRouteMiddlewares(array $middlewares): array
    {
        $resolved = [];

        $kernel = $this->container->bound('kernel') ? $this->container->make('kernel') : null;

        foreach ($middlewares as $middleware) {
            if ($kernel !== null) {
                // Check if it's a group (e.g. 'web', 'api')
                $group = $kernel->getMiddlewareGroup($middleware);
                if (!empty($group)) {
                    $resolved = array_merge($resolved, $this->resolveRouteMiddlewares($group));
                    continue;
                }

                // Check alias (e.g. 'auth')
                $aliased = $kernel->getRouteMiddleware($middleware);
                if ($aliased !== null) {
                    $resolved[] = $aliased;
                    continue;
                }
            }

            if ($this->container->bound($middleware) || class_exists($middleware) || is_callable($middleware) || is_object($middleware)) {
                $resolved[] = $middleware;
            }
        }

        return $resolved;
    }

    /**
     * Compile routes for production caching.
     */
    public function compile(): array
    {
        $compiled = [
            'static' => [],
            'dynamic' => [],
            'names' => [],
        ];

        foreach ($this->routes as $route) {
            $data = [
                'methods'    => $route->getMethods(),
                'uri'        => $route->getUri(),
                'action'     => $route->getAction(),
                'name'       => $route->getName(),
                'middleware' => $route->getMiddleware(),
                'constraints'=> $route->getConstraints(),
            ];

            if ($route->getName() !== null) {
                $compiled['names'][$route->getName()] = $data;
            }

            foreach ($route->getMethods() as $method) {
                if ($route->isStatic()) {
                    $compiled['static'][$method][$route->getUri()] = $data;
                } else {
                    $compiled['dynamic'][$method][] = $data;
                }
            }
        }

        return $compiled;
    }

    /**
     * Load compiled routes from cache array.
     */
    public function loadCompiled(array $cache): void
    {
        $this->routes = [];
        $this->staticRoutes = [];
        $this->dynamicRoutes = [];
        $this->namedRoutes = [];

        foreach ($cache['static'] ?? [] as $method => $routes) {
            foreach ($routes as $uri => $data) {
                $route = new Route($data['methods'], $data['uri'], $data['action']);
                $route->middleware($data['middleware']);
                if ($data['name'] !== null) {
                    $route->name($data['name']);
                    $this->namedRoutes[$data['name']] = $route;
                }
                $this->staticRoutes[$method][$uri] = $route;
                $this->routes[] = $route;
            }
        }

        foreach ($cache['dynamic'] ?? [] as $method => $routes) {
            foreach ($routes as $data) {
                $route = new Route($data['methods'], $data['uri'], $data['action']);
                $route->middleware($data['middleware']);
                $route->where($data['constraints']);
                if ($data['name'] !== null) {
                    $route->name($data['name']);
                    $this->namedRoutes[$data['name']] = $route;
                }
                $this->dynamicRoutes[$method][] = $route;
                $this->routes[] = $route;
            }
        }
    }

    /**
     * Generate URL by route name.
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = null;

        // Check named routes cache
        if (isset($this->namedRoutes[$name])) {
            $route = $this->namedRoutes[$name];
        } else {
            foreach ($this->routes as $r) {
                if ($r->getName() === $name) {
                    $route = $r;
                    $this->namedRoutes[$name] = $r;
                    break;
                }
            }
        }

        if ($route === null) {
            throw new InvalidArgumentException("Route [{$name}] not defined.");
        }

        $uri = $route->getUri();

        foreach ($parameters as $key => $value) {
            $uri = str_replace(['{' . $key . '}', '{' . $key . '?}'], (string) $value, $uri);
        }

        // Clean any leftover optional parameters
        $uri = preg_replace('/\{[a-zA-Z0-9_]+\?\}/', '', $uri);

        return '/' . trim($uri, '/');
    }

    /**
     * Get all registered routes.
     *
     * @return array<int, Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
