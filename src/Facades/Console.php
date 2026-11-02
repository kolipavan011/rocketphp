<?php

declare(strict_types=1);

namespace Rocket\Facades;

use Closure;
use Rocket\Console\ClosureCommand;
use Rocket\Console\Command;

/**
 * Class Console
 *
 * Facade for the Rocketphp Console Application.
 *
 * @method static ClosureCommand command(string $signature, Closure $callback)
 * @method static void add(Command $command)
 * @method static int call(string $command, array $arguments = [], array $options = [])
 */
class Console extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'console';
    }
}

