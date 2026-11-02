<?php

declare(strict_types=1);

namespace Rocket\View;

use Rocket\View\Contracts\HtmlableInterface;
use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;
use Stringable;

/**
 * Class ComponentAttributeBag
 *
 * Manages HTML attributes passed to a view component.
 * Provides fluent merging, conditional classes, and safe HTML rendering.
 */
class ComponentAttributeBag implements ArrayAccess, Countable, IteratorAggregate, Stringable, HtmlableInterface
{
    /**
     * The raw attribute key-value pairs.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Get an attribute value with an optional default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Determine if an attribute is set.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Retrieve the first attribute from the bag.
     */
    public function first(callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($this->attributes) ? $default : reset($this->attributes);
        }

        foreach ($this->attributes as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Return a new bag containing only the specified keys.
     */
    public function only(string|array ...$keys): static
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;
        $filtered = array_intersect_key($this->attributes, array_flip((array) $keys));

        return new static($filtered);
    }

    /**
     * Return a new bag without the specified keys.
     */
    public function except(string|array ...$keys): static
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;
        $filtered = array_diff_key($this->attributes, array_flip((array) $keys));

        return new static($filtered);
    }

    /**
     * Merge default attributes into the bag.
     * If both have 'class', their values are intelligently concatenated.
     * The passed attributes take precedence over defaults.
     */
    public function merge(array $defaultAttributes = []): static
    {
        $attributes = $this->attributes;

        if (isset($defaultAttributes['class']) && isset($attributes['class'])) {
            $attributes['class'] = trim($defaultAttributes['class'] . ' ' . $attributes['class']);
            unset($defaultAttributes['class']);
        }

        $merged = array_merge($defaultAttributes, $attributes);

        return new static($merged);
    }

    /**
     * Conditionally compile CSS classes and merge them into the 'class' attribute.
     *
     * Example: $attributes->class(['btn', 'btn-primary' => $isPrimary])
     */
    public function class(array|string $classesList): static
    {
        $classes = [];

        if (is_string($classesList)) {
            $classes[] = $classesList;
        } elseif (is_array($classesList)) {
            foreach ($classesList as $key => $value) {
                if (is_int($key)) {
                    if (is_string($value) && trim($value) !== '') {
                        $classes[] = trim($value);
                    }
                } elseif ($value) {
                    $classes[] = trim((string) $key);
                }
            }
        }

        $compiledClass = implode(' ', array_filter($classes));
        $existing = isset($this->attributes['class']) ? trim((string) $this->attributes['class']) : '';

        if ($existing !== '') {
            $compiledClass = $existing . ($compiledClass !== '' ? ' ' . $compiledClass : '');
        }

        $merged = array_merge($this->attributes, ['class' => $compiledClass]);

        return new static($merged);
    }

    /**
     * Filter attributes using a callback.
     */
    public function filter(callable $callback): static
    {
        return new static(array_filter($this->attributes, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Filter attributes whose keys start with a specific prefix.
     */
    public function whereStartsWith(string $needle): static
    {
        return $this->filter(fn ($value, $key) => str_starts_with((string) $key, $needle));
    }

    /**
     * Filter attributes whose keys do not start with a specific prefix.
     */
    public function whereDoesntStartWith(string $needle): static
    {
        return $this->filter(fn ($value, $key) => !str_starts_with((string) $key, $needle));
    }

    /**
     * Get all raw attributes.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Render the attributes as an HTML string.
     */
    public function toHtml(): string
    {
        $html = [];

        foreach ($this->attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true) {
                $html[] = htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }

            if (is_array($value)) {
                $value = json_encode($value);
            }

            $escapedName = htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $escapedValue = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $html[] = "{$escapedName}=\"{$escapedValue}\"";
        }

        return implode(' ', $html);
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function count(): int
    {
        return count($this->attributes);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->attributes);
    }
}
