<?php

declare(strict_types=1);

namespace Rocket\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Rocket\Foundation\Application;
use Rocket\Foundation\Kernel;
use Rocket\Http\Request;
use Rocket\Routing\RouteServiceProvider;
use Rocket\View\ViewServiceProvider;

class HttpLifecycleTest extends TestCase
{
    protected Application $app;
    protected Kernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $this->kernel = $this->app->make('kernel');
    }

    public function test_welcome_page_renders_successfully(): void
    {
        $request = Request::create('GET', '/');
        $response = $this->kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Rocketphp Framework', $response->getContent());
        $this->assertStringContainsString('Speed. Security. Simplicity.', $response->getContent());
        $this->assertStringContainsString('<link rel="stylesheet" href="/build/assets/app-', $response->getContent());
        $this->assertStringContainsString('<script type="module" src="/build/assets/app-', $response->getContent());
    }

    public function test_health_json_endpoint(): void
    {
        $request = Request::create('GET', '/health', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->header('content-type'));
        $data = json_decode($response->getContent(), true);

        $this->assertSame('healthy', $data['status']);
        $this->assertSame('Rocketphp', $data['framework']);
    }

    public function test_api_ping_route(): void
    {
        $request = Request::create('GET', '/api/ping');
        $response = $this->kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"message":"pong"}', $response->getContent());
    }
}

