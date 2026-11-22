<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class StorageUnlinkCommand extends Command
{
    protected string $name = 'storage:unlink';
    protected string $description = 'Delete the symbolic link "public/storage"';

    public function handle(array $arguments, array $options): int
    {
        $links = $this->app->bound('config')
            ? ($this->app->make('config')->get('filesystems.links') ?? [])
            : [];

        if (empty($links)) {
            $links = [
                $this->app->publicPath('storage') => $this->app->storagePath('public'),
            ];
        }

        foreach (array_keys($links) as $link) {
            $formattedLink = $this->formatPath($link);

            if (!$this->linkExists($link)) {
                $this->info("The [{$formattedLink}] link does not exist.");
                continue;
            }

            if ($this->removeLink($link)) {
                $this->success("The [{$formattedLink}] link has been deleted.");
            } else {
                $this->error("Failed to delete the [{$formattedLink}] link.");
            }
        }

        return 0;
    }

    protected function linkExists(string $link): bool
    {
        if (is_link($link)) {
            return true;
        }

        if (file_exists($link)) {
            return true;
        }

        if (PHP_OS_FAMILY === 'Windows' && @readlink($link) !== false) {
            return true;
        }

        return false;
    }

    protected function removeLink(string $link): bool
    {
        clearstatcache(true, $link);

        if (PHP_OS_FAMILY === 'Windows') {
            if (@rmdir($link) || @unlink($link)) {
                clearstatcache(true, $link);
                return true;
            }

            if ($this->linkExists($link)) {
                @exec(sprintf('cmd /c rmdir "%s"', $link), $output, $code);
                clearstatcache(true, $link);
                return !$this->linkExists($link);
            }

            return true;
        }

        $result = @unlink($link);
        clearstatcache(true, $link);
        return $result;
    }

    protected function formatPath(string $path): string
    {
        $base = $this->app->basePath();
        if (str_starts_with($path, $base)) {
            return ltrim(str_replace('\\', '/', substr($path, strlen($base))), '/');
        }

        return str_replace('\\', '/', $path);
    }
}
