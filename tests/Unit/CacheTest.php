<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Cache\CacheManager;
use Rocket\Cache\Stores\ArrayStore;
use Rocket\Cache\Stores\FileStore;
use Rocket\Facades\Cache;
use Rocket\Foundation\Application;

class CacheTest extends TestCase
{
    protected string $tempCacheDir;
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rocket_cache_test_' . uniqid();
        mkdir($this->tempCacheDir, 0755, true);

        $this->app = new Application();
        $manager = new CacheManager($this->app);
        $this->app->instance('cache', $manager);
    }

    protected function tearDown(): void
    {
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

    public function test_array_store_crud_and_remember(): void
    {
        $store = new ArrayStore();

        $this->assertFalse($store->has('key1'));
        $store->put('key1', 'value1', 3600);
        $this->assertTrue($store->has('key1'));
        $this->assertSame('value1', $store->get('key1'));

        // Remember
        $computed = $store->remember('key2', 3600, fn () => 'computed_val');
        $this->assertSame('computed_val', $computed);
        $this->assertSame('computed_val', $store->get('key2'));

        // Increment & Decrement
        $this->assertSame(1, $store->increment('counter'));
        $this->assertSame(5, $store->increment('counter', 4));
        $this->assertSame(3, $store->decrement('counter', 2));

        // Forget & Flush
        $store->forget('key1');
        $this->assertFalse($store->has('key1'));

        $store->flush();
        $this->assertFalse($store->has('key2'));
    }

    public function test_file_store_persistence(): void
    {
        $store = new FileStore($this->tempCacheDir);

        $data = ['user' => 'John', 'role' => 'editor', 'permissions' => [1, 2, 3]];
        $store->put('user_profile', $data, 300);

        $this->assertTrue($store->has('user_profile'));
        $this->assertSame($data, $store->get('user_profile'));

        $store->forget('user_profile');
        $this->assertFalse($store->has('user_profile'));
        $this->assertNull($store->get('user_profile'));
    }

    public function test_cache_facade(): void
    {
        Cache::put('facade_key', 'facade_val', 60);
        $this->assertSame('facade_val', Cache::get('facade_key'));
    }
}

