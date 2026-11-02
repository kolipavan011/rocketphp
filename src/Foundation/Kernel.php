<?php

declare(strict_types=1);

namespace Rocket\Foundation;

use Closure;
use Rocket\Foundation\Contracts\KernelContract;
use Rocket\Http\Contracts\MiddlewareInterface;
use Rocket\Http\JsonResponse;
use Rocket\Http\Pipeline;
use Rocket\Http\Request;
use Rocket\Http\Response;
use Rocket\Routing\Contracts\RouterContract;
use Throwable;

/**
 * Class Kernel
 *
 * Core HTTP Kernel handling the request-response lifecycle and middleware pipeline.
 */
class Kernel implements KernelContract
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The router instance.
     */
    protected ?RouterContract $router = null;

    /**
     * Global middleware stack.
     *
     * @var array<int, class-string<MiddlewareInterface>>
     */
    protected array $middleware = [];

    /**
     * Middleware groups (e.g. 'web', 'api').
     *
     * @var array<string, array<int, class-string<MiddlewareInterface>>>
     */
    protected array $middlewareGroups = [
        'web' => [],
        'api' => [],
    ];

    /**
     * Route middleware aliases.
     *
     * @var array<string, class-string<MiddlewareInterface>>
     */
    protected array $routeMiddleware = [];

    /**
     * Indicates if the application has been bootstrapped.
     */
    protected bool $bootstrapped = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Bootstrap the application.
     */
    public function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        // 1. Load Environment Variables
        $envFile = $this->app->basePath('.env');
        if (file_exists($envFile)) {
            Env::load($envFile);
        }

        // 2. Initialize Configuration Repository
        $config = new Config();
        $config->loadFromDirectory($this->app->configPath());
        $this->app->instance('config', $config);
        $this->app->instance(Config::class, $config);

        // 3. Register Configured Service Providers
        $providers = (array) $config->get('app.providers', []);
        foreach ($providers as $provider) {
            $this->app->register($provider);
        }

        // 4. Boot Application Providers
        $this->app->boot();

        $this->bootstrapped = true;
    }

    /**
     * Handle an incoming HTTP request.
     */
    public function handle(Request $request): Response
    {
        $this->bootstrap();

        // Register request as scoped in the container
        $this->app->scoped(Request::class, fn () => $request);
        $this->app->alias(Request::class, 'request');

        try {
            return (new Pipeline($this->app))
                ->send($request)
                ->through($this->middleware)
                ->then($this->dispatchToRouter());
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Get the destination callback for dispatching through the router.
     */
    protected function dispatchToRouter(): Closure
    {
        return function (Request $request): Response {
            /** @var RouterContract $router */
            $router = $this->app->make('router');
            return $router->dispatch($request);
        };
    }

    /**
     * Handle an uncaught exception securely.
     */
    public function handleException(Throwable $e, Request $request): Response
    {
        $statusCode = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;
        if ($statusCode < 400 || $statusCode > 599) {
            $statusCode = 500;
        }

        $isProduction = $this->app->isProduction();

        if ($request->wantsJson()) {
            $payload = [
                'error'   => true,
                'status'  => $statusCode,
                'message' => $isProduction && $statusCode === 500 ? 'Internal Server Error' : $e->getMessage(),
            ];

            if (!$isProduction) {
                $payload['exception'] = get_class($e);
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
                $payload['trace'] = explode("\n", $e->getTraceAsString());
            }

            return new JsonResponse($payload, $statusCode);
        }

        if ($isProduction && $statusCode === 500) {
            $html = '<!DOCTYPE html><html><head><title>500 Internal Server Error</title><style>body{font-family:sans-serif;text-align:center;padding:100px;background:#f8f9fa;color:#333;}</style></head><body><h1>500 - Internal Server Error</h1><p>Something went wrong on our servers.</p></body></html>';
        } else {
            $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $file = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
            $line = $e->getLine();
            $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
            $class = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');

            $html = "<!DOCTYPE html><html><head><title>Rocketphp Error: {$msg}</title><style>body{font-family:monospace;padding:40px;background:#1e1e2e;color:#cdd6f4;}h1{color:#f38ba8;font-size:22px;}pre{background:#11111b;padding:20px;border-radius:8px;overflow-x:auto;color:#a6adc8;line-height:1.5;}</style></head><body><h1>[{$class}] {$msg}</h1><p>In <strong>{$file}</strong> on line <strong>{$line}</strong></p><h3>Stack Trace:</h3><pre>{$trace}</pre></body></html>";
        }

        return new Response($html, $statusCode);
    }

    /**
     * Terminate the request lifecycle.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->app->terminate();
    }

    /**
     * Set global middlewares.
     */
    public function setMiddleware(array $middleware): static
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Set route middleware aliases.
     */
    public function setRouteMiddleware(array $routeMiddleware): static
    {
        $this->routeMiddleware = $routeMiddleware;
        return $this;
    }

    /**
     * Get route middleware class by alias.
     */
    public function getRouteMiddleware(string $alias): ?string
    {
        return $this->routeMiddleware[$alias] ?? null;
    }

    /**
     * Get middleware group.
     */
    public function getMiddlewareGroup(string $group): array
    {
        return $this->middlewareGroups[$group] ?? [];
    }

    /**
     * Set middleware groups.
     */
    public function setMiddlewareGroups(array $groups): static
    {
        $this->middlewareGroups = $groups;
        return $this;
    }
}

