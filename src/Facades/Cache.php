<?php

declare(strict_types=1);

namespace Rocket\Facades;

use Rocket\Cache\Contracts\CacheStoreInterface;
use Closure;

/**
 * Class Cache
 *
 * Facade for Unified Cache API.
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, int $ttl = 0)
 * @method static bool has(string $key)
 * @method static bool forget(string $key)
 * @method static bool flush()
 * @method static mixed remember(string $key, int $ttl, Closure $callback)
 * @method static CacheStoreInterface store(?string $name = null)
 * @method static CacheStoreInterface driver(?string $driver = null)
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache';
    }
}

