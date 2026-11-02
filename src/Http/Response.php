<?php

declare(strict_types=1);

namespace Rocket\Http;

/**
 * Class Response
 *
 * Lightweight, high-performance HTTP Response object.
 */
class Response
{
    protected int $statusCode;
    protected string $statusText;
    protected array $headers = [];
    protected string $content;
    protected array $cookies = [];

    /**
     * Standard HTTP status phrases.
     */
    protected const STATUS_TEXTS = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        419 => 'Page Expired',
        422 => 'Unprocessable Content',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->statusText = self::STATUS_TEXTS[$statusCode] ?? 'Unknown Status';

        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }

        if (!$this->hasHeader('Content-Type')) {
            $this->setHeader('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusText(): string
    {
        return $this->statusText;
    }

    public function status(int $code): static
    {
        $this->statusCode = $code;
        $this->statusText = self::STATUS_TEXTS[$code] ?? 'Unknown Status';
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalized = strtolower($key);
        return $this->headers[$normalized]['value'] ?? $default;
    }

    public function headers(): array
    {
        $result = [];
        foreach ($this->headers as $header) {
            $result[$header['name']] = $header['value'];
        }
        return $result;
    }

    public function hasHeader(string $key): bool
    {
        return isset($this->headers[strtolower($key)]);
    }

    public function setHeader(string $name, string $value): static
    {
        $this->headers[strtolower($name)] = [
            'name'  => $name,
            'value' => $value,
        ];
        return $this;
    }

    public function withHeader(string $name, string $value): static
    {
        return $this->setHeader($name, $value);
    }

    public function removeHeader(string $name): static
    {
        unset($this->headers[strtolower($name)]);
        return $this;
    }

    /**
     * Add a cookie to the response.
     */
    public function cookie(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        $expires = $minutes > 0 ? time() + ($minutes * 60) : 0;

        $this->cookies[$name] = [
            'name'     => $name,
            'value'    => $value,
            'expires'  => $expires,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];

        return $this;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Send HTTP headers, cookies, and output content.
     */
    public function send(): static
    {
        if (!headers_sent()) {
            // Send status code
            http_response_code($this->statusCode);

            // Send headers
            foreach ($this->headers as $header) {
                header("{$header['name']}: {$header['value']}", true);
            }

            // Send cookies
            foreach ($this->cookies as $cookie) {
                setcookie(
                    $cookie['name'],
                    $cookie['value'],
                    [
                        'expires'  => $cookie['expires'],
                        'path'     => $cookie['path'],
                        'domain'   => $cookie['domain'] ?? '',
                        'secure'   => $cookie['secure'],
                        'httponly' => $cookie['httponly'],
                        'samesite' => $cookie['samesite'],
                    ]
                );
            }
        }

        echo $this->content;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        return $this;
    }
}

