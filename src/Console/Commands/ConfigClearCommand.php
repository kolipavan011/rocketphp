<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class ConfigClearCommand extends Command
{
    protected string $name = 'config:clear';
    protected string $description = 'Remove the configuration cache file';

    public function handle(array $arguments, array $options): int
    {
        $cacheFile = $this->app->basePath('bootstrap/cache/config.php');

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $this->success("Configuration cache cleared!");
        } else {
            $this->line("No configuration cache found to clear.");
        }

        return 0;
    }
}

