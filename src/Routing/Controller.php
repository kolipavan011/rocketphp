<?php

declare(strict_types=1);

namespace Rocket\Routing;

use Rocket\Http\JsonResponse;
use Rocket\Http\RedirectResponse;
use Rocket\Http\Request;
use Rocket\Http\Response;
use Rocket\View\View;

/**
 * Class Controller
 *
 * Base controller providing convenient response, view, and middleware scoping methods.
 */
abstract class Controller
{
    /**
     * Middleware registered for this controller.
     *
     * @var array<int, array{middleware: string, only: array, except: array}>
     */
    protected array $middleware = [];

    /**
     * Register a middleware for this controller.
     */
    public function middleware(string $middleware): ControllerMiddlewareOptions
    {
        $options = [
            'middleware' => $middleware,
            'only'       => [],
            'except'     => [],
        ];

        $this->middleware[] = &$options;

        return new ControllerMiddlewareOptions($options);
    }

    /**
     * Get the controller middleware filtered for a specific action method.
     *
     * @return array<int, string>
     */
    public function getMiddlewareForMethod(string $method): array
    {
        $active = [];

        foreach ($this->middleware as $definition) {
            $only = $definition['only'];
            $except = $definition['except'];

            if (!empty($only) && !in_array($method, $only, true)) {
                continue;
            }

            if (!empty($except) && in_array($method, $except, true)) {
                continue;
            }

            $active[] = $definition['middleware'];
        }

        return $active;
    }

    /**
     * Return a JSON response.
     */
    protected function json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Return a standard HTTP response.
     */
    protected function response(string $content = '', int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }

    /**
     * Return a redirect response.
     */
    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Render and return a view template.
     */
    protected function view(string $view, array $data = []): Response
    {
        return view($view, $data);
    }

    /**
     * Validate the given request parameters.
     */
    protected function validate(Request $request, array $rules): array
    {
        return validate($request->all(), $rules);
    }
}

