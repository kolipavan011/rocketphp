<?php

declare(strict_types=1);

namespace Rocket\View;

use Rocket\View\Contracts\HtmlableInterface;
use Stringable;

/**
 * Class ComponentSlot
 *
 * Represents rendered content passed into a component slot, along with slot attributes.
 */
class ComponentSlot implements Stringable, HtmlableInterface
{
    /**
     * Captured slot HTML content.
     */
    protected string $contents;

    /**
     * Slot attributes.
     */
    public ComponentAttributeBag $attributes;

    public function __construct(string $contents = '', array $attributes = [])
    {
        $this->contents = $contents;
        $this->attributes = new ComponentAttributeBag($attributes);
    }

    /**
     * Get the slot HTML string.
     */
    public function toHtml(): string
    {
        return $this->contents;
    }

    /**
     * Check if slot has no content or only whitespace.
     */
    public function isEmpty(): bool
    {
        return trim($this->contents) === '';
    }

    /**
     * Check if slot has non-whitespace content.
     */
    public function isNotEmpty(): bool
    {
        return trim($this->contents) !== '';
    }

    /**
     * Check if slot has non-whitespace content (alias for isNotEmpty).
     */
    public function hasActualContent(): bool
    {
        return $this->isNotEmpty();
    }

    public function __toString(): string
    {
        return $this->contents;
    }
}
