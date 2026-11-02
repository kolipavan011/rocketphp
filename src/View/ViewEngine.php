<?php

declare(strict_types=1);

namespace Rocket\View;

use Rocket\Http\Response;
use Rocket\View\Contracts\CompilerInterface;
use Rocket\View\Contracts\ViewEngineInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Class ViewEngine
 *
 * Lightweight Blade-compatible View Rendering Engine.
 */
class ViewEngine implements ViewEngineInterface
{
    /**
     * View compiler.
     */
    protected CompilerInterface $compiler;

    /**
     * Root directory paths to search for templates.
     *
     * @var array<int, string>
     */
    protected array $paths = [];

    /**
     * Namespaced view paths (for modular packages or service providers).
     *
     * @var array<string, string>
     */
    protected array $namespaces = [];

    /**
     * Shared global data across all views.
     *
     * @var array<string, mixed>
     */
    protected array $shared = [];

    /**
     * Layout extended by the current template.
     */
    protected ?string $layout = null;

    /**
     * Captured section contents.
     *
     * @var array<string, string>
     */
    protected array $sections = [];

    /**
     * Stack of currently open section names.
     *
     * @var array<int, string>
     */
    protected array $sectionStack = [];

    public function __construct(CompilerInterface $compiler, array $paths = [])
    {
        $this->compiler = $compiler;
        foreach ($paths as $path) {
            $this->addPath($path);
        }
    }

    /**
     * Add a view directory path.
     */
    public function addPath(string $path, ?string $namespace = null): void
    {
        $normalized = rtrim($path, '/\\');
        if ($namespace !== null) {
            $this->namespaces[$namespace] = $normalized;
        } else {
            $this->paths[] = $normalized;
        }
    }

    /**
     * Share data across all rendered views.
     */
    public function share(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->shared = array_merge($this->shared, $key);
        } else {
            $this->shared[$key] = $value;
        }
    }

    /**
     * Render a template and return the raw output.
     */
    public function render(string $view, array $data = []): string
    {
        $filePath = $this->findViewFile($view);
        $compiledPath = $this->compiler->compile($filePath);

        $mergedData = array_merge($this->shared, $data);

        $content = $this->evaluatePath($compiledPath, $mergedData);

        // If template declared an @extends layout, render the parent layout
        if ($this->layout !== null) {
            $layoutView = $this->layout;
            $this->layout = null; // Prevent infinite loops
            return $this->render($layoutView, $mergedData);
        }

        // Clean up after top-level render completes
        $this->sections = [];
        $this->sectionStack = [];

        return $content;
    }

    /**
     * Render a partial view from within a template (for @include).
     */
    public function renderPartial(string $view, array $data = []): string
    {
        $filePath = $this->findViewFile($view);
        $compiledPath = $this->compiler->compile($filePath);
        $mergedData = array_merge($this->shared, $data);

        return $this->evaluatePath($compiledPath, $mergedData);
    }

    /**
     * Render a template and return an HTTP Response.
     */
    public function make(string $view, array $data = [], int $status = 200, array $headers = []): Response
    {
        $content = $this->render($view, $data);
        return new Response($content, $status, $headers);
    }

    /**
     * Mark an extended layout.
     */
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Start capturing output buffer for a section.
     */
    public function startSection(string $section): void
    {
        $this->sectionStack[] = $section;
        ob_start();
    }

    /**
     * Stop capturing output buffer and store section content.
     */
    public function endSection(): void
    {
        if (empty($this->sectionStack)) {
            throw new InvalidArgumentException("Cannot end a section without an active open section.");
        }

        $lastSection = array_pop($this->sectionStack);
        $this->sections[$lastSection] = ob_get_clean();
    }

    /**
     * Output content of a section or return default value.
     */
    public function yieldContent(string $section, string $default = ''): string
    {
        return $this->sections[$section] ?? $default;
    }

    /**
     * Evaluate compiled template file in isolated scope.
     */
    protected function evaluatePath(string $__compiledPath, array $__data): string
    {
        $obLevel = ob_get_level();
        ob_start();

        extract($__data, EXTR_SKIP);

        try {
            include $__compiledPath;
        } catch (Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            throw $e;
        }

        return ob_get_clean() ?: '';
    }

    /**
     * Locate template file across paths and namespaces.
     */
    protected function findViewFile(string $view): string
    {
        $normalizedView = str_replace('.', DIRECTORY_SEPARATOR, $view);

        // Check for namespaced view: "package::view.name"
        if (str_contains($view, '::')) {
            [$namespace, $relative] = explode('::', $view, 2);
            $basePath = $this->namespaces[$namespace] ?? null;
            if ($basePath === null) {
                throw new InvalidArgumentException("View namespace [{$namespace}] not registered.");
            }
            $cleanRelative = str_replace('.', DIRECTORY_SEPARATOR, $relative);
            return $this->resolvePath($basePath, $cleanRelative);
        }

        foreach ($this->paths as $path) {
            $resolved = $this->resolvePath($path, $normalizedView);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        throw new InvalidArgumentException("View [{$view}] not found.");
    }

    /**
     * Resolve template file extension.
     */
    protected function resolvePath(string $baseDir, string $relative): ?string
    {
        $extensions = ['.rocket.php', '.blade.php', '.php', '.html'];
        foreach ($extensions as $ext) {
            $candidate = $baseDir . DIRECTORY_SEPARATOR . $relative . $ext;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    public function getCompiler(): CompilerInterface
    {
        return $this->compiler;
    }
}
