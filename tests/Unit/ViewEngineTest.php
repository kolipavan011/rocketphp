<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Container\Container;
use Rocket\View\Compiler;
use Rocket\View\ViewEngine;

class ViewEngineTest extends TestCase
{
    protected string $tempViewsDir;
    protected string $tempCacheDir;
    protected Compiler $compiler;
    protected ViewEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempViewsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_views_' . uniqid();
        $this->tempCacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_cache_' . uniqid();

        mkdir($this->tempViewsDir, 0755, true);
        mkdir($this->tempCacheDir, 0755, true);

        $this->compiler = new Compiler($this->tempCacheDir);
        $this->engine = new ViewEngine($this->compiler, [$this->tempViewsDir]);

        $container = new Container();
        Container::setInstance($container);
        $container->instance('view', $this->engine);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempViewsDir);
        $this->removeDir($this->tempCacheDir);
        parent::tearDown();
    }

    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_variable_escaping_and_xss_prevention(): void
    {
        $content = '<h1>Hello {{ $name }}</h1>';
        file_put_contents($this->tempViewsDir . '/hello.rocket.php', $content);

        $output = $this->engine->render('hello', ['name' => '<script>alert("XSS")</script>']);
        $this->assertSame('<h1>Hello &lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;</h1>', $output);
    }

    public function test_raw_echo(): void
    {
        $content = '<div>{!! $rawHtml !!}</div>';
        file_put_contents($this->tempViewsDir . '/raw.rocket.php', $content);

        $output = $this->engine->render('raw', ['rawHtml' => '<b>Bold Text</b>']);
        $this->assertSame('<div><b>Bold Text</b></div>', $output);
    }

    public function test_conditionals_and_loops(): void
    {
        $content = <<<TEMPLATE
@if(\$isAdmin)
    <p>Welcome Admin</p>
@else
    <p>Welcome Guest</p>
@endif

<ul>
@foreach(\$items as \$item)
    <li>{{ \$item }}</li>
@endforeach
</ul>
TEMPLATE;

        file_put_contents($this->tempViewsDir . '/loop.rocket.php', $content);

        $output = $this->engine->render('loop', [
            'isAdmin' => true,
            'items'   => ['Apple', 'Banana'],
        ]);

        $this->assertStringContainsString('<p>Welcome Admin</p>', $output);
        $this->assertStringContainsString('<li>Apple</li>', $output);
        $this->assertStringContainsString('<li>Banana</li>', $output);
    }

    public function test_template_inheritance(): void
    {
        $layout = <<<LAYOUT
<!DOCTYPE html>
<html>
<head><title>{{ \$title }}</title></head>
<body>
<main>@yield('content')</main>
<footer>@yield('footer', 'Default Footer')</footer>
</body>
</html>
LAYOUT;

        $page = <<<PAGE
@extends('layout')

@section('content')
<h1>Page Heading</h1>
<p>Page Content</p>
@endsection
PAGE;

        file_put_contents($this->tempViewsDir . '/layout.rocket.php', $layout);
        file_put_contents($this->tempViewsDir . '/page.rocket.php', $page);

        $output = $this->engine->render('page', ['title' => 'My Page']);

        $this->assertStringContainsString('<title>My Page</title>', $output);
        $this->assertStringContainsString('<h1>Page Heading</h1>', $output);
        $this->assertStringContainsString('<footer>Default Footer</footer>', $output);
    }

    public function test_partial_includes(): void
    {
        $partial = '<div class="alert alert-{{ $type }}">{{ $msg }}</div>';
        $main = '<h1>Dashboard</h1>@include(\'alert\', [\'type\' => \'success\', \'msg\' => \'All good\'])';

        file_put_contents($this->tempViewsDir . '/alert.rocket.php', $partial);
        file_put_contents($this->tempViewsDir . '/main.rocket.php', $main);

        $output = $this->engine->render('main');
        $this->assertStringContainsString('<div class="alert alert-success">All good</div>', $output);
    }

    public function test_custom_directive(): void
    {
        $this->compiler->directive('upper', function ($expr) {
            return "<?= strtoupper({$expr}) ?>";
        });

        $content = 'Output: @upper("rocket")';
        file_put_contents($this->tempViewsDir . '/custom.rocket.php', $content);

        $output = $this->engine->render('custom');
        $this->assertSame('Output: ROCKET', $output);
    }

    public function test_shared_global_data(): void
    {
        $this->engine->share('appName', 'RocketApp');
        $content = 'App: {{ $appName }}';
        file_put_contents($this->tempViewsDir . '/shared.rocket.php', $content);

        $output = $this->engine->render('shared');
        $this->assertSame('App: RocketApp', $output);
    }
}

