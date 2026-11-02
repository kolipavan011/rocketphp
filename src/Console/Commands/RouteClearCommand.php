<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class RouteClearCommand extends Command
{
    protected string $name = 'route:clear';
    protected string $description = 'Remove the route cache file';

    public function handle(array $arguments, array $options): int
    {
        $cacheFile = $this->app->basePath('bootstrap/cache/routes.php');

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $this->success("Route cache cleared!");
        } else {
            $this->line("No route cache found to clear.");
        }

        return 0;
    }
}

