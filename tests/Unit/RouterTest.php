<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Container\Container;
use Rocket\Facades\Route as RouteFacade;
use Rocket\Http\JsonResponse;
use Rocket\Http\Request;
use Rocket\Http\Response;
use Rocket\Routing\Exceptions\MethodNotAllowedException;
use Rocket\Routing\Exceptions\RouteNotFoundException;
use Rocket\Routing\Router;

class DummyController
{
    public function show(int $id, Request $request): JsonResponse
    {
        return new JsonResponse(['id' => $id, 'path' => $request->getPath()]);
    }
}

class RouterTest extends TestCase
{
    protected Container $container;
    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        Container::setInstance($this->container);
        $this->router = new Router($this->container);
        $this->container->instance('router', $this->router);
    }

    public function test_basic_get_route_dispatch(): void
    {
        $this->router->get('/hello', function () {
            return 'Hello World';
        });

        $request = Request::create('GET', '/hello');
        $response = $this->router->dispatch($request);

        $this->assertSame('Hello World', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_parameterized_route_with_constraint(): void
    {
        $this->router->get('/users/{id}', function (Request $req, $id) {
            return new JsonResponse(['user_id' => (int) $id]);
        })->whereNumber('id');

        $request = Request::create('GET', '/users/123');
        $response = $this->router->dispatch($request);

        $this->assertSame('{"user_id":123}', $response->getContent());

        // Test non-numeric parameter causes 404
        $this->expectException(RouteNotFoundException::class);
        $this->router->dispatch(Request::create('GET', '/users/abc'));
    }

    public function test_optional_parameter(): void
    {
        $this->router->get('/archive/{year?}', function ($year = '2026') {
            return "Archive: {$year}";
        });

        $res1 = $this->router->dispatch(Request::create('GET', '/archive/2024'));
        $this->assertSame('Archive: 2024', $res1->getContent());

        $res2 = $this->router->dispatch(Request::create('GET', '/archive'));
        $this->assertSame('Archive: 2026', $res2->getContent());
    }

    public function test_route_groups_with_prefix(): void
    {
        $this->router->group(['prefix' => 'admin'], function (Router $router) {
            $router->get('/dashboard', fn () => 'Admin Dashboard');
            $router->get('/settings', fn () => 'Admin Settings');
        });

        $response = $this->router->dispatch(Request::create('GET', '/admin/dashboard'));
        $this->assertSame('Admin Dashboard', $response->getContent());
    }

    public function test_named_route_and_url_generation(): void
    {
        $this->router->get('/posts/{slug}/comments/{id}', fn () => 'ok')
            ->name('posts.comment.show');

        $url = $this->router->url('posts.comment.show', ['slug' => 'rocket-framework', 'id' => 99]);
        $this->assertSame('/posts/rocket-framework/comments/99', $url);
    }

    public function test_method_not_allowed_exception(): void
    {
        $this->router->post('/submit', fn () => 'Saved');

        try {
            $this->router->dispatch(Request::create('GET', '/submit'));
            $this->fail('Expected MethodNotAllowedException not thrown');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(405, $e->getStatusCode());
            $this->assertContains('POST', $e->getAllowedMethods());
        }
    }

    public function test_controller_action_dispatch_and_di(): void
    {
        $this->router->get('/items/{id}', [DummyController::class, 'show']);

        $request = Request::create('GET', '/items/55');
        $response = $this->router->dispatch($request);

        $this->assertSame('{"id":55,"path":"/items/55"}', $response->getContent());
    }

    public function test_route_compilation_and_cached_loading(): void
    {
        $this->router->get('/home', fn () => 'Home Page')->name('home');
        $this->router->get('/user/{id}', fn ($id) => "User {$id}")->name('user.show')->whereNumber('id');

        $compiled = $this->router->compile();

        // New fresh router loading compiled cache
        $freshRouter = new Router($this->container);
        $freshRouter->loadCompiled($compiled);

        $res1 = $freshRouter->dispatch(Request::create('GET', '/home'));
        $this->assertSame('Home Page', $res1->getContent());

        $res2 = $freshRouter->dispatch(Request::create('GET', '/user/456'));
        $this->assertSame('User 456', $res2->getContent());
    }

    public function test_route_facade(): void
    {
        RouteFacade::get('/facade-test', fn () => 'Facade Works');

        $response = $this->router->dispatch(Request::create('GET', '/facade-test'));
        $this->assertSame('Facade Works', $response->getContent());
    }

    public function test_resource_routing(): void
    {
        $this->router->resource('articles', DummyController::class);

        $routes = $this->router->getRoutes();
        $uris = array_map(fn ($r) => $r->getUri(), $routes);

        $this->assertContains('/articles', $uris);
        $this->assertContains('/articles/create', $uris);
        $this->assertContains('/articles/{id}', $uris);
        $this->assertContains('/articles/{id}/edit', $uris);
    }

    public function test_route_middleware_execution(): void
    {
        $middleware = new class implements \Rocket\Http\Contracts\MiddlewareInterface {
            public function handle(Request $request, \Closure $next): Response {
                $response = $next($request);
                return $response->withHeader('X-Route-Middleware', 'Passed');
            }
        };

        $this->container->instance('test.auth', $middleware);

        $this->router->get('/protected', fn () => 'Secret Area')
            ->middleware('test.auth');

        $response = $this->router->dispatch(Request::create('GET', '/protected'));
        $this->assertSame('Secret Area', $response->getContent());
        $this->assertSame('Passed', $response->header('x-route-middleware'));
    }
}
