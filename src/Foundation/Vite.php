<?php

declare(strict_types=1);

namespace Rocket\Foundation;

use RuntimeException;

/**
 * Class Vite
 *
 * High-performance Vite asset loader for development HMR and production manifests.
 */
class Vite
{
    protected Application $app;
    protected string $buildDirectory = 'build';
    protected ?string $hotFile = null;
    protected ?string $publicPath = null;
    protected array $manifests = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Set a custom public path for assets and manifests.
     */
    public function usePublicPath(string $path): static
    {
        $this->publicPath = rtrim($path, '/\\');
        return $this;
    }

    public function getPublicPath(string $subpath = ''): string
    {
        $base = $this->publicPath ?? $this->app->publicPath();
        return $base . ($subpath !== '' ? DIRECTORY_SEPARATOR . ltrim($subpath, '/\\') : '');
    }

    /**
     * Generate script and link tags for the specified entrypoints.
     */
    public function __invoke(array|string $entrypoints, ?string $buildDirectory = null): string
    {
        $entries = is_array($entrypoints) ? $entrypoints : [$entrypoints];
        $buildDir = $buildDirectory ?? $this->buildDirectory;

        if ($this->isRunningHot()) {
            return $this->renderHotTags($entries);
        }

        return $this->renderManifestTags($entries, $buildDir);
    }

    /**
     * Determine if the Vite development server is currently running.
     */
    public function isRunningHot(): bool
    {
        return file_exists($this->getHotFile());
    }

    /**
     * Get the hot reload file path.
     */
    public function getHotFile(): string
    {
        return $this->hotFile ?? $this->getPublicPath('hot');
    }

    /**
     * Set a custom hot file path.
     */
    public function useHotFile(string $path): static
    {
        $this->hotFile = $path;
        return $this;
    }

    /**
     * Set default build directory.
     */
    public function useBuildDirectory(string $directory): static
    {
        $this->buildDirectory = trim($directory, '/\\');
        return $this;
    }

    /**
     * Get the URL of the running Vite dev server.
     */
    public function getHotUrl(): string
    {
        $url = trim((string) @file_get_contents($this->getHotFile()));
        return rtrim($url, '/');
    }

    /**
     * Render development server tags including HMR client.
     */
    protected function renderHotTags(array $entries): string
    {
        $hotUrl = $this->getHotUrl();
        $tags = [
            sprintf('<script type="module" src="%s/@vite/client"></script>', $hotUrl),
        ];

        foreach ($entries as $entry) {
            $url = $hotUrl . '/' . ltrim($entry, '/');
            if ($this->isCss($entry)) {
                $tags[] = sprintf('<link rel="stylesheet" href="%s">', $url);
            } else {
                $tags[] = sprintf('<script type="module" src="%s"></script>', $url);
            }
        }

        return implode("\n    ", $tags);
    }

    /**
     * Render production tags from compiled Vite manifest.
     */
    protected function renderManifestTags(array $entries, string $buildDirectory): string
    {
        $manifest = $this->getManifest($buildDirectory);
        $tags = [];
        $loadedCss = [];

        foreach ($entries as $entry) {
            $normalized = ltrim($entry, '/');

            if (!isset($manifest[$normalized])) {
                continue;
            }

            $chunk = $manifest[$normalized];

            // 1. Output any associated CSS files
            if (isset($chunk['css']) && is_array($chunk['css'])) {
                foreach ($chunk['css'] as $cssFile) {
                    if (!isset($loadedCss[$cssFile])) {
                        $loadedCss[$cssFile] = true;
                        $tags[] = sprintf(
                            '<link rel="stylesheet" href="/%s/%s">',
                            $buildDirectory,
                            $cssFile
                        );
                    }
                }
            }

            // 2. Output the entry file itself
            $file = $chunk['file'];
            if ($this->isCss($file)) {
                if (!isset($loadedCss[$file])) {
                    $loadedCss[$file] = true;
                    $tags[] = sprintf(
                        '<link rel="stylesheet" href="/%s/%s">',
                        $buildDirectory,
                        $file
                    );
                }
            } else {
                $tags[] = sprintf(
                    '<script type="module" src="/%s/%s"></script>',
                    $buildDirectory,
                    $file
                );
            }
        }

        return implode("\n    ", $tags);
    }

    /**
     * Get and parse the Vite manifest.
     */
    public function getManifest(string $buildDirectory = 'build'): array
    {
        if (isset($this->manifests[$buildDirectory])) {
            return $this->manifests[$buildDirectory];
        }

        $candidates = [
            $this->getPublicPath("{$buildDirectory}/manifest.json"),
            $this->getPublicPath("{$buildDirectory}/.vite/manifest.json"),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $content = file_get_contents($candidate);
                $decoded = json_decode((string) $content, true);
                if (is_array($decoded)) {
                    return $this->manifests[$buildDirectory] = $decoded;
                }
            }
        }

        return [];
    }

    /**
     * Get the URL for an individual asset in the manifest or dev server.
     */
    public function asset(string $asset, ?string $buildDirectory = null): string
    {
        $buildDir = $buildDirectory ?? $this->buildDirectory;

        if ($this->isRunningHot()) {
            return $this->getHotUrl() . '/' . ltrim($asset, '/');
        }

        $manifest = $this->getManifest($buildDir);
        $normalized = ltrim($asset, '/');

        if (isset($manifest[$normalized]['file'])) {
            return "/{$buildDir}/" . $manifest[$normalized]['file'];
        }

        return "/{$buildDir}/" . $normalized;
    }

    protected function isCss(string $path): bool
    {
        return (bool) preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)$/i', $path);
    }
}
