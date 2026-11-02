<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class CacheClearCommand extends Command
{
    protected string $name = 'cache:clear';
    protected string $description = 'Flush application, view, and route caches';

    public function handle(array $arguments, array $options): int
    {
        $viewCache = $this->app->storagePath('cache/views');
        $this->clearDirectory($viewCache);

        $dataCache = $this->app->storagePath('cache/data');
        $this->clearDirectory($dataCache);

        $this->success("Application cache cleared successfully!");

        return 0;
    }

    protected function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->clearDirectory($path) : unlink($path);
        }
    }
}

