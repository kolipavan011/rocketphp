<?php

declare(strict_types=1);

namespace Rocket\Routing;

/**
 * Class ControllerMiddlewareOptions
 *
 * Fluent helper for attaching and scoping controller middleware.
 */
class ControllerMiddlewareOptions
{
    protected array $options;

    public function __construct(array &$options)
    {
        $this->options = &$options;
    }

    /**
     * Apply middleware only to specific actions.
     */
    public function only(string ...$methods): static
    {
        $this->options['only'] = $methods;
        return $this;
    }

    /**
     * Apply middleware except for specific actions.
     */
    public function except(string ...$methods): static
    {
        $this->options['except'] = $methods;
        return $this;
    }
}

