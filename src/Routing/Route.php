<?php

declare(strict_types=1);

namespace Rocket\Routing;

use Closure;
use Rocket\Routing\Contracts\RouteContract;

/**
 * Class Route
 *
 * Represents an individual HTTP route registration.
 */
class Route implements RouteContract
{
    /**
     * HTTP methods supported by this route.
     *
     * @var array<int, string>
     */
    protected array $methods;

    /**
     * URI pattern.
     */
    protected string $uri;

    /**
     * Route action (Closure, array [Class, method], or invokable).
     */
    protected mixed $action;

    /**
     * Optional route name.
     */
    protected ?string $name = null;

    /**
     * Middleware attached to this route.
     *
     * @var array<int, string>
     */
    protected array $middleware = [];

    /**
     * Parameter regex constraints.
     *
     * @var array<string, string>
     */
    protected array $constraints = [];

    /**
     * Compiled regex for route matching.
     */
    protected ?string $compiledRegex = null;

    /**
     * List of parameter names extracted from URI pattern.
     *
     * @var array<int, string>
     */
    protected array $parameterNames = [];

    public function __construct(array|string $methods, string $uri, array|callable|string $action)
    {
        $this->methods = array_map('strtoupper', (array) $methods);
        $this->uri = '/' . trim($uri, '/');
        $this->action = $action;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAction(): array|callable|string
    {
        return $this->action;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function middleware(array|string $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : func_get_args();
        $this->middleware = array_merge($this->middleware, $middlewares);
        return $this;
    }

    public function where(array|string $name, ?string $pattern = null): static
    {
        if (is_array($name)) {
            foreach ($name as $key => $val) {
                $this->constraints[$key] = $val;
            }
        } elseif ($pattern !== null) {
            $this->constraints[$name] = $pattern;
        }

        $this->compiledRegex = null; // Invalidate cached regex
        return $this;
    }

    public function whereNumber(string ...$parameters): static
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[0-9]+');
        }
        return $this;
    }

    public function whereAlpha(string ...$parameters): static
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[a-zA-Z]+');
        }
        return $this;
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * Compile URI pattern into a regular expression.
     */
    public function getCompiledRegex(): string
    {
        if ($this->compiledRegex !== null) {
            return $this->compiledRegex;
        }

        $this->parameterNames = [];

        // First replace optional parameters with leading slash: `/{name?}` -> `(?:/(?P<name>[^/]+))?`
        $pattern = preg_replace_callback('/\/\{([a-zA-Z_][a-zA-Z0-9_-]*)\?\}/', function ($matches) {
            $name = $matches[1];
            $this->parameterNames[] = $name;
            $constraint = $this->constraints[$name] ?? '[^/]+';
            return "(?:/(?P<{$name}>{$constraint}))?";
        }, $this->uri);

        // Then replace required parameters: `{name}` -> `(?P<name>[^/]+)`
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/', function ($matches) {
            $name = $matches[1];
            $this->parameterNames[] = $name;
            $constraint = $this->constraints[$name] ?? '[^/]+';
            return "(?P<{$name}>{$constraint})";
        }, $pattern);

        $this->compiledRegex = '#^' . $pattern . '$#u';
        return $this->compiledRegex;
    }

    /**
     * Determine if the path matches this route regardless of HTTP method.
     */
    public function matchesPath(string $path): bool
    {
        $path = '/' . trim($path, '/');

        if ($this->isStatic()) {
            return $this->uri === $path;
        }

        return (bool) preg_match($this->getCompiledRegex(), $path);
    }

    /**
     * Check if route matches HTTP method and path.
     */
    public function matches(string $method, string $path): bool
    {
        if (!in_array(strtoupper($method), $this->methods, true) && !in_array('ANY', $this->methods, true)) {
            return false;
        }

        return $this->matchesPath($path);
    }

    /**
     * Extract parameter values from URI path.
     *
     * @return array<string, string>
     */
    public function extractParameters(string $path): array
    {
        $path = '/' . trim($path, '/');

        if (!str_contains($this->uri, '{')) {
            return [];
        }

        if (preg_match($this->getCompiledRegex(), $path, $matches)) {
            $params = [];
            foreach ($this->parameterNames as $name) {
                if (isset($matches[$name]) && $matches[$name] !== '') {
                    $params[$name] = $matches[$name];
                }
            }
            return $params;
        }

        return [];
    }

    /**
     * Check if this is a static route without parameters.
     */
    public function isStatic(): bool
    {
        return !str_contains($this->uri, '{');
    }
}
