<?php

declare(strict_types=1);

namespace Rocket\Console;

use Closure;
use Rocket\Console\Commands\CacheClearCommand;
use Rocket\Console\Commands\ConfigCacheCommand;
use Rocket\Console\Commands\ConfigClearCommand;
use Rocket\Console\Commands\MakeCommandCommand;
use Rocket\Console\Commands\MakeComponentCommand;
use Rocket\Console\Commands\MakeControllerCommand;
use Rocket\Console\Commands\MakeMiddlewareCommand;
use Rocket\Console\Commands\MakeMigrationCommand;
use Rocket\Console\Commands\MakeModelCommand;
use Rocket\Console\Commands\MakeProviderCommand;
use Rocket\Console\Commands\MigrateCommand;
use Rocket\Console\Commands\RouteCacheCommand;
use Rocket\Console\Commands\RouteClearCommand;
use Rocket\Console\Commands\RouteListCommand;
use Rocket\Console\Commands\ServeCommand;
use Rocket\Console\Commands\StorageLinkCommand;
use Rocket\Console\Commands\StorageUnlinkCommand;
use Rocket\Foundation\Application as CoreApp;
use InvalidArgumentException;

/**
 * Class Application
 *
 * Rocketphp Console Application runner and command dispatcher.
 */
class Application
{
    public const VERSION = '1.0.0';

    protected CoreApp $app;

    /**
     * Registered commands.
     *
     * @var array<string, Command>
     */
    protected array $commands = [];

    /**
     * Indicates whether user commands and routes have been loaded.
     */
    protected bool $discovered = false;

    public function __construct(CoreApp $app)
    {
        $this->app = $app;
        $this->app->instance('console', $this);
        $this->app->instance(static::class, $this);

        $this->registerDefaultCommands();
    }

    protected function registerDefaultCommands(): void
    {
        $defaults = [
            new ServeCommand($this->app),
            new RouteListCommand($this->app),
            new MigrateCommand($this->app),
            new MakeCommandCommand($this->app),
            new MakeControllerCommand($this->app),
            new MakeModelCommand($this->app),
            new MakeMiddlewareCommand($this->app),
            new MakeMigrationCommand($this->app),
            new MakeProviderCommand($this->app),
            new MakeComponentCommand($this->app),
            new CacheClearCommand($this->app),
            new RouteCacheCommand($this->app),
            new RouteClearCommand($this->app),
            new ConfigCacheCommand($this->app),
            new ConfigClearCommand($this->app),
            new StorageLinkCommand($this->app),
            new StorageUnlinkCommand($this->app),
        ];

        foreach ($defaults as $command) {
            $this->add($command);
        }
    }

    public function add(Command $command): static
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    /**
     * Register a closure-based console command.
     */
    public function command(string $signature, Closure $callback): ClosureCommand
    {
        $command = new ClosureCommand($this->app, $signature, $callback);
        $this->add($command);
        return $command;
    }

    /**
     * Discover custom application commands and load console routes.
     */
    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discoverApplicationCommands();
        $this->loadConsoleRoutes();

        $this->discovered = true;
    }

    /**
     * Scan and register all custom commands in app/Console/Commands.
     */
    protected function discoverApplicationCommands(): void
    {
        $commandsPath = $this->app->appPath('Console/Commands');

        if (!is_dir($commandsPath)) {
            return;
        }

        $files = glob($commandsPath . '/*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $baseName = basename($file, '.php');
            $className = "App\\Console\\Commands\\{$baseName}";

            require_once $file;

            if (class_exists($className) && is_subclass_of($className, Command::class)) {
                $instance = $this->app->make($className);
                $this->add($instance);
            }
        }
    }

    /**
     * Load closure-based console routes from routes/console.php if present.
     */
    protected function loadConsoleRoutes(): void
    {
        $consoleRoutes = $this->app->basePath('routes/console.php');

        if (file_exists($consoleRoutes)) {
            require $consoleRoutes;
        }
    }

    /**
     * Call a console command programmatically.
     */
    public function call(string $commandName, array $arguments = [], array $options = []): int
    {
        $this->discover();

        if (!isset($this->commands[$commandName])) {
            throw new InvalidArgumentException("Console command [{$commandName}] not found.");
        }

        return $this->commands[$commandName]->execute($arguments, $options);
    }

    /**
     * Run the console command from raw CLI arguments ($argv).
     */
    public function run(array $argv = []): int
    {
        $this->app->boot();
        $this->discover();

        // Strip script name ($argv[0])
        array_shift($argv);

        $commandName = array_shift($argv);

        if ($commandName === null || $commandName === 'list' || $commandName === '--help' || $commandName === '-h') {
            $this->printHelp();
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            echo "\033[31m✖ Unknown command [{$commandName}]. Run 'php rocket' to see available commands.\033[0m" . PHP_EOL;
            return 1;
        }

        [$arguments, $options] = $this->parseArguments($argv);

        $command = $this->commands[$commandName];
        return $command->execute($arguments, $options);
    }

    protected function parseArguments(array $argv): array
    {
        $arguments = [];
        $options = [];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $opt = substr($arg, 2);
                if (str_contains($opt, '=')) {
                    [$k, $v] = explode('=', $opt, 2);
                    $options[$k] = $v;
                } else {
                    $options[$opt] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                $options[substr($arg, 1)] = true;
            } else {
                $arguments[] = $arg;
            }
        }

        return [$arguments, $options];
    }

    protected function printHelp(): void
    {
        $banner = <<<BANNER
\033[36m
  ____            _        _             _           
 |  _ \ ___   ___| | _____| |_ _ __  ___| |__  _ __  
 | |_) / _ \ / __| |/ / _ \ __| '_ \| '_ \ '_ \| '_ \ 
 |  _ < (_) | (__|   <  __/ |_| |_) | |_) | | | |_) |
 |_| \_\___/ \___|_|\_\___|\__| .__/| .__/|_| |_| .__/ 
                              |_|   |_|         |_|    
\033[0m \033[33mv%s\033[0m - High-Performance, Secure, Extensible PHP MVC Framework

\033[32mUsage:\033[0m
  php rocket <command> [arguments] [options]

\033[32mAvailable commands:\033[0m

BANNER;
        printf($banner, self::VERSION);

        $grouped = [];
        foreach ($this->commands as $name => $cmd) {
            $group = str_contains($name, ':') ? explode(':', $name)[0] : 'root';
            $grouped[$group][$name] = $cmd->getDescription();
        }

        foreach ($grouped as $group => $commands) {
            if ($group !== 'root') {
                echo " \033[33m{$group}\033[0m" . PHP_EOL;
            }

            foreach ($commands as $name => $desc) {
                echo '  ' . str_pad($name, 25) . " \033[90m{$desc}\033[0m" . PHP_EOL;
            }
        }

        echo PHP_EOL;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }
}
