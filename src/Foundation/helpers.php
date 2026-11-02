<?php

declare(strict_types=1);

use Rocket\Container\Container;
use Rocket\Foundation\Application;
use Rocket\Foundation\Config;
use Rocket\Http\JsonResponse;
use Rocket\Http\RedirectResponse;
use Rocket\Http\Response;
use Rocket\View\Contracts\ViewEngineInterface;

if (!function_exists('app')) {
    /**
     * Get the available container instance or resolve an abstract from it.
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $container = Container::getInstance();

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        /** @var Config $config */
        $config = app('config');

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML special characters in a string, except for HtmlableInterface objects.
     */
    function e(mixed $value): string
    {
        if ($value instanceof \Rocket\View\Contracts\HtmlableInterface) {
            return $value->toHtml();
        }

        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view.
     */
    function view(string $view, array $data = [], int $status = 200, array $headers = []): Response
    {
        /** @var ViewEngineInterface $engine */
        $engine = app('view');
        return $engine->make($view, $data, $status, $headers);
    }
}

if (!function_exists('response')) {
    /**
     * Return a new HTTP response.
     */
    function response(string $content = '', int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }
}

if (!function_exists('json')) {
    /**
     * Return a new JSON response.
     */
    function json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }
}

if (!function_exists('redirect')) {
    /**
     * Return a new redirect response.
     */
    function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the current CSRF token.
     */
    function csrf_token(): string
    {
        if (app()->bound('security.csrf')) {
            return app('security.csrf')->getToken();
        }
        return '';
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a CSRF token hidden input tag.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('method_field')) {
    /**
     * Generate an HTTP method override hidden input tag.
     */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('validate')) {
    /**
     * Validate data against specified rules.
     */
    function validate(array $data, array $rules): array
    {
        return \Rocket\Security\Validation\Validator::make($data, $rules)->validate();
    }
}

if (!function_exists('vite')) {
    /**
     * Generate HTML tags for Vite assets.
     */
    function vite(array|string $entrypoints, ?string $buildDirectory = null): string
    {
        return app('vite')($entrypoints, $buildDirectory);
    }
}
