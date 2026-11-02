<?php

declare(strict_types=1);

namespace Rocket\Cache\Stores;

use Closure;
use Rocket\Cache\Contracts\CacheStoreInterface;

/**
 * Class ArrayStore
 *
 * Ephemeral in-memory cache store.
 */
class ArrayStore implements CacheStoreInterface
{
    /**
     * Stored items with expiration timestamp.
     *
     * @var array<string, array{value: mixed, expires_at: int}>
     */
    protected array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->storage[$key]['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        $expiresAt = $ttl > 0 ? time() + $ttl : 0;
        $this->storage[$key] = [
            'value'      => $value,
            'expires_at' => $expiresAt,
        ];
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        $expiresAt = $this->storage[$key]['expires_at'];
        if ($expiresAt > 0 && time() > $expiresAt) {
            unset($this->storage[$key]);
            return false;
        }

        return true;
    }

    public function forget(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    public function flush(): bool
    {
        $this->storage = [];
        return true;
    }

    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $value;
        $this->put($key, $new);
        return $new;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }
}

