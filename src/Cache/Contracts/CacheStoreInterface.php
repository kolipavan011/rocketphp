<?php

declare(strict_types=1);

namespace Rocket\Cache\Contracts;

use Closure;

interface CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value, int $ttl = 0): bool;
    public function has(string $key): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function remember(string $key, int $ttl, Closure $callback): mixed;
    public function increment(string $key, int $value = 1): int|bool;
    public function decrement(string $key, int $value = 1): int|bool;
}

