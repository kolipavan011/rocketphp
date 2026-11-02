<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;
use Rocket\Routing\Router;

class RouteCacheCommand extends Command
{
    protected string $name = 'route:cache';
    protected string $description = 'Compile routes to a static cached file for maximum speed';

    public function handle(array $arguments, array $options): int
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $compiled = $router->compile();

        $cacheDir = $this->app->basePath('bootstrap/cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'routes.php';
        $export = var_export($compiled, true);
        $content = "<?php\n\n// Auto-generated Rocketphp Route Cache\nreturn {$export};\n";

        file_put_contents($cacheFile, $content, LOCK_EX);

        $this->success("Routes compiled and cached successfully at bootstrap/cache/routes.php");

        return 0;
    }
}

