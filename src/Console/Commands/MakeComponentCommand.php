<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class MakeComponentCommand extends Command
{
    protected string $name = 'make:component';
    protected string $description = 'Create a new view component class and template';

    public function handle(array $arguments, array $options): int
    {
        $rawName = $arguments[0] ?? null;
        if (!$rawName) {
            $this->error('Component name is required. Usage: php rocket make:component <Name> [--inline] [--view]');
            return 1;
        }

        $isInline = isset($options['inline']) || in_array('--inline', $arguments, true);
        $isViewOnly = isset($options['view']) || in_array('--view', $arguments, true);

        // Normalize slashes and dots to /
        $normalized = str_replace(['\\', '.'], '/', $rawName);
        $segments = explode('/', trim($normalized, '/'));

        // Format class segments (PascalCase)
        $classSegments = array_map(function ($seg) {
            return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $seg)));
        }, $segments);

        $className = array_pop($classSegments);
        $subNamespace = !empty($classSegments) ? '\\' . implode('\\', $classSegments) : '';
        $fullNamespace = 'App\\View\\Components' . $subNamespace;

        // Format view path segments (kebab-case)
        $viewSegments = array_map(function ($seg) {
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $seg));
        }, $segments);
        $viewDotName = 'components.' . implode('.', $viewSegments);
        $viewRelativePath = 'components/' . implode('/', $viewSegments) . '.rocket.php';

        $classDirectory = $this->app->appPath('View/Components' . (!empty($classSegments) ? '/' . implode('/', $classSegments) : ''));
        $classFilePath = $classDirectory . DIRECTORY_SEPARATOR . $className . '.php';

        $viewFilePath = $this->app->resourcePath('views/' . $viewRelativePath);

        // 1. View-only (anonymous component)
        if ($isViewOnly) {
            if (file_exists($viewFilePath)) {
                $this->error("Component view [{$viewRelativePath}] already exists!");
                return 1;
            }
            if (!is_dir(dirname($viewFilePath))) {
                mkdir(dirname($viewFilePath), 0755, true);
            }
            $viewTemplate = <<<BLADE
@props([
    // 'prop' => 'default',
])

<div>
    {{ \$slot }}
</div>

BLADE;
            file_put_contents($viewFilePath, $viewTemplate);
            $this->success("Anonymous component view created at resources/views/{$viewRelativePath}");
            return 0;
        }

        // 2. Class file
        if (file_exists($classFilePath)) {
            $this->error("Component class [{$className}] already exists!");
            return 1;
        }

        if (!is_dir($classDirectory)) {
            mkdir($classDirectory, 0755, true);
        }

        if ($isInline) {
            $renderMethod = <<<PHP
    /**
     * Get the view / contents that represent the component.
     */
    public function render(): string
    {
        return <<<'HTML'
<div>
    {{ \$slot }}
</div>
HTML;
    }
PHP;
        } else {
            $renderMethod = <<<PHP
    /**
     * Get the view / contents that represent the component.
     */
    public function render(): string
    {
        return '{$viewDotName}';
    }
PHP;
        }

        $classContent = <<<PHP
<?php

declare(strict_types=1);

namespace {$fullNamespace};

use Rocket\View\Component;

class {$className} extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

{$renderMethod}
}

PHP;

        file_put_contents($classFilePath, $classContent);
        $classRelativePath = 'app/View/Components/' . (!empty($classSegments) ? implode('/', $classSegments) . '/' : '') . "{$className}.php";
        $this->success("Component class [{$className}] created at {$classRelativePath}");

        // 3. View template (if not inline)
        if (!$isInline) {
            if (!file_exists($viewFilePath)) {
                if (!is_dir(dirname($viewFilePath))) {
                    mkdir(dirname($viewFilePath), 0755, true);
                }

                $viewContent = <<<BLADE
<div>
    {{ \$slot }}
</div>

BLADE;
                file_put_contents($viewFilePath, $viewContent);
                $this->success("Component view created at resources/views/{$viewRelativePath}");
            }
        }

        return 0;
    }
}

