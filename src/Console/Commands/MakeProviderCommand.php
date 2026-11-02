<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class MakeProviderCommand extends Command
{
    protected string $name = 'make:provider';
    protected string $description = 'Create a new service provider class';

    public function handle(array $arguments, array $options): int
    {
        $name = $arguments[0] ?? null;
        if (!$name) {
            $this->error('Provider name is required. Usage: php rocket make:provider <Name>');
            return 1;
        }

        $className = str_ends_with($name, 'ServiceProvider') ? $name : $name . 'ServiceProvider';
        $directory = $this->app->appPath('Providers');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $className . '.php';
        if (file_exists($filePath)) {
            $this->error("Service Provider [{$className}] already exists at app/Providers/{$className}.php!");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Providers;

use Rocket\Foundation\ServiceProvider;

class {$className} extends ServiceProvider
{
    /**
     * Register any application services in the container.
     */
    public function register(): void
    {
        // Example: \$this->app->singleton('my.service', fn () => new MyService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Example: Register event listeners, routes, or view composers
    }
}
PHP;

        file_put_contents($filePath, $template);

        $this->success("Service Provider [{$className}] created successfully!");
        $this->line("Path: app/Providers/{$className}.php");
        $this->line("Remember to register it in config/app.php under the 'providers' array.");

        return 0;
    }
}

