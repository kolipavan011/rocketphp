<?php

declare(strict_types=1);

namespace Rocket\Security;

/**
 * Class RateLimiter
 *
 * Fixed-window / sliding rate limiter for brute-force and DDoS protection.
 */
class RateLimiter
{
    /**
     * In-memory storage for hits and reset timers.
     *
     * @var array<string, array{hits: int, reset_at: int}>
     */
    protected static array $storage = [];

    /**
     * Determine if the given key has exceeded its allowed attempts.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $this->cleanExpired($key);

        if (!isset(self::$storage[$key])) {
            return false;
        }

        return self::$storage[$key]['hits'] >= $maxAttempts;
    }

    /**
     * Increment the counter for a given key for a given decay duration in seconds.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $this->cleanExpired($key);
        $now = time();

        if (!isset(self::$storage[$key])) {
            self::$storage[$key] = [
                'hits'     => 1,
                'reset_at' => $now + $decaySeconds,
            ];
            return 1;
        }

        self::$storage[$key]['hits']++;
        return self::$storage[$key]['hits'];
    }

    /**
     * Get the number of remaining attempts.
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        $this->cleanExpired($key);

        $hits = self::$storage[$key]['hits'] ?? 0;
        return max(0, $maxAttempts - $hits);
    }

    /**
     * Get the number of seconds until the key is reset.
     */
    public function availableIn(string $key): int
    {
        $this->cleanExpired($key);

        if (!isset(self::$storage[$key])) {
            return 0;
        }

        return max(0, self::$storage[$key]['reset_at'] - time());
    }

    /**
     * Reset the attempts counter for a given key.
     */
    public function resetAttempts(string $key): void
    {
        unset(self::$storage[$key]);
    }

    /**
     * Clean expired entries.
     */
    protected function cleanExpired(string $key): void
    {
        if (isset(self::$storage[$key]) && time() > self::$storage[$key]['reset_at']) {
            unset(self::$storage[$key]);
        }
    }

    /**
     * Clear all rate limits (useful for testing).
     */
    public static function clear(): void
    {
        self::$storage = [];
    }
}

