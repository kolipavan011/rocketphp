<?php

declare(strict_types=1);

namespace Rocket\View;

use Rocket\View\Contracts\CompilerInterface;
use RuntimeException;

/**
 * Class Compiler
 *
 * High-performance Blade-inspired template compiler with OPcache caching.
 */
class Compiler implements CompilerInterface
{
    /**
     * Directory to store compiled template files.
     */
    protected string $cachePath;

    /**
     * Custom registered directives.
     *
     * @var array<string, callable>
     */
    protected array $customDirectives = [];

    public function __construct(string $cachePath)
    {
        $this->cachePath = rtrim($cachePath, '/\\');
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Register a custom directive.
     */
    public function directive(string $name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
    }

    /**
     * Get compiled cache path for a template file.
     */
    public function getCompiledPath(string $path): string
    {
        return $this->cachePath . DIRECTORY_SEPARATOR . sha1($path) . '.php';
    }

    /**
     * Check if the template needs recompilation.
     */
    public function isExpired(string $path): bool
    {
        $compiled = $this->getCompiledPath($path);

        if (!file_exists($compiled)) {
            return true;
        }

        return filemtime($path) > filemtime($compiled);
    }

    /**
     * Compile template file to disk and return compiled file path.
     */
    public function compile(string $path): string
    {
        $compiled = $this->getCompiledPath($path);

        if ($this->isExpired($path)) {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException("Unable to read template file: {$path}");
            }

            $compiledContents = $this->compileString($contents);
            file_put_contents($compiled, $compiledContents, LOCK_EX);
        }

        return $compiled;
    }

    /**
     * Compile a template string into valid PHP.
     */
    public function compileString(string $value): string
    {
        // 1. Comments {{-- comment --}}
        $value = preg_replace('/\{\{--(.*?)--\}\}/s', '', $value);

        // 2. Custom directives
        foreach ($this->customDirectives as $name => $handler) {
            $value = preg_replace_callback('/@' . preg_quote($name, '/') . '(?:\((.*?)\))?/', function ($matches) use ($handler) {
                $args = $matches[1] ?? '';
                return $handler($args);
            }, $value);
        }

        // 3. Raw echo {!! $var !!}
        $value = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?= $1 ?>', $value);

        // 4. Escaped echo {{ $var }}
        $value = preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<?= htmlspecialchars((string)($1), ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\') ?>',
            $value
        );

        // 5. Template inheritance directives
        $value = preg_replace('/@extends\s*\((.*?)\)/', '<?php $this->extend($1); ?>', $value);
        $value = preg_replace('/@section\s*\((.*?)\)/', '<?php $this->startSection($1); ?>', $value);
        $value = preg_replace('/@endsection/', '<?php $this->endSection(); ?>', $value);
        $value = preg_replace('/@yield\s*\((.*?)\)/', '<?= $this->yieldContent($1); ?>', $value);

        // 6. Component and partial includes
        $value = preg_replace_callback('/@include\s*\((.*?)\)/', function ($matches) {
            return '<?= $this->renderPartial(' . $matches[1] . ', $__data); ?>';
        }, $value);

        // 7. Security directives
        $value = preg_replace('/@csrf/', '<?= csrf_field(); ?>', $value);
        $value = preg_replace('/@method\s*\((.*?)\)/', '<?= method_field($1); ?>', $value);
        $value = preg_replace('/@vite\s*\((.*?)\)/', '<?= vite($1); ?>', $value);

        // 8. Conditionals
        $value = preg_replace('/@if\s*\((.*?)\)/', '<?php if ($1): ?>', $value);
        $value = preg_replace('/@elseif\s*\((.*?)\)/', '<?php elseif ($1): ?>', $value);
        $value = preg_replace('/@else/', '<?php else: ?>', $value);
        $value = preg_replace('/@endif/', '<?php endif; ?>', $value);

        $value = preg_replace('/@unless\s*\((.*?)\)/', '<?php if (! ($1)): ?>', $value);
        $value = preg_replace('/@endunless/', '<?php endif; ?>', $value);

        $value = preg_replace('/@isset\s*\((.*?)\)/', '<?php if (isset($1)): ?>', $value);
        $value = preg_replace('/@endisset/', '<?php endif; ?>', $value);

        $value = preg_replace('/@empty\s*\((.*?)\)/', '<?php if (empty($1)): ?>', $value);
        $value = preg_replace('/@endempty/', '<?php endif; ?>', $value);

        // 9. Loops
        $value = preg_replace('/@foreach\s*\((.*?)\)/', '<?php foreach ($1): ?>', $value);
        $value = preg_replace('/@endforeach/', '<?php endforeach; ?>', $value);

        $value = preg_replace('/@for\s*\((.*?)\)/', '<?php for ($1): ?>', $value);
        $value = preg_replace('/@endfor/', '<?php endfor; ?>', $value);

        $value = preg_replace('/@while\s*\((.*?)\)/', '<?php while ($1): ?>', $value);
        $value = preg_replace('/@endwhile/', '<?php endwhile; ?>', $value);

        // 10. Direct PHP execution
        $value = preg_replace('/@php/', '<?php', $value);
        $value = preg_replace('/@endphp/', '?>', $value);

        return $value;
    }
}

