<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;
use Rocket\Database\Migrations\Migrator;
use Rocket\Facades\DB;

class MigrateCommand extends Command
{
    protected string $name = 'migrate';
    protected string $description = 'Run outstanding database migrations';

    public function handle(array $arguments, array $options): int
    {
        $path = $this->app->databasePath('migrations');
        $migrator = new Migrator(DB::connection());

        $this->info("Running migrations from: {$path}");

        $executed = $migrator->run($path);

        if (empty($executed)) {
            $this->line("Nothing to migrate.");
            return 0;
        }

        foreach ($executed as $migration) {
            $this->success("Migrated: {$migration}");
        }

        return 0;
    }
}

