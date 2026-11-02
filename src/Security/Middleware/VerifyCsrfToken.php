<?php

declare(strict_types=1);

namespace Rocket\Security\Middleware;

use Closure;
use Rocket\Http\Contracts\MiddlewareInterface;
use Rocket\Http\Request;
use Rocket\Http\Response;
use Rocket\Security\Contracts\CsrfTokenManagerContract;

/**
 * Class VerifyCsrfToken
 *
 * Enforces CSRF token verification on state-changing HTTP requests.
 */
class VerifyCsrfToken implements MiddlewareInterface
{
    protected CsrfTokenManagerContract $csrf;

    /**
     * URIs that should be excluded from CSRF verification (e.g. webhooks).
     *
     * @var array<int, string>
     */
    protected array $except = [];

    public function __construct(CsrfTokenManagerContract $csrf)
    {
        $this->csrf = $csrf;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isReading($request) || $this->inExceptArray($request)) {
            return $next($request);
        }

        $token = $this->getTokenFromRequest($request);

        if (!$this->csrf->validateToken($token)) {
            return new Response(
                '<!DOCTYPE html><html><head><title>419 Page Expired</title><style>body{font-family:sans-serif;text-align:center;padding:100px;background:#fdf2f2;color:#991b1b;}h1{font-size:28px;}</style></head><body><h1>419 - CSRF Token Mismatch</h1><p>Your session has expired or the token is invalid. Please refresh and try again.</p></body></html>',
                419
            );
        }

        return $next($request);
    }

    protected function isReading(Request $request): bool
    {
        return in_array($request->getMethod(), ['HEAD', 'GET', 'OPTIONS'], true);
    }

    protected function inExceptArray(Request $request): bool
    {
        $path = $request->getPath();

        foreach ($this->except as $except) {
            $pattern = '#^' . str_replace('\*', '.*', preg_quote($except, '#')) . '$#u';
            if ((bool) preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    protected function getTokenFromRequest(Request $request): ?string
    {
        return $request->input('_token')
            ?? $request->header('x-csrf-token')
            ?? $request->header('x-xsrf-token');
    }
}

