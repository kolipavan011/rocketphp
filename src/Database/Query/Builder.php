<?php

declare(strict_types=1);

namespace Rocket\Database\Query;

use Rocket\Database\Contracts\ConnectionContract;
use Rocket\Database\Paginator;
use Rocket\Database\Raw;
use InvalidArgumentException;

/**
 * Class Builder
 *
 * Expressive, high-performance, and secure SQL Query Builder with prepared statements.
 */
class Builder
{
    protected ConnectionContract $connection;
    protected string $table;
    protected array $columns = ['*'];
    protected bool $distinct = false;
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $joins = [];
    protected array $orders = [];
    protected array $groups = [];
    protected array $havings = [];
    protected ?int $limit = null;
    protected ?int $offset = null;

    public function __construct(ConnectionContract $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function select(array|string ...$columns): static
    {
        $cols = [];
        foreach ($columns as $column) {
            if (is_array($column)) {
                $cols = array_merge($cols, $column);
            } else {
                $cols[] = $column;
            }
        }

        $this->columns = !empty($cols) ? $cols : ['*'];
        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;
        return $this;
    }

    public function from(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function where(string|Raw $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        // Two arguments: where('status', 'active') => where('status', '=', 'active')
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if ($column instanceof Raw) {
            $this->wheres[] = [
                'type'    => 'raw',
                'sql'     => $column->getValue(),
                'boolean' => $boolean,
            ];
            return $this;
        }

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => strtoupper((string) $operator),
            'value'    => $value,
            'boolean'  => $boolean,
        ];

        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string|Raw $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): static
    {
        if (empty($values)) {
            // Empty WHERE IN should match nothing
            return $this->where(new Raw($not ? '1 = 1' : '0 = 1'), boolean: $boolean);
        }

        $this->wheres[] = [
            'type'    => 'in',
            'column'  => $column,
            'values'  => array_values($values),
            'not'     => $not,
            'boolean' => $boolean,
        ];

        foreach ($values as $val) {
            $this->bindings[] = $val;
        }

        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): static
    {
        $this->wheres[] = [
            'type'    => 'null',
            'column'  => $column,
            'not'     => $not,
            'boolean' => $boolean,
        ];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function whereBetween(string $column, array $values, string $boolean = 'AND', bool $not = false): static
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException("whereBetween requires exactly two values.");
        }

        $this->wheres[] = [
            'type'    => 'between',
            'column'  => $column,
            'not'     => $not,
            'boolean' => $boolean,
        ];

        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $this->joins[] = [
            'type'     => strtoupper($type),
            'table'    => $table,
            'first'    => $first,
            'operator' => $operator,
            'second'   => $second,
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function groupBy(string ...$columns): static
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orders[] = ['column' => $column, 'direction' => $direction];
        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(int $limit): static
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function take(int $limit): static
    {
        return $this->limit($limit);
    }

    public function offset(int $offset): static
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function skip(int $offset): static
    {
        return $this->offset($offset);
    }

    /**
     * Compile SELECT query string.
     */
    public function toSql(): string
    {
        $parts = ['SELECT'];

        if ($this->distinct) {
            $parts[] = 'DISTINCT';
        }

        $parts[] = implode(', ', array_map([$this, 'wrapColumn'], $this->columns));
        $parts[] = 'FROM ' . $this->wrapTable($this->table);

        foreach ($this->joins as $join) {
            $parts[] = "{$join['type']} JOIN {$this->wrapTable($join['table'])} ON {$this->wrapColumn($join['first'])} {$join['operator']} {$this->wrapColumn($join['second'])}";
        }

        if (!empty($this->wheres)) {
            $parts[] = 'WHERE ' . $this->compileWheres();
        }

        if (!empty($this->groups)) {
            $parts[] = 'GROUP BY ' . implode(', ', array_map([$this, 'wrapColumn'], $this->groups));
        }

        if (!empty($this->orders)) {
            $orderStrings = [];
            foreach ($this->orders as $order) {
                $orderStrings[] = "{$this->wrapColumn($order['column'])} {$order['direction']}";
            }
            $parts[] = 'ORDER BY ' . implode(', ', $orderStrings);
        }

        if ($this->limit !== null) {
            $parts[] = "LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $parts[] = "OFFSET {$this->offset}";
        }

        return implode(' ', $parts);
    }

    protected function compileWheres(): string
    {
        $sql = [];

        foreach ($this->wheres as $index => $where) {
            $prefix = $index === 0 ? '' : "{$where['boolean']} ";

            switch ($where['type']) {
                case 'basic':
                    $sql[] = "{$prefix}{$this->wrapColumn($where['column'])} {$where['operator']} ?";
                    break;
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $operator = $where['not'] ? 'NOT IN' : 'IN';
                    $sql[] = "{$prefix}{$this->wrapColumn($where['column'])} {$operator} ({$placeholders})";
                    break;
                case 'null':
                    $operator = $where['not'] ? 'IS NOT NULL' : 'IS NULL';
                    $sql[] = "{$prefix}{$this->wrapColumn($where['column'])} {$operator}";
                    break;
                case 'between':
                    $operator = $where['not'] ? 'NOT BETWEEN' : 'BETWEEN';
                    $sql[] = "{$prefix}{$this->wrapColumn($where['column'])} {$operator} ? AND ?";
                    break;
                case 'raw':
                    $sql[] = "{$prefix}{$where['sql']}";
                    break;
            }
        }

        return implode(' ', $sql);
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->getBindings());
    }

    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit(1);
        $results = $clone->get();
        return !empty($results) ? $results[0] : null;
    }

    public function find(mixed $id, string $column = 'id'): ?array
    {
        return $this->where($column, $id)->first();
    }

    public function count(string $column = '*'): int
    {
        $clone = clone $this;
        $clone->columns = [new Raw("COUNT({$column}) as aggregate")];
        $clone->orders = [];
        $clone->limit = null;
        $clone->offset = null;

        $result = $this->connection->selectOne($clone->toSql(), $clone->getBindings());
        return (int) ($result['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column): float|int
    {
        return $this->aggregate('SUM', $column);
    }

    public function avg(string $column): float|int
    {
        return $this->aggregate('AVG', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    protected function aggregate(string $function, string $column): mixed
    {
        $clone = clone $this;
        $clone->columns = [new Raw("{$function}({$this->wrapColumn($column)}) as aggregate")];
        $clone->orders = [];
        $clone->limit = null;
        $clone->offset = null;

        $result = $this->connection->selectOne($clone->toSql(), $clone->getBindings());
        return $result['aggregate'] ?? null;
    }

    public function insert(array $values): int|string
    {
        if (empty($values)) {
            return 0;
        }

        $isBatch = isset($values[0]) && is_array($values[0]);
        $rows = $isBatch ? $values : [$values];

        $columns = array_keys($rows[0]);
        $columnList = implode(', ', array_map([$this, 'wrapColumn'], $columns));

        $placeholders = [];
        $bindings = [];

        foreach ($rows as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $col) {
                $rowPlaceholders[] = '?';
                $bindings[] = $row[$col] ?? null;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql = "INSERT INTO {$this->wrapTable($this->table)} ({$columnList}) VALUES " . implode(', ', $placeholders);

        return $this->connection->insert($sql, $bindings);
    }

    public function insertGetId(array $values): int|string
    {
        return $this->insert($values);
    }

    public function update(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        $setClauses = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $setClauses[] = "{$this->wrapColumn($column)} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->wrapTable($this->table)} SET " . implode(', ', $setClauses);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
            $bindings = array_merge($bindings, $this->bindings);
        }

        return $this->connection->update($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->wrapTable($this->table)}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        return $this->connection->delete($sql, $this->bindings);
    }

    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $total = $this->count();

        $items = $this->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    protected function wrapTable(string $table): string
    {
        return "`{$table}`";
    }

    protected function wrapColumn(mixed $column): string
    {
        if ($column instanceof Raw) {
            return $column->getValue();
        }

        $column = (string) $column;

        if ($column === '*') {
            return '*';
        }

        if (str_contains($column, '.')) {
            $segments = explode('.', $column);
            return implode('.', array_map(fn ($s) => $s === '*' ? '*' : "`{$s}`", $segments));
        }

        if (str_contains(strtolower($column), ' as ')) {
            [$col, $alias] = preg_split('/\s+as\s+/i', $column);
            return $this->wrapColumn($col) . " AS `{$alias}`";
        }

        return "`{$column}`";
    }

    public function getConnection(): ConnectionContract
    {
        return $this->connection;
    }
}

