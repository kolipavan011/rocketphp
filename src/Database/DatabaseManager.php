<?php

declare(strict_types=1);

namespace Rocket\Database;

use Rocket\Database\Contracts\ConnectionContract;
use Rocket\Database\Query\Builder;
use Rocket\Foundation\Config;
use InvalidArgumentException;

/**
 * Class DatabaseManager
 *
 * Manages multiple database connections and provides unified database access.
 */
class DatabaseManager
{
    protected Config $config;
    protected array $connections = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get a database connection instance.
     */
    public function connection(?string $name = null): ConnectionContract
    {
        $name = $name ?? $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return (string) $this->config->get('database.default', 'sqlite');
    }

    /**
     * Create a new Connection instance.
     */
    protected function makeConnection(string $name): ConnectionContract
    {
        $connections = (array) $this->config->get('database.connections', []);

        if (!isset($connections[$name])) {
            throw new InvalidArgumentException("Database connection [{$name}] not configured.");
        }

        return new Connection($connections[$name]);
    }

    /**
     * Begin a fluent query against a database table on default connection.
     */
    public function table(string $table): Builder
    {
        return $this->connection()->table($table);
    }

    /**
     * Create a raw SQL expression.
     */
    public static function raw(string $value): Raw
    {
        return new Raw($value);
    }

    /**
     * Forward dynamic method calls to default connection.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }
}

