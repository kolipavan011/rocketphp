<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Foundation\Config;

class ConfigTest extends TestCase
{
    public function test_get_and_set_dot_notation(): void
    {
        $config = new Config('', [
            'app' => [
                'name' => 'Rocketphp',
                'nested' => [
                    'item' => 123,
                ],
            ],
        ]);

        $this->assertSame('Rocketphp', $config->get('app.name'));
        $this->assertSame(123, $config->get('app.nested.item'));
        $this->assertNull($config->get('app.nested.unknown'));
        $this->assertSame('default', $config->get('app.unknown', 'default'));

        $config->set('app.nested.item', 456);
        $this->assertSame(456, $config->get('app.nested.item'));

        $config->set('database.connections.mysql.host', '127.0.0.1');
        $this->assertSame('127.0.0.1', $config->get('database.connections.mysql.host'));
    }

    public function test_has(): void
    {
        $config = new Config('', ['services' => ['stripe' => ['key' => 'xyz']]]);
        $this->assertTrue($config->has('services.stripe.key'));
        $this->assertFalse($config->has('services.github.key'));
    }
}

