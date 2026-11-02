<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;
use Rocket\Foundation\Config;

class ConfigCacheCommand extends Command
{
    protected string $name = 'config:cache';
    protected string $description = 'Combine and compile all configuration files into a single cache';

    public function handle(array $arguments, array $options): int
    {
        /** @var Config $config */
        $config = $this->app->make('config');
        $all = $config->all();

        $cacheDir = $this->app->basePath('bootstrap/cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'config.php';
        $export = var_export($all, true);
        $content = "<?php\n\n// Auto-generated Rocketphp Config Cache\nreturn {$export};\n";

        file_put_contents($cacheFile, $content, LOCK_EX);

        $this->success("Configuration compiled and cached successfully at bootstrap/cache/config.php");

        return 0;
    }
}

