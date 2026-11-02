<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class MakeModelCommand extends Command
{
    protected string $name = 'make:model';
    protected string $description = 'Create a new Active Record model class';

    public function handle(array $arguments, array $options): int
    {
        $name = $arguments[0] ?? null;
        if (!$name) {
            $this->error('Model name is required. Usage: php rocket make:model <Name>');
            return 1;
        }

        $className = ucfirst($name);
        $directory = $this->app->appPath('Models');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $className . '.php';
        if (file_exists($filePath)) {
            $this->error("Model [{$className}] already exists!");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use Rocket\Database\ORM\Model;

class {$className} extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array \$fillable = [];
}
PHP;

        file_put_contents($filePath, $template);
        $this->success("Model [{$className}] created successfully at app/Models/{$className}.php");

        return 0;
    }
}

