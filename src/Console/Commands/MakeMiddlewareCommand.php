<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class MakeMiddlewareCommand extends Command
{
    protected string $name = 'make:middleware';
    protected string $description = 'Create a new HTTP middleware class';

    public function handle(array $arguments, array $options): int
    {
        $name = $arguments[0] ?? null;
        if (!$name) {
            $this->error('Middleware name is required. Usage: php rocket make:middleware <Name>');
            return 1;
        }

        $className = str_ends_with($name, 'Middleware') ? $name : $name . 'Middleware';
        $directory = $this->app->appPath('Http/Middleware');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $className . '.php';
        if (file_exists($filePath)) {
            $this->error("Middleware [{$className}] already exists!");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Rocket\Http\Contracts\MiddlewareInterface;
use Rocket\Http\Request;
use Rocket\Http\Response;

class {$className} implements MiddlewareInterface
{
    public function handle(Request \$request, Closure \$next): Response
    {
        // Pre-processing logic...

        \$response = \$next(\$request);

        // Post-processing logic...

        return \$response;
    }
}
PHP;

        file_put_contents($filePath, $template);
        $this->success("Middleware [{$className}] created successfully at app/Http/Middleware/{$className}.php");

        return 0;
    }
}

