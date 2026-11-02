<?php

declare(strict_types=1);

namespace Rocket\Security\Middleware;

use Closure;
use Rocket\Http\Contracts\MiddlewareInterface;
use Rocket\Http\Request;
use Rocket\Http\Response;

/**
 * Class SecureHeaders
 *
 * Automatically injects industry-standard HTTP security headers.
 */
class SecureHeaders implements MiddlewareInterface
{
    /**
     * Default security headers configuration.
     *
     * @var array<string, string>
     */
    protected array $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options'        => 'SAMEORIGIN',
        'X-XSS-Protection'       => '1; mode=block',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Content-Security-Policy'=> "default-src 'self'; img-src 'self' data: https:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        foreach ($this->headers as $header => $value) {
            if (!$response->hasHeader($header)) {
                $response->setHeader($header, $value);
            }
        }

        if ($request->isSecure() && !$response->hasHeader('Strict-Transport-Security')) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

