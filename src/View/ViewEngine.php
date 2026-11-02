<?php

declare(strict_types=1);

namespace Rocket\View;

use Rocket\Container\Container;
use Rocket\Container\ContainerInterface;
use Rocket\Http\Response;
use Rocket\View\Contracts\CompilerInterface;
use Rocket\View\Contracts\ViewEngineInterface;
use InvalidArgumentException;
use RuntimeException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
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

    /**
     * Dependency injection container instance.
     */
    protected ?ContainerInterface $container = null;

    /**
     * Registered component aliases (alias => class or view).
     *
     * @var array<string, string>
     */
    protected array $componentAliases = [];

    /**
     * Component rendering stack.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $componentStack = [];

    public function __construct(CompilerInterface $compiler, array $paths = [], ?ContainerInterface $container = null)
    {
        $this->compiler = $compiler;
        foreach ($paths as $path) {
            $this->addPath($path);
        }
        $this->container = $container;
    }

    /**
     * Set the container instance.
     */
    public function setContainer(?ContainerInterface $container): static
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Get the container instance.
     */
    public function getContainer(): ?ContainerInterface
    {
        if ($this->container !== null) {
            return $this->container;
        }

        if (class_exists(Container::class)) {
            return Container::getInstance();
        }

        return null;
    }

    /**
     * Register a custom component alias.
     */
    public function component(string $alias, string $classOrView): static
    {
        $this->componentAliases[$alias] = $classOrView;
        return $this;
    }

    /**
     * Check if a template view exists.
     */
    public function exists(string $view): bool
    {
        try {
            $this->findViewFile($view);
            return true;
        } catch (InvalidArgumentException) {
            return false;
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
        $this->componentStack = [];

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

    /**
     * Start capturing output for a component.
     *
     * @param string $name Component tag name (e.g. 'alert', 'forms.input')
     * @param array<string, mixed> $data Attributes passed to the component
     */
    public function startComponent(string $name, array $data = []): void
    {
        $this->componentStack[] = [
            'name' => $name,
            'data' => $data,
            'slots' => [],
            'slotStack' => [],
            'obLevel' => ob_get_level(),
        ];

        ob_start();
    }

    /**
     * Complete and render the most recent component on the stack.
     */
    public function renderComponent(?string $expectedName = null): string
    {
        if (empty($this->componentStack)) {
            throw new RuntimeException("Cannot render component: no active component on stack.");
        }

        $component = array_pop($this->componentStack);

        if ($expectedName !== null && $component['name'] !== $expectedName) {
            throw new RuntimeException("Mismatched component tags: expected </x-{$component['name']}>, found </x-{$expectedName}>.");
        }

        // Close any dangling slots if open
        while (!empty($component['slotStack'])) {
            $slotMeta = array_pop($component['slotStack']);
            $content = ob_get_clean() ?: '';
            $component['slots'][$slotMeta['name']] = new ComponentSlot($content, $slotMeta['attributes']);
        }

        $defaultContent = ob_get_clean() ?: '';
        if (!isset($component['slots']['slot'])) {
            $component['slots']['slot'] = new ComponentSlot($defaultContent);
        }

        return $this->resolveAndRenderComponent($component['name'], $component['data'], $component['slots']);
    }

    /**
     * Render a self-closing component directly without capturing a default slot.
     */
    public function renderComponentDirect(string $name, array $data = []): string
    {
        $slots = [
            'slot' => new ComponentSlot(''),
        ];

        return $this->resolveAndRenderComponent($name, $data, $slots);
    }

    /**
     * Start capturing content for a named slot.
     */
    public function startSlot(string $name, array $attributes = []): void
    {
        if (empty($this->componentStack)) {
            throw new RuntimeException("Cannot start slot [{$name}] outside of a component.");
        }

        $topIndex = array_key_last($this->componentStack);
        $this->componentStack[$topIndex]['slotStack'][] = [
            'name' => $name,
            'attributes' => $attributes,
            'obLevel' => ob_get_level(),
        ];

        ob_start();
    }

    /**
     * End capturing content for the active slot.
     */
    public function endSlot(): void
    {
        if (empty($this->componentStack)) {
            throw new RuntimeException("Cannot end slot outside of a component.");
        }

        $topIndex = array_key_last($this->componentStack);
        if (empty($this->componentStack[$topIndex]['slotStack'])) {
            throw new RuntimeException("Cannot end slot without an active open slot.");
        }

        $slotMeta = array_pop($this->componentStack[$topIndex]['slotStack']);
        $content = ob_get_clean() ?: '';

        $this->componentStack[$topIndex]['slots'][$slotMeta['name']] = new ComponentSlot(
            $content,
            $slotMeta['attributes']
        );
    }

    /**
     * Extract declared props from template data and remove them from the attribute bag.
     *
     * @param array<int|string, mixed> $declaredProps
     * @param array<string, mixed> $data
     * @param ComponentAttributeBag|null $attributes
     * @return array<string, mixed>
     */
    public function extractProps(array $declaredProps, array $data, ?ComponentAttributeBag $attributes = null): array
    {
        $extracted = [];
        $consumedKeys = [];

        foreach ($declaredProps as $key => $default) {
            $propName = is_int($key) ? $default : $key;
            $propDefault = is_int($key) ? null : $default;

            $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $propName));
            $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $propName));

            if (array_key_exists($propName, $data)) {
                $extracted[$propName] = $data[$propName];
                $consumedKeys[$propName] = true;
            } elseif (array_key_exists($kebab, $data)) {
                $extracted[$propName] = $data[$kebab];
                $consumedKeys[$kebab] = true;
            } elseif (array_key_exists($snake, $data)) {
                $extracted[$propName] = $data[$snake];
                $consumedKeys[$snake] = true;
            } else {
                $extracted[$propName] = $propDefault;
            }
        }

        if ($attributes !== null) {
            $extracted['attributes'] = $attributes->except(...array_keys($consumedKeys));
        } else {
            $remaining = array_diff_key($data, $consumedKeys, ['slot' => true, 'attributes' => true]);
            $extracted['attributes'] = new ComponentAttributeBag($remaining);
        }

        return $extracted;
    }

    /**
     * Convert component tag name to PSR-4 class name.
     * Examples: 'alert' => 'App\View\Components\Alert', 'forms.input-field' => 'App\View\Components\Forms\InputField'
     */
    public function formatComponentClass(string $name): string
    {
        $segments = explode('.', $name);
        $formatted = array_map(function ($segment) {
            return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $segment)));
        }, $segments);

        return 'App\\View\\Components\\' . implode('\\', $formatted);
    }

    /**
     * Resolve component name and render as either class component or anonymous view component.
     *
     * @param string $name
     * @param array<string, mixed> $data
     * @param array<string, ComponentSlot> $slots
     */
    protected function resolveAndRenderComponent(string $name, array $data, array $slots): string
    {
        // 1. Check explicit alias
        $target = $this->componentAliases[$name] ?? null;

        if ($target !== null && is_subclass_of($target, Component::class)) {
            return $this->renderClassComponent($target, $data, $slots);
        }

        if ($target !== null && is_string($target)) {
            return $this->renderAnonymousComponent($target, $data, $slots);
        }

        // 2. Class-based component check (e.g. App\View\Components\Alert)
        $className = $this->formatComponentClass($name);
        if (class_exists($className) && is_subclass_of($className, Component::class)) {
            return $this->renderClassComponent($className, $data, $slots);
        }

        // 3. Anonymous component view check (components.name)
        $viewName = 'components.' . $name;
        if ($this->exists($viewName)) {
            return $this->renderAnonymousComponent($viewName, $data, $slots);
        }

        // 4. Direct view check (name)
        if ($this->exists($name)) {
            return $this->renderAnonymousComponent($name, $data, $slots);
        }

        throw new InvalidArgumentException(
            "Component [{$name}] not found. Neither class [{$className}] nor view [{$viewName}] exists."
        );
    }

    /**
     * Render a class-based component.
     *
     * @param class-string<Component> $className
     * @param array<string, mixed> $data
     * @param array<string, ComponentSlot> $slots
     */
    protected function renderClassComponent(string $className, array $data, array $slots): string
    {
        $reflector = new ReflectionClass($className);
        $constructor = $reflector->getConstructor();
        $constructorArgs = [];
        $consumedKeys = [];

        if ($constructor !== null) {
            $container = $this->getContainer();
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();
                $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $paramName));
                $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $paramName));

                if (array_key_exists($paramName, $data)) {
                    $constructorArgs[] = $data[$paramName];
                    $consumedKeys[$paramName] = true;
                } elseif (array_key_exists($kebab, $data)) {
                    $constructorArgs[] = $data[$kebab];
                    $consumedKeys[$kebab] = true;
                } elseif (array_key_exists($snake, $data)) {
                    $constructorArgs[] = $data[$snake];
                    $consumedKeys[$snake] = true;
                } elseif ($param->isDefaultValueAvailable()) {
                    $constructorArgs[] = $param->getDefaultValue();
                } elseif ($container && $param->getType() && !$param->getType()->isBuiltin()) {
                    /** @var \ReflectionNamedType $type */
                    $type = $param->getType();
                    $constructorArgs[] = $container->make($type->getName());
                } else {
                    $constructorArgs[] = null;
                }
            }
        }

        $remainingAttributes = array_diff_key($data, $consumedKeys);

        /** @var Component $component */
        $component = $reflector->newInstanceArgs($constructorArgs);
        $component->attributes = new ComponentAttributeBag($remainingAttributes);
        $component->slot = $slots['slot'] ?? new ComponentSlot('');

        if (!$component->shouldRender()) {
            return '';
        }

        $rendered = $component->render();

        if ($rendered instanceof Response) {
            return $rendered->getContent();
        }

        // If returned a view name, render that view with component data
        if (is_string($rendered) && !str_contains($rendered, '<') && $this->exists($rendered)) {
            $viewData = array_merge(
                $component->data(),
                [
                    'attributes' => $component->attributes,
                    'slot' => $component->slot,
                ],
                $slots
            );

            return $this->renderPartial($rendered, $viewData);
        }

        return (string) $rendered;
    }

    /**
     * Render an anonymous component view.
     *
     * @param string $viewName
     * @param array<string, mixed> $data
     * @param array<string, ComponentSlot> $slots
     */
    protected function renderAnonymousComponent(string $viewName, array $data, array $slots): string
    {
        $attributes = new ComponentAttributeBag($data);
        $defaultSlot = $slots['slot'] ?? new ComponentSlot('');

        $viewData = array_merge(
            $data,
            [
                'attributes' => $attributes,
                'slot' => $defaultSlot,
            ],
            $slots
        );

        return $this->renderPartial($viewName, $viewData);
    }
}
