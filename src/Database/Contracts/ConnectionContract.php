<?php

declare(strict_types=1);

namespace Rocket\Database\Contracts;

use Closure;
use PDO;

interface ConnectionContract
{
    public function getPdo(): PDO;
    public function getDriverName(): string;
    public function select(string $query, array $bindings = []): array;
    public function selectOne(string $query, array $bindings = []): ?array;
    public function insert(string $query, array $bindings = []): int|string;
    public function update(string $query, array $bindings = []): int;
    public function delete(string $query, array $bindings = []): int;
    public function statement(string $query, array $bindings = []): bool;
    public function transaction(Closure $callback): mixed;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollBack(): void;
    public function table(string $table): mixed;
}

