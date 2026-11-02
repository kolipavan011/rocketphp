<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class MakeControllerCommand extends Command
{
    protected string $name = 'make:controller';
    protected string $description = 'Create a new controller class';

    public function handle(array $arguments, array $options): int
    {
        $name = $arguments[0] ?? null;
        if (!$name) {
            $this->error('Controller name is required. Usage: php rocket make:controller <Name>');
            return 1;
        }

        $className = str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
        $directory = $this->app->appPath('Http/Controllers');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $className . '.php';
        if (file_exists($filePath)) {
            $this->error("Controller [{$className}] already exists!");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Rocket\Routing\Controller;
use Rocket\Http\Request;
use Rocket\Http\Response;

class {$className} extends Controller
{
    public function index(Request \$request): Response
    {
        return \$this->json(['message' => 'Hello from {$className}']);
    }
}
PHP;

        file_put_contents($filePath, $template);
        $this->success("Controller [{$className}] created successfully at app/Http/Controllers/{$className}.php");

        return 0;
    }
}

