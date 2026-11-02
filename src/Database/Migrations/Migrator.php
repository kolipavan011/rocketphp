<?php

declare(strict_types=1);

namespace Rocket\Database\Migrations;

use Rocket\Database\Contracts\ConnectionContract;
use Rocket\Database\Schema\Blueprint;
use Rocket\Database\Schema\Schema;

/**
 * Class Migrator
 *
 * Migration runner and batch state manager.
 */
class Migrator
{
    protected ConnectionContract $connection;
    protected string $table = 'migrations';

    public function __construct(ConnectionContract $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create migrations tracking table if not exists.
     */
    public function ensureRepository(): void
    {
        if (!Schema::hasTable($this->table)) {
            Schema::create($this->table, function (Blueprint $table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }
    }

    /**
     * Get list of already executed migrations.
     *
     * @return array<int, string>
     */
    public function getRan(): array
    {
        $this->ensureRepository();
        $records = $this->connection->table($this->table)->orderBy('batch', 'asc')->get();
        return array_column($records, 'migration');
    }

    /**
     * Get the next migration batch number.
     */
    public function getNextBatchNumber(): int
    {
        $this->ensureRepository();
        $max = $this->connection->table($this->table)->max('batch');
        return ((int) $max) + 1;
    }

    /**
     * Run all pending migrations in a path.
     *
     * @return array<int, string> Names of executed migrations
     */
    public function run(string $path): array
    {
        $this->ensureRepository();

        $files = glob(rtrim($path, '/\\') . '/*.php');
        if ($files === false || empty($files)) {
            return [];
        }

        sort($files);

        $ran = $this->getRan();
        $batch = $this->getNextBatchNumber();
        $executed = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = require $file;
            if (!$migration instanceof Migration) {
                // If file declared an anonymous class or regular class
                continue;
            }

            $this->connection->transaction(function () use ($migration, $name, $batch) {
                $migration->up();
                $this->connection->table($this->table)->insert([
                    'migration' => $name,
                    'batch'     => $batch,
                ]);
            });

            $executed[] = $name;
        }

        return $executed;
    }
}

