<?php

declare(strict_types=1);

namespace Rocket\Tests\Benchmark;

use Rocket\Container\Container;
use Rocket\Database\DatabaseManager;
use Rocket\Database\Schema\Blueprint;
use Rocket\Database\Schema\Schema;
use Rocket\Foundation\Application;
use Rocket\Foundation\Config;
use Rocket\Foundation\Kernel;
use Rocket\Http\Request;
use Rocket\Http\Response;
use Rocket\Routing\Router;
use Rocket\View\Compiler;
use Rocket\View\ViewEngine;

class ServiceC {}
class ServiceB { public function __construct(public ServiceC $c) {} }
class ServiceA { public function __construct(public ServiceB $b) {} }

/**
 * Class FrameworkBenchmark
 *
 * Real, reproducible performance and memory benchmarks for Rocketphp.
 */
class FrameworkBenchmark
{
    protected Application $app;

    public function runAll(): array
    {
        $results = [];

        $results['bootstrap'] = $this->benchmarkBootstrap(1000);
        $results['di_container'] = $this->benchmarkContainer(10000);
        $results['route_dispatch'] = $this->benchmarkRouteDispatch(10000);
        $results['middleware_overhead'] = $this->benchmarkMiddlewareOverhead(5000);
        $results['database_query'] = $this->benchmarkDatabase(1000);
        $results['view_rendering'] = $this->benchmarkViewEngine(2000);

        return $results;
    }

    /**
     * Measure Application Kernel bootstrap time & memory.
     */
    public function benchmarkBootstrap(int $iterations = 1000): array
    {
        $startMem = memory_get_usage();
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $app = new Application();
            $kernel = new Kernel($app);
            $app->instance('kernel', $kernel);
            unset($app, $kernel);
        }

        $duration = microtime(true) - $start;
        $peakMem = memory_get_peak_usage();

        return [
            'iterations'        => $iterations,
            'total_time_ms'     => round($duration * 1000, 2),
            'avg_time_per_op_us'=> round(($duration / $iterations) * 1000000, 2),
            'ops_per_second'    => (int) round($iterations / $duration),
            'peak_memory_kb'    => round($peakMem / 1024, 2),
        ];
    }

    /**
     * Measure DI Container autowiring and reflection cache performance.
     */
    public function benchmarkContainer(int $iterations = 10000): array
    {
        $container = new Container();
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $obj = $container->make(ServiceA::class);
        }

        $duration = microtime(true) - $start;

        return [
            'iterations'        => $iterations,
            'total_time_ms'     => round($duration * 1000, 2),
            'avg_time_per_op_us'=> round(($duration / $iterations) * 1000000, 2),
            'ops_per_second'    => (int) round($iterations / $duration),
        ];
    }

    /**
     * Measure pure HTTP request-response route dispatching.
     */
    public function benchmarkRouteDispatch(int $iterations = 10000): array
    {
        $container = new Container();
        $router = new Router($container);
        $router->get('/api/users/{id}', fn ($id) => ['user_id' => $id]);

        $request = Request::create('GET', '/api/users/42');
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $response = $router->dispatch($request);
        }

        $duration = microtime(true) - $start;

        return [
            'iterations'        => $iterations,
            'total_time_ms'     => round($duration * 1000, 2),
            'avg_time_per_op_us'=> round(($duration / $iterations) * 1000000, 2),
            'ops_per_second'    => (int) round($iterations / $duration),
        ];
    }

    /**
     * Measure 5-stage middleware pipeline overhead.
     */
    public function benchmarkMiddlewareOverhead(int $iterations = 5000): array
    {
        $container = new Container();
        $router = new Router($container);

        $m = new class implements \Rocket\Http\Contracts\MiddlewareInterface {
            public function handle(Request $r, \Closure $n): Response { return $n($r); }
        };

        $container->instance('m1', $m);
        $container->instance('m2', $m);
        $container->instance('m3', $m);
        $container->instance('m4', $m);
        $container->instance('m5', $m);

        $router->get('/piped', fn () => 'OK')
            ->middleware(['m1', 'm2', 'm3', 'm4', 'm5']);

        $request = Request::create('GET', '/piped');
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $res = $router->dispatch($request);
        }

        $duration = microtime(true) - $start;

        return [
            'iterations'        => $iterations,
            'total_time_ms'     => round($duration * 1000, 2),
            'avg_time_per_op_us'=> round(($duration / $iterations) * 1000000, 2),
            'ops_per_second'    => (int) round($iterations / $duration),
        ];
    }

    /**
     * Measure Database Query Builder operations on in-memory SQLite.
     */
    public function benchmarkDatabase(int $iterations = 1000): array
    {
        $config = new Config('', [
            'database' => [
                'default' => 'sqlite',
                'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']],
            ],
        ]);
        $db = new DatabaseManager($config);
        $container = new Container();
        Container::setInstance($container);
        $container->instance('db', $db);

        Schema::create('bench_records', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->integer('score');
        });

        $start = microtime(true);

        for ($i = 1; $i <= $iterations; $i++) {
            $db->table('bench_records')->insert(['title' => "Title {$i}", 'score' => $i * 10]);
            $row = $db->table('bench_records')->where('id', $i)->first();
        }

        $duration = microtime(true) - $start;

        return [
            'iterations'        => $iterations,
            'total_time_ms'     => round($duration * 1000, 2),
            'avg_time_per_op_us'=> round(($duration / $iterations) * 1000000, 2),
            'ops_per_second'    => (int) round($iterations / $duration),
        ];
    }

    /**
     * Measure View Engine compiled template evaluation.
     */
    public function benchmarkViewEngine(int $iterations = 2000): array
    {
        $cacheDir = sys_get_temp_dir() . '/rocket_bench_view_' . uniqid();
        mkdir($cacheDir, 0755, true);

        $compiler = new Compiler($cacheDir);
        $viewPath = sys_get_temp_dir() . '/rocket_view_' . uniqid() . '.rocket.php';
        file_put_contents($viewPath, '<h1>{{ $title }}</h1><ul>@foreach($items as $i)<li>{{ $i }}</li>@endforeach</ul>');

        $engine = new ViewEngine($compiler, [dirname($viewPath)]);

        // Warm up compilation
        $engine->render(basename($viewPath, '.rocket.php'), ['title' => 'Warmup', 'items' => [1, 2]]);

        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $html = $engine->render(basename($viewPath, '.rocket.php'), [
                'title' => 'Benchmark Run',
                'items' => ['Alpha', 'Beta', 'Gamma'],
            ]);
        }

        $duration = microtime(true) - $start;

        unlink($viewPath);

        return [
            'iterations'        => $iterations,
            'total_time_ms'     => round($duration * 1000, 2),
            'avg_time_per_op_us'=> round(($duration / $iterations) * 1000000, 2),
            'ops_per_second'    => (int) round($iterations / $duration),
        ];
    }
}

