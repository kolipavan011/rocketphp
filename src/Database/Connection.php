<?php

declare(strict_types=1);

namespace Rocket\Database;

use Closure;
use PDO;
use PDOException;
use PDOStatement;
use Rocket\Database\Contracts\ConnectionContract;
use Rocket\Database\Query\Builder;
use RuntimeException;
use Throwable;

/**
 * Class Connection
 *
 * PDO database connection wrapper with prepared statements and transaction management.
 */
class Connection implements ConnectionContract
{
    protected ?PDO $pdo = null;
    protected array $config;
    protected int $transactions = 0;
    protected array $queryLog = [];
    protected bool $logging = false;

    public function __construct(array $config, ?PDO $pdo = null)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Get or lazily create the underlying PDO instance.
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->createPdoInstance();
        }

        return $this->pdo;
    }

    /**
     * Get driver name (mysql, sqlite, pgsql).
     */
    public function getDriverName(): string
    {
        return $this->config['driver'] ?? 'sqlite';
    }

    /**
     * Create PDO connection based on configuration.
     */
    protected function createPdoInstance(): PDO
    {
        $driver = $this->getDriverName();

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            switch ($driver) {
                case 'sqlite':
                    $database = $this->config['database'] ?? ':memory:';
                    $dsn = "sqlite:{$database}";
                    return new PDO($dsn, null, null, $options);

                case 'mysql':
                case 'mariadb':
                    $host = $this->config['host'] ?? '127.0.0.1';
                    $port = $this->config['port'] ?? 3306;
                    $db = $this->config['database'] ?? '';
                    $charset = $this->config['charset'] ?? 'utf8mb4';
                    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
                    return new PDO($dsn, $this->config['username'] ?? '', $this->config['password'] ?? '', $options);

                case 'pgsql':
                    $host = $this->config['host'] ?? '127.0.0.1';
                    $port = $this->config['port'] ?? 5432;
                    $db = $this->config['database'] ?? '';
                    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
                    return new PDO($dsn, $this->config['username'] ?? '', $this->config['password'] ?? '', $options);

                default:
                    throw new RuntimeException("Unsupported database driver: [{$driver}].");
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Run a select query and return all results.
     */
    public function select(string $query, array $bindings = []): array
    {
        $statement = $this->prepared($query, $bindings);
        $statement->execute();
        return $statement->fetchAll();
    }

    /**
     * Run a select query and return a single row.
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        $statement = $this->prepared($query, $bindings);
        $statement->execute();
        $record = $statement->fetch();
        return $record === false ? null : $record;
    }

    /**
     * Run an insert statement and return the last insert ID.
     */
    public function insert(string $query, array $bindings = []): int|string
    {
        $this->statement($query, $bindings);
        $id = $this->getPdo()->lastInsertId();
        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Run an update statement and return affected rows.
     */
    public function update(string $query, array $bindings = []): int
    {
        $statement = $this->prepared($query, $bindings);
        $statement->execute();
        return $statement->rowCount();
    }

    /**
     * Run a delete statement and return affected rows.
     */
    public function delete(string $query, array $bindings = []): int
    {
        $statement = $this->prepared($query, $bindings);
        $statement->execute();
        return $statement->rowCount();
    }

    /**
     * Execute a general SQL statement.
     */
    public function statement(string $query, array $bindings = []): bool
    {
        $statement = $this->prepared($query, $bindings);
        return $statement->execute();
    }

    /**
     * Prepare a statement and bind parameters.
     */
    protected function prepared(string $query, array $bindings): PDOStatement
    {
        $pdo = $this->getPdo();
        $statement = $pdo->prepare($query);

        foreach ($bindings as $key => $value) {
            $paramKey = is_int($key) ? $key + 1 : $key;

            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };

            $statement->bindValue($paramKey, $value, $type);
        }

        if ($this->logging) {
            $this->queryLog[] = ['query' => $query, 'bindings' => $bindings, 'time' => microtime(true)];
        }

        return $statement;
    }

    /**
     * Execute callback within a database transaction.
     */
    public function transaction(Closure $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactions === 0) {
            $this->getPdo()->beginTransaction();
        } else {
            $this->getPdo()->exec("SAVEPOINT trans_{$this->transactions}");
        }

        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions === 1) {
            $this->getPdo()->commit();
        } elseif ($this->transactions > 1) {
            $savepoint = $this->transactions - 1;
            $this->getPdo()->exec("RELEASE SAVEPOINT trans_{$savepoint}");
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactions === 1) {
            $this->getPdo()->rollBack();
        } elseif ($this->transactions > 1) {
            $savepoint = $this->transactions - 1;
            $this->getPdo()->exec("ROLLBACK TO SAVEPOINT trans_{$savepoint}");
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    /**
     * Create a new Query Builder instance for a table.
     */
    public function table(string $table): Builder
    {
        return new Builder($this, $table);
    }

    public function enableQueryLog(): void
    {
        $this->logging = true;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }
}

