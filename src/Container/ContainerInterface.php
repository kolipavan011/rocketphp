<?php

declare(strict_types=1);

namespace Rocket\Container;

use Closure;

/**
 * Interface ContainerInterface
 *
 * Contract for the Rocketphp Dependency Injection Container.
 */
interface ContainerInterface
{
    /**
     * Bind an abstract type to a concrete implementation.
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void;

    /**
     * Register a shared binding (singleton) in the container.
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void;

    /**
     * Register a scoped binding in the container.
     * Scoped bindings act like singletons within a request cycle and are purged at cycle end.
     */
    public function scoped(string $abstract, Closure|string|null $concrete = null): void;

    /**
     * Register an existing instance as shared in the container.
     */
    public function instance(string $abstract, mixed $instance): void;

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Call the given Closure / class@method / [Class, Method] and inject its dependencies.
     *
     * @param callable|array|string $callback
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed;

    /**
     * Determine if the given abstract type has been bound.
     */
    public function bound(string $abstract): bool;

    /**
     * Determine if a given type has been resolved.
     */
    public function resolved(string $abstract): bool;

    /**
     * Flush all request-scoped instances from the container.
     */
    public function flushScoped(): void;

    /**
     * Flush the entire container state.
     */
    public function flush(): void;
}
