<?php

declare(strict_types=1);

namespace Rocket\Database;

/**
 * Class Raw
 *
 * Represents an explicit raw SQL expression.
 */
class Raw
{
    protected string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

