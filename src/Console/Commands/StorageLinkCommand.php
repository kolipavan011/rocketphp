<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;
use Throwable;

class StorageLinkCommand extends Command
{
    protected string $name = 'storage:link';
    protected string $description = 'Create the symbolic link from "storage/public" to "public/storage"';

    public function handle(array $arguments, array $options): int
    {
        $force = isset($options['force'])
            || isset($options['--force'])
            || isset($arguments['force'])
            || isset($arguments['--force'])
            || in_array('--force', $arguments, true)
            || in_array('-f', $arguments, true);

        $relative = isset($options['relative'])
            || isset($options['--relative'])
            || isset($arguments['relative'])
            || isset($arguments['--relative'])
            || in_array('--relative', $arguments, true)
            || in_array('-r', $arguments, true);

        // Get configured links or default to public/storage => storage/public
        $links = $this->app->bound('config')
            ? ($this->app->make('config')->get('filesystems.links') ?? [])
            : [];

        if (empty($links)) {
            $links = [
                $this->app->publicPath('storage') => $this->app->storagePath('public'),
            ];
        }

        $hasError = false;

        foreach ($links as $link => $target) {
            // Ensure target directory exists
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
                if (!file_exists($target . DIRECTORY_SEPARATOR . '.gitkeep')) {
                    file_put_contents($target . DIRECTORY_SEPARATOR . '.gitkeep', '');
                }
            }

            // Check if link already exists
            if ($this->linkExists($link)) {
                if (!$force) {
                    $this->error("The [{$this->formatPath($link)}] link already exists.");
                    $hasError = true;
                    continue;
                }

                if (!$this->removeLink($link)) {
                    $this->error("Failed to remove existing link [{$this->formatPath($link)}].");
                    $hasError = true;
                    continue;
                }
            }

            // Create symbolic link / junction
            if ($this->createLink($target, $link, $relative)) {
                $this->success("The [{$this->formatPath($link)}] link has been connected to [{$this->formatPath($target)}].");
            } else {
                $this->error("Failed to create symbolic link [{$this->formatPath($link)}].");
                $hasError = true;
            }
        }

        return $hasError ? 1 : 0;
    }

    /**
     * Determine if a link or file exists at the given path.
     */
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

    /**
     * Create the symbolic link or Windows directory junction.
     */
    protected function createLink(string $target, string $link, bool $relative): bool
    {
        // Ensure parent directory of link exists
        $parentDir = dirname($link);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        // On Windows: try native symlink first, then fallback to directory junction
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                if (@symlink($target, $link)) {
                    return true;
                }
            } catch (Throwable) {
                // Ignore and fallback to junction
            }

            if (is_dir($target)) {
                $command = sprintf('cmd /c mklink /J "%s" "%s"', $link, $target);
                @exec($command, $output, $resultCode);
                return $resultCode === 0 || is_dir($link);
            }

            return false;
        }

        // On Unix / Linux / macOS
        $linkTarget = $target;
        if ($relative) {
            $linkTarget = $this->getRelativePath(dirname($link), $target);
        }

        return @symlink($linkTarget, $link);
    }

    /**
     * Remove an existing link or directory junction safely.
     */
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

    /**
     * Calculate relative path from one directory to target.
     */
    protected function getRelativePath(string $from, string $to): string
    {
        $from = str_replace('\\', '/', $from);
        $to = str_replace('\\', '/', $to);

        $fromParts = explode('/', trim($from, '/'));
        $toParts = explode('/', trim($to, '/'));

        while (count($fromParts) && count($toParts) && ($fromParts[0] === $toParts[0])) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        return str_repeat('../', count($fromParts)) . implode('/', $toParts);
    }

    /**
     * Format an absolute path into an application-relative path for cleaner output.
     */
    protected function formatPath(string $path): string
    {
        $base = $this->app->basePath();
        if (str_starts_with($path, $base)) {
            return ltrim(str_replace('\\', '/', substr($path, strlen($base))), '/');
        }

        return str_replace('\\', '/', $path);
    }
}
