<?php

declare(strict_types=1);

namespace Rocket\Database\Schema;

/**
 * Class ColumnDefinition
 *
 * Represents an individual column in a schema blueprint with fluent modifiers.
 */
class ColumnDefinition
{
    public string $name;
    public string $type;
    public ?int $length = null;
    public bool $nullable = false;
    public mixed $default = null;
    public bool $hasDefault = false;
    public bool $unique = false;
    public bool $primary = false;
    public bool $autoIncrement = false;

    public function __construct(string $name, string $type, ?int $length = null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->length = $length;
    }

    public function nullable(): static
    {
        $this->nullable = true;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->hasDefault = true;
        $this->default = $value;
        return $this;
    }

    public function unique(): static
    {
        $this->unique = true;
        return $this;
    }

    public function primary(): static
    {
        $this->primary = true;
        return $this;
    }

    public function autoIncrement(): static
    {
        $this->autoIncrement = true;
        return $this;
    }
}

