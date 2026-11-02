<?php

declare(strict_types=1);

namespace Rocket\Foundation;

/**
 * Class Env
 *
 * Lightweight, zero-dependency .env environment variable parser and loader.
 */
class Env
{
    /**
     * Loaded variables repository.
     *
     * @var array<string, mixed>
     */
    protected static array $repository = [];

    /**
     * Load environment variables from a .env file.
     */
    public static function load(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Split key and value
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Handle quoted values
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            } else {
                // Strip inline comment if not quoted
                if (str_contains($value, ' #')) {
                    $value = trim(explode(' #', $value, 2)[0]);
                }
            }

            $parsedValue = self::parseValue($value);

            self::$repository[$key] = $parsedValue;
            $_ENV[$key] = $parsedValue;
            putenv("{$key}={$value}");
        }

        return true;
    }

    /**
     * Parse type-casted values.
     */
    protected static function parseValue(string $value): mixed
    {
        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)'   => null,
            default            => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value,
        };
    }

    /**
     * Get an environment variable value with default fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$repository)) {
            return self::$repository[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $envValue = getenv($key);
        if ($envValue !== false) {
            return self::parseValue($envValue);
        }

        return $default;
    }

    /**
     * Set an environment variable programmatically.
     */
    public static function set(string $key, mixed $value): void
    {
        self::$repository[$key] = $value;
        $_ENV[$key] = $value;
        putenv("{$key}=" . (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value));
    }

    /**
     * Clear loaded environment repository.
     */
    public static function clear(): void
    {
        self::$repository = [];
    }
}

