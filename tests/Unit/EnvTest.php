<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Foundation\Env;

class EnvTest extends TestCase
{
    protected string $tempEnvFile;

    protected function setUp(): void
    {
        parent::setUp();
        Env::clear();
        $this->tempEnvFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_test_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempEnvFile)) {
            unlink($this->tempEnvFile);
        }
        Env::clear();
        parent::tearDown();
    }

    public function test_loads_and_parses_env_file(): void
    {
        $content = <<<ENV
APP_NAME=Rocketphp
APP_ENV=local
APP_DEBUG=true
APP_PORT=8080
RATE_LIMIT=12.5
EMPTY_VAL=empty
NULL_VAL=null
QUOTED_VAL="Hello World"
SINGLE_QUOTED='Single Quoted'
WITH_COMMENT=value # this is a comment
# Just a comment line
ENV;
        file_put_contents($this->tempEnvFile, $content);

        $this->assertTrue(Env::load($this->tempEnvFile));

        $this->assertSame('Rocketphp', Env::get('APP_NAME'));
        $this->assertSame('local', Env::get('APP_ENV'));
        $this->assertTrue(Env::get('APP_DEBUG'));
        $this->assertSame(8080, Env::get('APP_PORT'));
        $this->assertSame(12.5, Env::get('RATE_LIMIT'));
        $this->assertSame('', Env::get('EMPTY_VAL'));
        $this->assertNull(Env::get('NULL_VAL'));
        $this->assertSame('Hello World', Env::get('QUOTED_VAL'));
        $this->assertSame('Single Quoted', Env::get('SINGLE_QUOTED'));
        $this->assertSame('value', Env::get('WITH_COMMENT'));
    }

    public function test_default_fallback(): void
    {
        $this->assertSame('fallback', Env::get('NON_EXISTENT_KEY', 'fallback'));
    }
}

