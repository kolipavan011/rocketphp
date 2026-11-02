<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Http\Request;

class RequestTest extends TestCase
{
    public function test_create_request_get_attributes(): void
    {
        $request = Request::create(
            method: 'GET',
            uri: '/users?page=2&sort=asc',
            parameters: ['custom' => 'val'],
            server: ['HTTP_AUTHORIZATION' => 'Bearer sample-token-123']
        );

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/users', $request->getPath());
        $this->assertSame('2', $request->query('page'));
        $this->assertSame('asc', $request->query('sort'));
        $this->assertSame('sample-token-123', $request->bearerToken());
        $this->assertSame('Bearer sample-token-123', $request->header('authorization'));
        $this->assertFalse($request->isSecure());
    }

    public function test_json_payload_parsing(): void
    {
        $json = json_encode(['username' => 'alex', 'role' => 'admin']);
        $request = Request::create(
            method: 'POST',
            uri: '/api/users',
            content: $json
        );

        $this->assertSame('alex', $request->json('username'));
        $this->assertSame('admin', $request->json('role'));
        $this->assertSame('alex', $request->input('username'));
    }

    public function test_route_parameters(): void
    {
        $request = Request::create('GET', '/users/42');
        $request->setRouteParams(['id' => '42']);

        $this->assertSame('42', $request->route('id'));
        $this->assertSame(['id' => '42'], $request->route());
    }

    public function test_only_and_except(): void
    {
        $request = Request::create('POST', '/submit', ['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(['a' => 1, 'b' => 2], $request->only(['a', 'b']));
        $this->assertSame(['b' => 2], $request->except(['a', 'c']));
    }
}

