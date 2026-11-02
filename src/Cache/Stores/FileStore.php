<?php

declare(strict_types=1);

namespace Rocket\Cache\Stores;

use Closure;
use Rocket\Cache\Contracts\CacheStoreInterface;

/**
 * Class FileStore
 *
 * High-performance file cache store with atomic writes and expiration headers.
 */
class FileStore implements CacheStoreInterface
{
    protected string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    protected function getFilePath(string $key): string
    {
        $hash = sha1($key);
        $folder = $this->directory . DIRECTORY_SEPARATOR . substr($hash, 0, 2);
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        return $folder . DIRECTORY_SEPARATOR . $hash;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return $default;
        }

        $expire = (int) substr($contents, 0, 10);
        if ($expire > 0 && time() > $expire) {
            @unlink($file);
            return $default;
        }

        return unserialize(substr($contents, 10));
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        $file = $this->getFilePath($key);
        $expire = $ttl > 0 ? time() + $ttl : 9999999999;
        $content = sprintf('%010d', $expire) . serialize($value);

        return (bool) file_put_contents($file, $content, LOCK_EX);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return false;
    }

    public function flush(): bool
    {
        $this->clearDirectory($this->directory);
        return true;
    }

    protected function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->clearDirectory($path) : @unlink($path);
        }
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

