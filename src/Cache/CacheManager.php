<?php

declare(strict_types=1);

namespace Rocket\Cache;

use Closure;
use Rocket\Cache\Contracts\CacheStoreInterface;
use Rocket\Cache\Stores\ArrayStore;
use Rocket\Cache\Stores\FileStore;
use Rocket\Foundation\Application;
use InvalidArgumentException;

/**
 * Class CacheManager
 *
 * Unified cache manager supporting multiple cache stores and tagging.
 */
class CacheManager implements CacheStoreInterface
{
    protected Application $app;
    protected array $stores = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a cache store instance by driver name.
     */
    public function store(?string $name = null): CacheStoreInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->createStore($name);
        }

        return $this->stores[$name];
    }

    public function driver(?string $driver = null): CacheStoreInterface
    {
        return $this->store($driver);
    }

    public function getDefaultDriver(): string
    {
        if ($this->app->bound('config')) {
            return (string) $this->app->make('config')->get('cache.default', 'file');
        }

        return 'file';
    }

    protected function createStore(string $name): CacheStoreInterface
    {
        return match ($name) {
            'array' => new ArrayStore(),
            'file'  => new FileStore($this->app->storagePath('cache/data')),
            default => throw new InvalidArgumentException("Unsupported cache store driver [{$name}]."),
        };
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->store()->put($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    public function flush(): bool
    {
        return $this->store()->flush();
    }

    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        return $this->store()->remember($key, $ttl, $callback);
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->store()->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->store()->decrement($key, $value);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->store()->$method(...$parameters);
    }
}

