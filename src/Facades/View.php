<?php

declare(strict_types=1);

namespace Rocket\Facades;

use Rocket\Http\Response;

/**
 * Class View
 *
 * Facade for the Rocketphp View Engine.
 *
 * @method static string render(string $view, array $data = [])
 * @method static Response make(string $view, array $data = [], int $status = 200, array $headers = [])
 * @method static void share(string|array $key, mixed $value = null)
 * @method static void addPath(string $path, ?string $namespace = null)
 */
class View extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'view';
    }
}

