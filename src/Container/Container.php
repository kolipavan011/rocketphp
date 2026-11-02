<?php

declare(strict_types=1);

namespace Rocket\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Class Container
 *
 * High-performance, lightweight Dependency Injection Container with
 * reflection caching, autowiring, and scoped lifecycle management.
 */
class Container implements ContainerInterface
{
    /**
     * The globally available container instance.
     */
    protected static ?Container $instance = null;

    /**
     * Registered bindings.
     *
     * @var array<string, array{concrete: Closure|string, shared: bool, scoped: bool}>
     */
    protected array $bindings = [];

    /**
     * Shared singleton instances.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Request-scoped instances.
     *
     * @var array<string, object>
     */
    protected array $scopedInstances = [];

    /**
     * Registered type aliases.
     *
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Static cache of reflected constructor parameters to avoid repeated reflection.
     *
     * @var array<string, array<int, array{name: string, type: ?string, isDefault: bool, default: mixed, allowsNull: bool}>>
     */
    protected static array $constructorCache = [];

    /**
     * Static cache of reflected method parameters.
     *
     * @var array<string, array<int, array{name: string, type: ?string, isDefault: bool, default: mixed, allowsNull: bool}>>
     */
    protected static array $methodCache = [];

    /**
     * Get the globally available container instance.
     */
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Set the globally available container instance.
     */
    public static function setInstance(?Container $container = null): ?static
    {
        return static::$instance = $container;
    }

    /**
     * Bind an abstract type to a concrete implementation.
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $abstract = $this->normalize($abstract);
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared'   => $shared,
            'scoped'   => false,
        ];
    }

    /**
     * Register a shared binding (singleton) in the container.
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $abstract = $this->normalize($abstract);
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared'   => true,
            'scoped'   => false,
        ];
    }

    /**
     * Register a scoped binding in the container.
     */
    public function scoped(string $abstract, Closure|string|null $concrete = null): void
    {
        $abstract = $this->normalize($abstract);
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared'   => false,
            'scoped'   => true,
        ];
    }

    /**
     * Register an existing instance as shared in the container.
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $abstract = $this->normalize($abstract);
        $this->instances[$abstract] = $instance;
    }

    /**
     * Register an alias for an abstract type.
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $this->normalize($abstract);
    }

    /**
     * Determine if the given abstract type has been bound.
     */
    public function bound(string $abstract): bool
    {
        $abstract = $this->normalize($abstract);
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract])
            || isset($this->scopedInstances[$abstract]);
    }

    /**
     * Determine if a given type has been resolved.
     */
    public function resolved(string $abstract): bool
    {
        $abstract = $this->normalize($abstract);
        return isset($this->instances[$abstract]) || isset($this->scopedInstances[$abstract]);
    }

    /**
     * Resolve the given type from the container.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->normalize($abstract);

        // Return singleton if already resolved
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Return scoped instance if already resolved
        if (isset($this->scopedInstances[$abstract])) {
            return $this->scopedInstances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete'] ?? $abstract;

        if ($concrete instanceof Closure) {
            $object = $concrete($this, $parameters);
        } elseif ($concrete === $abstract || is_string($concrete)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $concrete;
        }

        // Store singleton instance
        if ($binding !== null && $binding['shared']) {
            $this->instances[$abstract] = $object;
        }

        // Store scoped instance
        if ($binding !== null && $binding['scoped']) {
            $this->scopedInstances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Build an instance of the given concrete class using autowiring and reflection cache.
     */
    protected function build(string $concrete, array $parameters = []): object
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new NotFoundException("Target class [{$concrete}] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Target class [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveMethodDependencies($concrete, $constructor, $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies with static reflection caching.
     */
    protected function resolveMethodDependencies(string $class, ReflectionMethod $constructor, array $parameters): array
    {
        if (!isset(self::$constructorCache[$class])) {
            self::$constructorCache[$class] = $this->extractParameterMetadata($constructor);
        }

        return $this->matchDependencies(self::$constructorCache[$class], $parameters);
    }

    /**
     * Extract parameter metadata for caching.
     */
    protected function extractParameterMetadata(ReflectionFunctionAbstract $reflection): array
    {
        $metadata = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            $typeName = null;

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
            }

            $metadata[] = [
                'name'       => $param->getName(),
                'type'       => $typeName,
                'isDefault'  => $param->isDefaultValueAvailable(),
                'default'    => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'allowsNull' => $param->allowsNull(),
            ];
        }

        return $metadata;
    }

    /**
     * Match dependencies against given parameters and container resolutions.
     */
    protected function matchDependencies(array $metadataList, array $parameters): array
    {
        $dependencies = [];

        foreach ($metadataList as $index => $meta) {
            $name = $meta['name'];

            // 1. Check positional override
            if (array_key_exists($index, $parameters)) {
                $dependencies[] = $parameters[$index];
                continue;
            }

            // 2. Check named override
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // 3. Resolve class dependency from container
            if ($meta['type'] !== null) {
                try {
                    $dependencies[] = $this->make($meta['type']);
                    continue;
                } catch (NotFoundException | ContainerException $e) {
                    if ($meta['allowsNull']) {
                        $dependencies[] = null;
                        continue;
                    }
                    if ($meta['isDefault']) {
                        $dependencies[] = $meta['default'];
                        continue;
                    }
                    throw $e;
                }
            }

            // 4. Default value
            if ($meta['isDefault']) {
                $dependencies[] = $meta['default'];
                continue;
            }

            // 5. Nullable primitive
            if ($meta['allowsNull']) {
                $dependencies[] = null;
                continue;
            }

            throw new ContainerException("Unresolvable dependency [{$name}] cannot be resolved.");
        }

        return $dependencies;
    }

    /**
     * Call the given Closure / class@method / [Class, Method] and inject its dependencies.
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            $callback = explode('@', $callback, 2);
        }

        if (is_array($callback)) {
            [$class, $method] = $callback;
            $instance = is_object($class) ? $class : $this->make($class);
            $reflector = new ReflectionMethod($instance, $method);

            $cacheKey = get_class($instance) . '::' . $method;
            if (!isset(self::$methodCache[$cacheKey])) {
                self::$methodCache[$cacheKey] = $this->extractParameterMetadata($reflector);
            }

            $dependencies = $this->matchDependencies(self::$methodCache[$cacheKey], $parameters);
            return $reflector->invokeArgs($instance, $dependencies);
        }

        if ($callback instanceof Closure || is_callable($callback)) {
            $reflector = new ReflectionFunction(Closure::fromCallable($callback));
            $dependencies = $this->matchDependencies($this->extractParameterMetadata($reflector), $parameters);
            return $callback(...$dependencies);
        }

        throw new ContainerException("Given callback is not callable.");
    }

    /**
     * Normalize an abstract type identifier.
     */
    protected function normalize(string $abstract): string
    {
        $trimmed = ltrim($abstract, '\\');
        return $this->aliases[$trimmed] ?? $trimmed;
    }

    /**
     * Flush all request-scoped instances from the container.
     */
    public function flushScoped(): void
    {
        $this->scopedInstances = [];
    }

    /**
     * Flush the entire container state.
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->scopedInstances = [];
        $this->aliases = [];
        self::$constructorCache = [];
        self::$methodCache = [];
    }
}
