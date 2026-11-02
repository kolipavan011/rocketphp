<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Console\Application as ConsoleApp;
use Rocket\Foundation\Application;
use Rocket\Foundation\ServiceProvider;

class TestCustomServiceProvider extends ServiceProvider
{
    public bool $registered = false;
    public bool $booted = false;

    public function register(): void
    {
        $this->registered = true;
        $this->app->singleton('test.service', fn () => 'service_instance');
    }

    public function boot(): void
    {
        $this->booted = true;
        $this->app->instance('test.booted_flag', true);
    }
}

class ServiceProviderTest extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application();
    }

    public function test_service_provider_lifecycle(): void
    {
        $provider = new TestCustomServiceProvider($this->app);
        $this->app->register($provider);

        $this->assertTrue($provider->registered);
        $this->assertFalse($provider->booted);
        $this->assertTrue($this->app->bound('test.service'));
        $this->assertSame('service_instance', $this->app->make('test.service'));

        $this->app->boot();

        $this->assertTrue($provider->booted);
        $this->assertTrue($this->app->bound('test.booted_flag'));
        $this->assertTrue($this->app->make('test.booted_flag'));
    }

    public function test_make_provider_cli_command(): void
    {
        $console = new ConsoleApp($this->app);

        ob_start();
        $exitCode = $console->call('make:provider', ['Payment']);
        ob_end_clean();

        $this->assertSame(0, $exitCode);

        $filePath = $this->app->appPath('Providers/PaymentServiceProvider.php');
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('class PaymentServiceProvider extends ServiceProvider', $content);
        $this->assertStringContainsString('public function register(): void', $content);
        $this->assertStringContainsString('public function boot(): void', $content);

        // Clean up generated provider file
        unlink($filePath);
    }
}

