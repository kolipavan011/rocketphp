<?php

declare(strict_types=1);

namespace Rocket\Facades;

use Rocket\Foundation\Vite as ViteCore;

/**
 * Class Vite
 *
 * Facade for the Rocketphp Vite asset integration.
 *
 * @method static string __invoke(array|string $entrypoints, ?string $buildDirectory = null)
 * @method static string asset(string $asset, ?string $buildDirectory = null)
 * @method static bool isRunningHot()
 * @method static string getHotUrl()
 * @method static array getManifest(string $buildDirectory = 'build')
 * @method static ViteCore useBuildDirectory(string $directory)
 * @method static ViteCore useHotFile(string $path)
 */
class Vite extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'vite';
    }
}

