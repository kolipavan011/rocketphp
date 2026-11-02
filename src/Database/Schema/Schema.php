<?php

declare(strict_types=1);

namespace Rocket\Database\Schema;

use Closure;
use Rocket\Database\Contracts\ConnectionContract;
use Rocket\Facades\DB;

/**
 * Class Schema
 *
 * Facade-style Schema builder for table creation and inspection.
 */
class Schema
{
    /**
     * Get database connection.
     */
    protected static function getConnection(?string $name = null): ConnectionContract
    {
        return DB::connection($name);
    }

    /**
     * Create a new database table.
     */
    public static function create(string $table, Closure $callback, ?string $connection = null): void
    {
        $conn = static::getConnection($connection);
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = $blueprint->toSql($conn->getDriverName());
        $conn->statement($sql);
    }

    /**
     * Drop a database table.
     */
    public static function drop(string $table, ?string $connection = null): void
    {
        static::getConnection($connection)->statement("DROP TABLE `{$table}`");
    }

    /**
     * Drop a database table if it exists.
     */
    public static function dropIfExists(string $table, ?string $connection = null): void
    {
        static::getConnection($connection)->statement("DROP TABLE IF EXISTS `{$table}`");
    }

    /**
     * Determine if a table exists.
     */
    public static function hasTable(string $table, ?string $connection = null): bool
    {
        $conn = static::getConnection($connection);
        $driver = $conn->getDriverName();

        if ($driver === 'sqlite') {
            $result = $conn->select("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
            return !empty($result);
        }

        if ($driver === 'pgsql') {
            $result = $conn->select("SELECT tablename FROM pg_catalog.pg_tables WHERE tablename = ?", [$table]);
            return !empty($result);
        }

        $result = $conn->select("SHOW TABLES LIKE ?", [$table]);
        return !empty($result);
    }
}

