<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class MakeCommandCommand extends Command
{
    protected string $name = 'make:command';
    protected string $description = 'Create a new custom Rocket CLI command class';

    public function handle(array $arguments, array $options): int
    {
        $name = $arguments[0] ?? null;
        if (!$name) {
            $this->error('Command class name is required. Usage: php rocket make:command <Name> [--command=signature]');
            return 1;
        }

        $className = str_ends_with($name, 'Command') ? $name : $name . 'Command';
        $directory = $this->app->appPath('Console/Commands');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $className . '.php';
        if (file_exists($filePath)) {
            $this->error("Command [{$className}] already exists at app/Console/Commands/{$className}.php!");
            return 1;
        }

        // Determine default command signature if not provided
        $signature = $options['command'] ?? null;
        if (!$signature) {
            $baseName = str_replace('Command', '', $className);
            $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $baseName));
            $signature = "app:{$kebab}";
        }

        $description = $options['description'] ?? "Execute the {$signature} command";

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Rocket\Console\Command;

class {$className} extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected string \$name = '{$signature}';

    /**
     * The console command description.
     */
    protected string \$description = '{$description}';

    /**
     * Execute the console command.
     *
     * @param array \$arguments Positional command arguments
     * @param array \$options Flags and options passed with --option
     * @return int Exit status code (0 for success, non-zero for failure)
     */
    public function handle(array \$arguments, array \$options): int
    {
        \$this->info("Executing [{\$this->name}] command...");

        // Your custom business logic goes here...

        \$this->success("Command [{\$this->name}] executed successfully!");

        return 0;
    }
}
PHP;

        file_put_contents($filePath, $template);

        $this->success("Command class [{$className}] created successfully!");
        $this->line("Path: app/Console/Commands/{$className}.php");
        $this->line("Signature: php rocket {$signature}");

        return 0;
    }
}

