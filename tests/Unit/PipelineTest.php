<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use Closure;
use PHPUnit\Framework\TestCase;
use Rocket\Container\Container;
use Rocket\Http\Contracts\MiddlewareInterface;
use Rocket\Http\Pipeline;
use Rocket\Http\Request;
use Rocket\Http\Response;

class MiddlewareA implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->setAttribute('flow', array_merge($request->getAttribute('flow', []), ['A_before']));
        /** @var Response $response */
        $response = $next($request);
        return $response->withHeader('X-Middleware-A', 'Processed');
    }
}

class MiddlewareB implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->setAttribute('flow', array_merge($request->getAttribute('flow', []), ['B_before']));
        /** @var Response $response */
        $response = $next($request);
        return $response->withHeader('X-Middleware-B', 'Processed');
    }
}

class PipelineTest extends TestCase
{
    public function test_pipeline_execution_order(): void
    {
        $container = new Container();
        $request = Request::create('GET', '/test');
        $request->setAttribute('flow', []);

        $pipeline = new Pipeline($container);
        $response = $pipeline
            ->send($request)
            ->through([
                MiddlewareA::class,
                MiddlewareB::class,
            ])
            ->then(function (Request $req) {
                $this->assertSame(['A_before', 'B_before'], $req->getAttribute('flow'));
                return new Response('Target reached');
            });

        $this->assertSame('Target reached', $response->getContent());
        $this->assertSame('Processed', $response->header('x-middleware-a'));
        $this->assertSame('Processed', $response->header('x-middleware-b'));
    }
}

