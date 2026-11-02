<?php

declare(strict_types=1);

namespace Rocket\Console;

use Closure;
use Rocket\Foundation\Application;

/**
 * Class ClosureCommand
 *
 * Allows registering console commands dynamically using Closures.
 */
class ClosureCommand extends Command
{
    protected Closure $callback;

    public function __construct(Application $app, string $name, Closure $callback)
    {
        parent::__construct($app);
        $this->name = $name;
        $this->callback = $callback;
    }

    public function describe(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function handle(array $arguments, array $options): int
    {
        $bound = $this->callback->bindTo($this, $this);
        $result = $bound($arguments, $options);

        return is_int($result) ? $result : 0;
    }
}

