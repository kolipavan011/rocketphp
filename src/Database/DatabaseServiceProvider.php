<?php

declare(strict_types=1);

namespace Rocket\Database;

use Rocket\Database\Contracts\ConnectionContract;
use Rocket\Foundation\ServiceProvider;

/**
 * Class DatabaseServiceProvider
 *
 * Bootstraps database services and connections.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('db', function () {
            return new DatabaseManager($this->app->make('config'));
        });

        $this->app->alias('db', DatabaseManager::class);

        $this->app->singleton('db.connection', function () {
            /** @var DatabaseManager $db */
            $db = $this->app->make('db');
            return $db->connection();
        });

        $this->app->alias('db.connection', ConnectionContract::class);
        $this->app->alias('db.connection', Connection::class);
    }
}

