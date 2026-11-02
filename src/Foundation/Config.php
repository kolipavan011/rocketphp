<?php

declare(strict_types=1);

namespace Rocket\Foundation;

/**
 * Class Config
 *
 * High-performance configuration repository with dot-notation retrieval and caching.
 */
class Config
{
    /**
     * Stored configuration items.
     *
     * @var array<string, mixed>
     */
    protected array $items = [];

    /**
     * Path to configuration directory.
     */
    protected string $configPath;

    public function __construct(string $configPath = '', array $items = [])
    {
        $this->configPath = rtrim($configPath, '/\\');
        $this->items = $items;
    }

    /**
     * Load all configuration files from the config directory.
     */
    public function loadFromDirectory(string $directory): void
    {
        $this->configPath = rtrim($directory, '/\\');

        if (!is_dir($this->configPath)) {
            return;
        }

        $files = glob($this->configPath . '/*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $data = require $file;
            if (is_array($data)) {
                $this->items[$key] = $data;
            }
        }
    }

    /**
     * Get a configuration value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        if (!str_contains($key, '.')) {
            return $this->items[$key] ?? $default;
        }

        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Set a configuration value using dot notation.
     */
    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &$this->items;

        while (count($segments) > 1) {
            $segment = array_shift($segments);
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current[array_shift($segments)] = $value;
    }

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get all configuration items.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}

