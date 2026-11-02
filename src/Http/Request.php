<?php

declare(strict_types=1);

namespace Rocket\Http;

/**
 * Class Request
 *
 * Lightweight, high-performance HTTP Request representation.
 */
class Request
{
    protected string $method;
    protected string $uri;
    protected string $path;
    protected array $query;
    protected array $post;
    protected array $attributes;
    protected array $cookies;
    protected array $files;
    protected array $server;
    protected array $headers;
    protected ?string $rawBody;
    protected ?array $jsonPayload = null;
    protected array $routeParams = [];
    protected mixed $session = null;
    protected mixed $user = null;

    public function __construct(
        string $method = 'GET',
        string $uri = '/',
        array $query = [],
        array $post = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $rawBody = null
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = $this->extractPath($uri);
        $this->query = $query;
        $this->post = $post;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->headers = $this->extractHeaders($server);
        $this->rawBody = $rawBody;
    }

    /**
     * Create a new Request instance from PHP globals.
     */
    public static function capture(): static
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Check for method spoofing in POST requests
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper((string) $_POST['_method']);
        } elseif ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = strtoupper((string) $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            $rawBody = null;
        }

        return new static(
            $method,
            $uri,
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
            $rawBody
        );
    }

    /**
     * Factory method for creating requests programmatically (useful for tests and sub-requests).
     */
    public static function create(
        string $method = 'GET',
        string $uri = '/',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): static {
        $server['REQUEST_METHOD'] = strtoupper($method);
        $server['REQUEST_URI'] = $uri;

        $query = [];
        $post = [];

        if (strtoupper($method) === 'GET') {
            $query = $parameters;
        } else {
            $post = $parameters;
        }

        $parsedUrl = parse_url($uri);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $urlQuery);
            $query = array_merge($urlQuery, $query);
        }

        return new static(
            $method,
            $uri,
            $query,
            $post,
            [],
            $cookies,
            $files,
            $server,
            $content
        );
    }

    protected function extractPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $cleanPath = is_string($path) ? rawurldecode($path) : '/';
        return '/' . trim($cleanPath, '/');
    }

    protected function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = (string) $value;
            }
        }
        return $headers;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->header('x-forwarded-proto') === 'https')
            || ((int) ($this->server['SERVER_PORT'] ?? 80) === 443);
    }

    public function ajax(): bool
    {
        return $this->header('x-requested-with') === 'XMLHttpRequest';
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('accept', '');
        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }

    public function ip(): string
    {
        return $this->server['HTTP_CF_CONNECTING_IP']
            ?? $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['REMOTE_ADDR']
            ?? '127.0.0.1';
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalized = strtolower(str_replace('_', '-', $key));
        return $this->headers[$normalized] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    public function getBody(): ?string
    {
        return $this->rawBody;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonPayload === null) {
            $raw = $this->rawBody ?? '';
            $decoded = json_decode($raw, true);
            $this->jsonPayload = is_array($decoded) ? $decoded : [];
        }

        if ($key === null) {
            return $this->jsonPayload;
        }

        return $this->jsonPayload[$key] ?? $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = $this->all();

        if ($key === null) {
            return $all;
        }

        return $all[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json() ?? [], $this->attributes);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        $results = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $results[$key] = $all[$key];
            }
        }
        return $results;
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        foreach ($keys as $key) {
            unset($all[$key]);
        }
        return $all;
    }

    public function has(string $key): bool
    {
        $all = $this->all();
        return isset($all[$key]);
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');
        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->routeParams;
        }
        return $this->routeParams[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setUser(mixed $user): void
    {
        $this->user = $user;
    }

    public function user(): mixed
    {
        return $this->user;
    }

    public function setSession(mixed $session): void
    {
        $this->session = $session;
    }

    public function session(): mixed
    {
        return $this->session;
    }

    public function hasSession(): bool
    {
        return $this->session !== null;
    }
}

