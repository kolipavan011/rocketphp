<?php

declare(strict_types=1);

namespace Rocket\Facades;

use Rocket\Database\Contracts\ConnectionContract;
use Rocket\Database\Query\Builder;
use Rocket\Database\Raw;

/**
 * Class DB
 *
 * Facade for Database Manager.
 *
 * @method static ConnectionContract connection(?string $name = null)
 * @method static Builder table(string $table)
 * @method static array select(string $query, array $bindings = [])
 * @method static ?array selectOne(string $query, array $bindings = [])
 * @method static int|string insert(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static bool statement(string $query, array $bindings = [])
 * @method static mixed transaction(\Closure $callback)
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'db';
    }

    public static function raw(string $value): Raw
    {
        return new Raw($value);
    }
}

