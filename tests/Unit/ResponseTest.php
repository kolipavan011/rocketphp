<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Http\JsonResponse;
use Rocket\Http\RedirectResponse;
use Rocket\Http\Response;

class ResponseTest extends TestCase
{
    public function test_standard_response(): void
    {
        $response = new Response('<h1>Hello</h1>', 200);
        $response->withHeader('X-Custom-Header', 'Rocket');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getStatusText());
        $this->assertSame('<h1>Hello</h1>', $response->getContent());
        $this->assertSame('Rocket', $response->header('X-Custom-Header'));
        $this->assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
    }

    public function test_json_response(): void
    {
        $response = new JsonResponse(['success' => true, 'count' => 10], 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        $this->assertSame('{"success":true,"count":10}', $response->getContent());
    }

    public function test_redirect_response(): void
    {
        $response = new RedirectResponse('/dashboard');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/dashboard', $response->header('Location'));
    }

    public function test_cookie_handling(): void
    {
        $response = new Response();
        $response->cookie('session_id', 'xyz789', 60, '/', 'example.com', true, true, 'Strict');

        $cookies = $response->getCookies();
        $this->assertArrayHasKey('session_id', $cookies);
        $this->assertSame('xyz789', $cookies['session_id']['value']);
        $this->assertSame('Strict', $cookies['session_id']['samesite']);
        $this->assertTrue($cookies['session_id']['httponly']);
    }
}

