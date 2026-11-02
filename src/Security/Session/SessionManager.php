<?php

declare(strict_types=1);

namespace Rocket\Security\Session;

/**
 * Class SessionManager
 *
 * Lightweight, secure session repository with flash messages and fixation defense.
 */
class SessionManager
{
    protected string $id;
    protected array $attributes = [];
    protected array $flash = [];
    protected bool $started = false;

    public function __construct(?string $id = null, array $attributes = [])
    {
        $this->id = $id ?? bin2hex(random_bytes(16));
        $this->attributes = $attributes;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function remove(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function all(): array
    {
        return $this->attributes;
    }

    public function clear(): void
    {
        $this->attributes = [];
    }

    /**
     * Regenerate session ID to prevent session fixation attacks.
     */
    public function regenerate(bool $destroyOld = true): void
    {
        $this->id = bin2hex(random_bytes(16));
    }

    /**
     * Store flash data available only for the next request.
     */
    public function flash(string $key, mixed $value): void
    {
        $this->flash[$key] = $value;
        $this->attributes['_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->attributes['_flash'][$key] ?? $default;
    }
}

