<?php

declare(strict_types=1);

namespace Rocket\Facades;

use Rocket\Container\Container;
use RuntimeException;

/**
 * Class Facade
 *
 * Base class for static facade accessors.
 */
abstract class Facade
{
    /**
     * Get the registered name of the component.
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Handle dynamic, static calls to the object.
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = Container::getInstance()->make(static::getFacadeAccessor());

        if (!is_object($instance)) {
            throw new RuntimeException("A facade root has not been set for [" . static::getFacadeAccessor() . "].");
        }

        return $instance->$method(...$args);
    }
}

