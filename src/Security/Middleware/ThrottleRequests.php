<?php

declare(strict_types=1);

namespace Rocket\Security\Middleware;

use Closure;
use Rocket\Http\Contracts\MiddlewareInterface;
use Rocket\Http\JsonResponse;
use Rocket\Http\Request;
use Rocket\Http\Response;
use Rocket\Security\RateLimiter;

/**
 * Class ThrottleRequests
 *
 * Middleware enforcing request rate limits per client IP or user.
 */
class ThrottleRequests implements MiddlewareInterface
{
    protected RateLimiter $limiter;
    protected int $maxAttempts;
    protected int $decayMinutes;

    public function __construct(?RateLimiter $limiter = null, int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $this->limiter = $limiter ?? new RateLimiter();
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        $decaySeconds = $this->decayMinutes * 60;

        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            $headers = [
                'Retry-After'                   => (string) $retryAfter,
                'X-RateLimit-Limit'             => (string) $this->maxAttempts,
                'X-RateLimit-Remaining'         => '0',
                'X-RateLimit-Reset'             => (string) (time() + $retryAfter),
            ];

            if ($request->wantsJson()) {
                return new JsonResponse([
                    'error'       => true,
                    'message'     => 'Too Many Requests',
                    'retry_after' => $retryAfter,
                ], 429, $headers);
            }

            return new Response('<h1>429 Too Many Requests</h1><p>Please wait before trying again.</p>', 429, $headers);
        }

        $this->limiter->hit($key, $decaySeconds);

        /** @var Response $response */
        $response = $next($request);

        $response->setHeader('X-RateLimit-Limit', (string) $this->maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', (string) $this->limiter->remaining($key, $this->maxAttempts));

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $ip = $request->ip();
        $path = $request->getPath();
        return sha1("throttle:{$ip}:{$path}");
    }
}

