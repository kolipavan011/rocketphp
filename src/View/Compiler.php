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

        // 3. Props directive @props(['type' => 'info'])
        $value = preg_replace_callback('/@props\s*\((.*?)\)/s', function ($matches) {
            return '<?php extract($this->extractProps(' . $matches[1] . ', $__data, $attributes ?? null)); ?>';
        }, $value);

        // 4. Component and Slot tags (<x-...>, <x-slot:...>)
        $value = $this->compileComponents($value);

        // 5. Raw echo {!! $var !!}
        $value = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?= $1 ?>', $value);

        // 6. Escaped echo {{ $var }} (uses e() to escape HTML safely while preserving HtmlableInterface objects)
        $value = preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<?= e($1) ?>',
            $value
        );

        // 7. Template inheritance directives
        $value = preg_replace('/@extends\s*\((.*?)\)/', '<?php $this->extend($1); ?>', $value);
        $value = preg_replace('/@section\s*\((.*?)\)/', '<?php $this->startSection($1); ?>', $value);
        $value = preg_replace('/@endsection/', '<?php $this->endSection(); ?>', $value);
        $value = preg_replace('/@yield\s*\((.*?)\)/', '<?= $this->yieldContent($1); ?>', $value);

        // 8. Partial includes
        $value = preg_replace_callback('/@include\s*\((.*?)\)/', function ($matches) {
            return '<?= $this->renderPartial(' . $matches[1] . ', $__data); ?>';
        }, $value);

        // 9. Security directives
        $value = preg_replace('/@csrf/', '<?= csrf_field(); ?>', $value);
        $value = preg_replace('/@method\s*\((.*?)\)/', '<?= method_field($1); ?>', $value);
        $value = preg_replace('/@vite\s*\((.*?)\)/', '<?= vite($1); ?>', $value);

        // 10. Conditionals
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

        // 11. Loops
        $value = preg_replace('/@foreach\s*\((.*?)\)/', '<?php foreach ($1): ?>', $value);
        $value = preg_replace('/@endforeach/', '<?php endforeach; ?>', $value);

        $value = preg_replace('/@for\s*\((.*?)\)/', '<?php for ($1): ?>', $value);
        $value = preg_replace('/@endfor/', '<?php endfor; ?>', $value);

        $value = preg_replace('/@while\s*\((.*?)\)/', '<?php while ($1): ?>', $value);
        $value = preg_replace('/@endwhile/', '<?php endwhile; ?>', $value);

        // 12. Direct PHP execution
        $value = preg_replace('/@php/', '<?php', $value);
        $value = preg_replace('/@endphp/', '?>', $value);

        return $value;
    }

    /**
     * Compile view components and slot tags.
     */
    public function compileComponents(string $value): string
    {
        // Safe, non-backtracking attribute matching pattern
        $attrPattern = '(?<attrs>(?>\s++[^\s"\'><=\/]++(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'><=\/]++))?)*+)';

        // 1. Slot tags (opening and self-closing)
        $slotPattern = '/<x-slot(?::(?<name1>[a-zA-Z0-9\-_]+)|\s+name=["\'](?<name2>[a-zA-Z0-9\-_]+)["\'])' . $attrPattern . '\s*+(?<selfClosing>\/)?\s*+>/s';
        $value = preg_replace_callback($slotPattern, function ($matches) {
            $name = !empty($matches['name1']) ? $matches['name1'] : $matches['name2'];
            $attrs = $this->compileAttributes($matches['attrs'] ?? '');
            if (!empty($matches['selfClosing'])) {
                return '<?php $this->startSlot(\'' . $name . '\', ' . $attrs . '); $this->endSlot(); ?>';
            }
            return '<?php $this->startSlot(\'' . $name . '\', ' . $attrs . '); ?>';
        }, $value);

        // 2. Closing slot tags: </x-slot:header> or </x-slot>
        $value = preg_replace(
            '/<\/x-slot(?::[a-zA-Z0-9\-_]+)?\s*>/s',
            '<?php $this->endSlot(); ?>',
            $value
        );

        // 3. Component tags (opening and self-closing)
        $componentPattern = '/<x-(?!slot[\s:>])(?<name>[a-zA-Z0-9\-_\.]+)' . $attrPattern . '\s*+(?<selfClosing>\/)?\s*+>/s';
        $value = preg_replace_callback($componentPattern, function ($matches) {
            $name = $matches['name'];
            $attrs = $this->compileAttributes($matches['attrs'] ?? '');
            if (!empty($matches['selfClosing'])) {
                return '<?= $this->renderComponentDirect(\'' . $name . '\', ' . $attrs . '); ?>';
            }
            return '<?php $this->startComponent(\'' . $name . '\', ' . $attrs . '); ?>';
        }, $value);

        // 4. Closing component tags: </x-alert>
        $value = preg_replace_callback(
            '/<\/x-(?!slot[\s:>])([a-zA-Z0-9\-_\.]+)\s*>/s',
            function ($matches) {
                return '<?= $this->renderComponent(\'' . $matches[1] . '\'); ?>';
            },
            $value
        );

        return $value;
    }

    /**
     * Compile component attribute string into a valid PHP array expression.
     */
    public function compileAttributes(string $attributeString): string
    {
        $attributeString = trim($attributeString);
        if ($attributeString === '') {
            return '[]';
        }

        $pattern = '/
            (?<name>[:\w\-\.]+)(?:\s*=\s*(?:
                "(?<dval>[^"]*)" |
                \'(?<sval>[^\']*)\' |
                (?<uval>[^\s"\'>]+)
            ))?
        /x';

        preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as $match) {
            $rawName = $match['name'];
            if ($rawName === '' || $rawName === '/') {
                continue;
            }

            $hasValue = (isset($match['dval']) && $match['dval'] !== '')
                || (isset($match['sval']) && $match['sval'] !== '')
                || (isset($match['uval']) && $match['uval'] !== '')
                || str_contains($match[0], '=');

            $value = $match['dval'] ?? ($match['sval'] ?? ($match['uval'] ?? ''));

            // Escaped colon "::class" -> literal string ":class"
            if (str_starts_with($rawName, '::')) {
                $name = substr($rawName, 1);
                $attributes[] = var_export($name, true) . ' => ' . var_export($value, true);
                continue;
            }

            // Bound PHP expression ":attr" -> evaluated as PHP code
            if (str_starts_with($rawName, ':')) {
                $name = substr($rawName, 1);
                $expression = trim($value);
                if ($expression === '') {
                    $expression = 'null';
                }
                $attributes[] = var_export($name, true) . ' => (' . $expression . ')';
                continue;
            }

            // Boolean flag attribute (e.g. "disabled", "required")
            if (!$hasValue) {
                $attributes[] = var_export($rawName, true) . ' => true';
                continue;
            }

            // Standard HTML string attribute
            $attributes[] = var_export($rawName, true) . ' => ' . var_export($value, true);
        }

        return '[' . implode(', ', $attributes) . ']';
    }
}

