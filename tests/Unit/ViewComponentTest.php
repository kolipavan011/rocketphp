<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Container\Container;
use Rocket\View\Compiler;
use Rocket\View\Component;
use Rocket\View\ComponentAttributeBag;
use Rocket\View\ComponentSlot;
use Rocket\View\ViewEngine;
use Rocket\Console\Application as ConsoleApp;
use Rocket\Foundation\Application as CoreApp;
use InvalidArgumentException;
use RuntimeException;

class TestUserBadgeComponent extends Component
{
    public function __construct(
        public string $user,
        public string $role = 'member'
    ) {
        parent::__construct();
    }

    public function render(): string
    {
        return '<span ' . $this->attributes->merge(['class' => 'badge badge-' . $this->role]) . '>' . $this->user . ' (' . $this->slot . ')</span>';
    }
}

class TestConditionalComponent extends Component
{
    public function __construct(public bool $show = false)
    {
        parent::__construct();
    }

    public function shouldRender(): bool
    {
        return $this->show;
    }

    public function render(): string
    {
        return '<div>Visible</div>';
    }
}

class ViewComponentTest extends TestCase
{
    protected string $tempViewsDir;
    protected string $tempCacheDir;
    protected Compiler $compiler;
    protected ViewEngine $engine;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempViewsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_comp_views_' . uniqid();
        $this->tempCacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_comp_cache_' . uniqid();

        mkdir($this->tempViewsDir . DIRECTORY_SEPARATOR . 'components', 0755, true);
        mkdir($this->tempCacheDir, 0755, true);

        $this->container = new Container();
        Container::setInstance($this->container);

        $this->compiler = new Compiler($this->tempCacheDir);
        $this->engine = new ViewEngine($this->compiler, [$this->tempViewsDir], $this->container);
        $this->container->instance('view', $this->engine);
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
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function test_attribute_bag_operations(): void
    {
        $bag = new ComponentAttributeBag([
            'id' => 'main-alert',
            'class' => 'alert-danger',
            'data-role' => 'admin',
            'disabled' => true,
            'hidden' => false,
        ]);

        $this->assertTrue($bag->has('id'));
        $this->assertFalse($bag->has('missing'));
        $this->assertSame('main-alert', $bag->get('id'));
        $this->assertSame('default', $bag->get('missing', 'default'));
        $this->assertSame('main-alert', $bag['id']);

        // Merge
        $merged = $bag->merge(['class' => 'alert p-4', 'role' => 'alert']);
        $this->assertSame('alert p-4 alert-danger', $merged->get('class'));
        $this->assertSame('alert', $merged->get('role'));
        $this->assertSame('main-alert', $merged->get('id'));

        // Class helper
        $classed = (new ComponentAttributeBag(['class' => 'base']))
            ->class(['font-bold', 'text-red' => true, 'text-green' => false]);
        $this->assertSame('base font-bold text-red', $classed->get('class'));

        // Filtering
        $prefixed = $bag->whereStartsWith('data-');
        $this->assertTrue($prefixed->has('data-role'));
        $this->assertFalse($prefixed->has('id'));

        // Only & except
        $this->assertSame(['id' => 'main-alert'], $bag->only('id')->all());
        $this->assertFalse($bag->except('id', 'class')->has('id'));

        // HTML rendering (disabled is boolean true, hidden is false so omitted)
        $html = (string) $bag;
        $this->assertStringContainsString('id="main-alert"', $html);
        $this->assertStringContainsString('class="alert-danger"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString('hidden', $html);
    }

    public function test_component_slot(): void
    {
        $emptySlot = new ComponentSlot('   ');
        $this->assertTrue($emptySlot->isEmpty());
        $this->assertFalse($emptySlot->isNotEmpty());
        $this->assertFalse($emptySlot->hasActualContent());

        $filledSlot = new ComponentSlot('Hello World', ['class' => 'text-bold']);
        $this->assertFalse($filledSlot->isEmpty());
        $this->assertTrue($filledSlot->isNotEmpty());
        $this->assertSame('Hello World', (string) $filledSlot);
        $this->assertSame('text-bold', $filledSlot->attributes->get('class'));
    }

    public function test_anonymous_component_basic_rendering(): void
    {
        $componentPath = $this->tempViewsDir . '/components/alert.rocket.php';
        file_put_contents($componentPath, '<div {{ $attributes->merge([\'class\' => \'alert\']) }}>{{ $slot }}</div>');

        $viewPath = $this->tempViewsDir . '/test.rocket.php';
        file_put_contents($viewPath, '<x-alert class="alert-danger" id="box">Alert message!</x-alert>');

        $output = $this->engine->render('test');

        $this->assertStringContainsString('class="alert alert-danger"', $output);
        $this->assertStringContainsString('id="box"', $output);
        $this->assertStringContainsString('Alert message!', $output);
    }

    public function test_anonymous_component_self_closing(): void
    {
        $componentPath = $this->tempViewsDir . '/components/badge.rocket.php';
        file_put_contents($componentPath, '<span class="badge badge-{{ $color }}">{{ $label ?? \'Default\' }}</span>');

        $viewPath = $this->tempViewsDir . '/test_badge.rocket.php';
        file_put_contents($viewPath, '<x-badge color="success" label="Approved" />');

        $output = $this->engine->render('test_badge');

        $this->assertSame('<span class="badge badge-success">Approved</span>', trim($output));
    }

    public function test_props_directive_in_anonymous_component(): void
    {
        $componentPath = $this->tempViewsDir . '/components/toast.rocket.php';
        file_put_contents(
            $componentPath,
            "@props(['type' => 'info', 'title' => null])\n" .
            "<div {{ \$attributes->merge(['class' => 'toast toast-' . \$type]) }}>" .
            "<strong>{{ \$title }}</strong>" .
            "<span>{{ \$slot }}</span>" .
            "</div>"
        );

        $viewPath = $this->tempViewsDir . '/test_toast.rocket.php';
        file_put_contents(
            $viewPath,
            '<x-toast type="danger" title="Error!" class="shadow-lg" id="toast-1">File not found</x-toast>'
        );

        $output = $this->engine->render('test_toast');

        $this->assertStringContainsString('class="toast toast-danger shadow-lg"', $output);
        $this->assertStringContainsString('id="toast-1"', $output);
        $this->assertStringContainsString('<strong>Error!</strong>', $output);
        $this->assertStringContainsString('<span>File not found</span>', $output);
        // Props type and title should NOT be present as raw HTML attributes in the div
        $this->assertStringNotContainsString('type="danger"', $output);
        $this->assertStringNotContainsString('title="Error!"', $output);
    }

    public function test_bound_php_expressions_and_attributes(): void
    {
        $componentPath = $this->tempViewsDir . '/components/counter.rocket.php';
        file_put_contents(
            $componentPath,
            "@props(['count' => 0, 'active' => false])\n" .
            "<div class=\"counter\" data-count=\"{{ \$count }}\" data-active=\"{{ \$active ? 'yes' : 'no' }}\">{{ \$slot }}</div>"
        );

        $viewPath = $this->tempViewsDir . '/test_counter.rocket.php';
        file_put_contents(
            $viewPath,
            '<x-counter :count="10 + 5" :active="true">Calculated: {{ $total }}</x-counter>'
        );

        $output = $this->engine->render('test_counter', ['total' => 15]);

        $this->assertStringContainsString('data-count="15"', $output);
        $this->assertStringContainsString('data-active="yes"', $output);
        $this->assertStringContainsString('Calculated: 15', $output);
    }

    public function test_named_slots_with_attributes(): void
    {
        $componentPath = $this->tempViewsDir . '/components/card.rocket.php';
        file_put_contents(
            $componentPath,
            "<div class=\"card\">\n" .
            "    @isset(\$header)\n" .
            "        <div {{ \$header->attributes->merge(['class' => 'card-header']) }}>{{ \$header }}</div>\n" .
            "    @endisset\n" .
            "    <div class=\"card-body\">{{ \$slot }}</div>\n" .
            "    @isset(\$footer)\n" .
            "        <div class=\"card-footer\">{{ \$footer }}</div>\n" .
            "    @endisset\n" .
            "</div>"
        );

        $viewPath = $this->tempViewsDir . '/test_card.rocket.php';
        file_put_contents(
            $viewPath,
            "<x-card>\n" .
            "    <x-slot:header class=\"bg-primary text-white\">\n" .
            "        <h3>User Profile</h3>\n" .
            "    </x-slot:header>\n" .
            "    <p>User biographical information.</p>\n" .
            "    <x-slot name=\"footer\">\n" .
            "        <button>Save</button>\n" .
            "    </x-slot>\n" .
            "</x-card>"
        );

        $output = $this->engine->render('test_card');

        $this->assertStringContainsString('class="card-header bg-primary text-white"', $output);
        $this->assertStringContainsString('<h3>User Profile</h3>', $output);
        $this->assertStringContainsString('<p>User biographical information.</p>', $output);
        $this->assertStringContainsString('<div class="card-footer">', $output);
        $this->assertStringContainsString('<button>Save</button>', $output);
    }

    public function test_nested_components(): void
    {
        $cardPath = $this->tempViewsDir . '/components/panel.rocket.php';
        file_put_contents($cardPath, '<div class="panel">{{ $header ?? \'\' }}<div class="body">{{ $slot }}</div></div>');

        $badgePath = $this->tempViewsDir . '/components/tag.rocket.php';
        file_put_contents($badgePath, '<span class="tag tag-{{ $type }}">{{ $slot }}</span>');

        $viewPath = $this->tempViewsDir . '/test_nested.rocket.php';
        file_put_contents(
            $viewPath,
            "<x-panel>\n" .
            "    <x-slot:header>\n" .
            "        <x-tag type=\"danger\">Alert</x-tag>\n" .
            "    </x-slot:header>\n" .
            "    Content with <x-tag type=\"info\">Status</x-tag>\n" .
            "</x-panel>"
        );

        $output = $this->engine->render('test_nested');

        $this->assertStringContainsString('<span class="tag tag-danger">Alert</span>', $output);
        $this->assertStringContainsString('<span class="tag tag-info">Status</span>', $output);
    }

    public function test_class_based_component(): void
    {
        $this->engine->component('user-badge', TestUserBadgeComponent::class);

        $viewPath = $this->tempViewsDir . '/test_class_comp.rocket.php';
        file_put_contents(
            $viewPath,
            '<x-user-badge user="Alice" role="admin" id="alice-badge">Active</x-user-badge>'
        );

        $output = $this->engine->render('test_class_comp');

        $this->assertSame(
            '<span class="badge badge-admin" id="alice-badge">Alice (Active)</span>',
            trim($output)
        );
    }

    public function test_class_based_component_conditional_rendering(): void
    {
        $this->engine->component('conditional', TestConditionalComponent::class);

        $viewPath = $this->tempViewsDir . '/test_conditional.rocket.php';
        file_put_contents(
            $viewPath,
            'A<x-conditional :show="false" />B<x-conditional :show="true" />C'
        );

        $output = $this->engine->render('test_conditional');

        $this->assertSame('AB<div>Visible</div>C', trim($output));
    }

    public function test_mismatched_component_tags_throws_exception(): void
    {
        $viewPath = $this->tempViewsDir . '/test_mismatch.rocket.php';
        file_put_contents($viewPath, '<x-card>Content</x-alert>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mismatched component tags: expected </x-card>, found </x-alert>.');

        $this->engine->render('test_mismatch');
    }

    public function test_missing_component_throws_exception(): void
    {
        $viewPath = $this->tempViewsDir . '/test_missing.rocket.php';
        file_put_contents($viewPath, '<x-non-existent-component />');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Component [non-existent-component] not found');

        $this->engine->render('test_missing');
    }

    public function test_make_component_scaffolding(): void
    {
        $app = new CoreApp(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_app_' . uniqid());
        $console = new ConsoleApp($app);

        ob_start();
        $status = $console->call('make:component', ['TestCard']);
        $output = ob_get_clean();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Component class [TestCard] created', $output);
        $this->assertStringContainsString('Component view created', $output);

        $classFile = $app->appPath('View/Components/TestCard.php');
        $viewFile = $app->resourcePath('views/components/test-card.rocket.php');

        $this->assertFileExists($classFile);
        $this->assertFileExists($viewFile);

        // Inline test
        ob_start();
        $statusInline = $console->call('make:component', ['TestInline', '--inline']);
        ob_get_clean();

        $this->assertSame(0, $statusInline);
        $inlineClass = $app->appPath('View/Components/TestInline.php');
        $this->assertFileExists($inlineClass);
        $this->assertStringContainsString('return <<<\'HTML\'', file_get_contents($inlineClass));

        // Clean up
        $this->removeDir($app->basePath());
    }
}

