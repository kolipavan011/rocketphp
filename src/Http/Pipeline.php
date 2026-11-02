<?php

declare(strict_types=1);

namespace Rocket\Http;

use Closure;
use Rocket\Container\ContainerInterface;
use Rocket\Http\Contracts\MiddlewareInterface;
use InvalidArgumentException;

/**
 * Class Pipeline
 *
 * Ultra-fast array-reduced onion pipeline for HTTP middleware execution.
 */
class Pipeline
{
    protected ?ContainerInterface $container;
    protected mixed $passable;
    protected array $pipes = [];

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Set the object being passed through the pipeline (e.g. Request).
     */
    public function send(mixed $passable): static
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * Set the array of pipes (middlewares).
     */
    public function through(array $pipes): static
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Run the pipeline with a final destination callback.
     */
    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    /**
     * Get a Closure that represents a slice of the pipeline onion.
     */
    protected function carry(): Closure
    {
        return function (Closure $stack, mixed $pipe): Closure {
            return function (mixed $passable) use ($stack, $pipe) {
                if ($pipe instanceof Closure) {
                    return $pipe($passable, $stack);
                }

                if (is_string($pipe)) {
                    $pipeInstance = $this->resolvePipe($pipe);

                    if ($pipeInstance instanceof MiddlewareInterface) {
                        return $pipeInstance->handle($passable, $stack);
                    }

                    if (method_exists($pipeInstance, 'handle')) {
                        return $pipeInstance->handle($passable, $stack);
                    }
                }

                if (is_object($pipe) && method_exists($pipe, 'handle')) {
                    return $pipe->handle($passable, $stack);
                }

                throw new InvalidArgumentException(
                    "Middleware pipe must implement MiddlewareInterface or have a handle() method."
                );
            };
        };
    }

    /**
     * Prepare the final destination closure.
     */
    protected function prepareDestination(Closure $destination): Closure
    {
        return function (mixed $passable) use ($destination) {
            return $destination($passable);
        };
    }

    /**
     * Resolve a middleware pipe from the container or direct instantiation.
     */
    protected function resolvePipe(string $pipe): object
    {
        if ($this->container !== null) {
            return $this->container->make($pipe);
        }

        return new $pipe();
    }
}

