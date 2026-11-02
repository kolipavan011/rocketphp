<?php

declare(strict_types=1);

namespace Rocket\Database\Schema;

/**
 * Class Blueprint
 *
 * Fluent table schema definition builder.
 */
class Blueprint
{
    protected string $table;

    /**
     * Columns defined on the table.
     *
     * @var array<int, ColumnDefinition>
     */
    protected array $columns = [];

    /**
     * Primary key column names.
     *
     * @var array<int, string>
     */
    protected array $primaryKeys = [];

    /**
     * Unique key column names.
     *
     * @var array<int, string>
     */
    protected array $uniqueKeys = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(string $column = 'id'): ColumnDefinition
    {
        $col = $this->addColumn($column, 'id');
        $col->primary()->autoIncrement();
        $this->primaryKeys[] = $column;
        return $col;
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($column, 'string', $length);
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'text');
    }

    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'integer');
    }

    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'bigInteger');
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'boolean');
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'timestamp');
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(): void
    {
        $this->timestamp('deleted_at')->nullable();
    }

    protected function addColumn(string $name, string $type, ?int $length = null): ColumnDefinition
    {
        $col = new ColumnDefinition($name, $type, $length);
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Compile table creation SQL based on database driver.
     */
    public function toSql(string $driver = 'sqlite'): string
    {
        $colDefs = [];

        foreach ($this->columns as $column) {
            $colDefs[] = $this->compileColumn($column, $driver);
        }

        return "CREATE TABLE `{$this->table}` (" . implode(', ', $colDefs) . ")";
    }

    protected function compileColumn(ColumnDefinition $col, string $driver): string
    {
        $name = "`{$col->name}`";

        if ($col->type === 'id') {
            if ($driver === 'sqlite') {
                return "{$name} INTEGER PRIMARY KEY AUTOINCREMENT";
            }
            if ($driver === 'pgsql') {
                return "{$name} BIGSERIAL PRIMARY KEY";
            }
            return "{$name} BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        }

        $type = match ($col->type) {
            'string'     => $col->length ? "VARCHAR({$col->length})" : 'VARCHAR(255)',
            'text'       => 'TEXT',
            'integer'    => 'INTEGER',
            'bigInteger' => $driver === 'sqlite' ? 'INTEGER' : 'BIGINT',
            'boolean'    => $driver === 'sqlite' ? 'INTEGER' : 'BOOLEAN',
            'timestamp'  => $driver === 'sqlite' ? 'DATETIME' : 'TIMESTAMP',
            default      => 'VARCHAR(255)',
        };

        $sql = "{$name} {$type}";

        if (!$col->nullable) {
            $sql .= ' NOT NULL';
        }

        if ($col->hasDefault) {
            $default = is_bool($col->default) ? ($col->default ? 1 : 0) : $col->default;
            $sql .= " DEFAULT " . (is_numeric($default) ? $default : "'{$default}'");
        }

        if ($col->unique) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    public function getTable(): string
    {
        return $this->table;
    }
}

