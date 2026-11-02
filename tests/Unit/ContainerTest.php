<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Container\Container;
use Rocket\Container\ContainerException;
use Rocket\Container\NotFoundException;

interface SampleInterface
{
    public function getVal(): string;
}

class SampleService implements SampleInterface
{
    public function getVal(): string
    {
        return 'service_val';
    }
}

class DependentService
{
    public function __construct(public SampleInterface $service, public string $custom = 'default')
    {
    }
}

class ContainerTest extends TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_bind_and_make(): void
    {
        $this->container->bind(SampleInterface::class, SampleService::class);
        $instance = $this->container->make(SampleInterface::class);

        $this->assertInstanceOf(SampleService::class, $instance);
        $this->assertSame('service_val', $instance->getVal());
    }

    public function test_singleton_returns_same_instance(): void
    {
        $this->container->singleton(SampleService::class);

        $instance1 = $this->container->make(SampleService::class);
        $instance2 = $this->container->make(SampleService::class);

        $this->assertSame($instance1, $instance2);
    }

    public function test_transient_binding_returns_new_instance(): void
    {
        $this->container->bind(SampleService::class, shared: false);

        $instance1 = $this->container->make(SampleService::class);
        $instance2 = $this->container->make(SampleService::class);

        $this->assertNotSame($instance1, $instance2);
    }

    public function test_autowiring_with_dependency_injection(): void
    {
        $this->container->bind(SampleInterface::class, SampleService::class);

        /** @var DependentService $instance */
        $instance = $this->container->make(DependentService::class);

        $this->assertInstanceOf(DependentService::class, $instance);
        $this->assertSame('service_val', $instance->service->getVal());
        $this->assertSame('default', $instance->custom);
    }

    public function test_parameter_override_on_make(): void
    {
        $this->container->bind(SampleInterface::class, SampleService::class);

        /** @var DependentService $instance */
        $instance = $this->container->make(DependentService::class, ['custom' => 'overridden']);

        $this->assertSame('overridden', $instance->custom);
    }

    public function test_scoped_instances_cleared_on_flush(): void
    {
        $this->container->scoped('counter', function () {
            return new \stdClass();
        });

        $obj1 = $this->container->make('counter');
        $obj2 = $this->container->make('counter');
        $this->assertSame($obj1, $obj2);

        $this->container->flushScoped();

        $obj3 = $this->container->make('counter');
        $this->assertNotSame($obj1, $obj3);
    }

    public function test_call_method_injection(): void
    {
        $this->container->bind(SampleInterface::class, SampleService::class);

        $result = $this->container->call(function (SampleInterface $svc, string $suffix = '!') {
            return $svc->getVal() . $suffix;
        }, ['suffix' => '?']);

        $this->assertSame('service_val?', $result);
    }

    public function test_not_found_exception(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->make('NonExistentClassXYZ');
    }
}

