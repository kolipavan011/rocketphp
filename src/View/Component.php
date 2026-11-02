<?php

declare(strict_types=1);

namespace Rocket\View;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Class Component
 *
 * Base class for all class-based view components.
 */
abstract class Component
{
    /**
     * Component attributes bag.
     */
    public ComponentAttributeBag $attributes;

    /**
     * Component default slot.
     */
    public ComponentSlot $slot;

    public function __construct()
    {
        $this->attributes = new ComponentAttributeBag();
        $this->slot = new ComponentSlot();
    }

    /**
     * Get the view name or HTML string that represents the component.
     */
    abstract public function render(): string|object;

    /**
     * Determine if the component should be rendered.
     */
    public function shouldRender(): bool
    {
        return true;
    }

    /**
     * Set the component attributes.
     */
    public function withAttributes(array $attributes): static
    {
        $this->attributes = new ComponentAttributeBag($attributes);
        return $this;
    }

    /**
     * Extract public properties and methods to provide to the view.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $data = [];
        $reflector = new ReflectionClass($this);

        // Extract public non-static properties
        foreach ($reflector->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic() && !in_array($property->getName(), ['attributes', 'slot'])) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        // Extract public non-static methods
        $ignoredMethods = [
            '__construct', 'render', 'shouldRender', 'withAttributes', 'data',
        ];

        foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                !$method->isStatic()
                && !in_array($method->getName(), $ignoredMethods)
                && !str_starts_with($method->getName(), '__')
            ) {
                $data[$method->getName()] = Closure::fromCallable([$this, $method->getName()]);
            }
        }

        return $data;
    }
}

