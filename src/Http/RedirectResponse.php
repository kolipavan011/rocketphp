<?php

declare(strict_types=1);

namespace Rocket\Http;

/**
 * Class RedirectResponse
 *
 * Specialized HTTP response for redirects.
 */
class RedirectResponse extends Response
{
    public function __construct(string $url, int $statusCode = 302, array $headers = [])
    {
        $headers['Location'] = $url;
        $content = sprintf(
            '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=%1$s"></head><body>Redirecting to <a href="%1$s">%1$s</a>.</body></html>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        );

        parent::__construct($content, $statusCode, $headers);
    }
}

