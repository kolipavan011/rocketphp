<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;

class ServeCommand extends Command
{
    protected string $name = 'serve';
    protected string $description = 'Start the Rocketphp development server';

    public function handle(array $arguments, array $options): int
    {
        $host = $options['host'] ?? '127.0.0.1';
        $port = (int) ($options['port'] ?? 8000);
        $publicDir = $this->app->publicPath();

        $this->info("Rocketphp development server started at http://{$host}:{$port}");
        $this->line("Press Ctrl+C to stop.");

        $command = sprintf(
            'php -S %s:%d -t %s %s/index.php',
            $host,
            $port,
            escapeshellarg($publicDir),
            escapeshellarg($publicDir)
        );

        passthru($command);

        return 0;
    }
}

