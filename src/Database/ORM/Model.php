<?php

declare(strict_types=1);

namespace Rocket\Database\ORM;

use ArrayAccess;
use JsonSerializable;
use Rocket\Database\Contracts\ConnectionContract;
use Rocket\Database\ORM\Relations\BelongsTo;
use Rocket\Database\ORM\Relations\HasMany;
use Rocket\Database\ORM\Relations\HasOne;
use Rocket\Database\ORM\Relations\Relation;
use Rocket\Facades\DB;
use InvalidArgumentException;

/**
 * Class Model
 *
 * Lightweight, high-performance Active Record ORM base class.
 */
abstract class Model implements ArrayAccess, JsonSerializable
{
    /**
     * Database table name.
     */
    protected ?string $table = null;

    /**
     * Primary key column.
     */
    protected string $primaryKey = 'id';

    /**
     * Indicates if IDs are auto-incrementing.
     */
    protected bool $incrementing = true;

    /**
     * Database connection name.
     */
    protected ?string $connection = null;

    /**
     * Indicates whether model manages created_at and updated_at timestamps.
     */
    protected bool $timestamps = true;

    /**
     * Attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [];

    /**
     * Attributes that are guarded from mass assignment.
     *
     * @var array<int, string>
     */
    protected array $guarded = ['*'];

    /**
     * Model attribute storage.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Original attributes when loaded from database.
     *
     * @var array<string, mixed>
     */
    protected array $original = [];

    /**
     * Loaded relationship cache.
     *
     * @var array<string, mixed>
     */
    protected array $relations = [];

    /**
     * Indicates if model exists in database.
     */
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Get database table name.
     */
    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        // Derive pluralized snake_case from class name (e.g. BlogPost -> blog_posts)
        $shortClass = basename(str_replace('\\', '/', static::class));
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortClass));
        return $snake . 's';
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getId(): mixed
    {
        return $this->getAttribute($this->primaryKey);
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Determine if model uses SoftDeletes trait.
     */
    public function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class), true);
    }

    /**
     * Get database connection.
     */
    public function getConnection(): ConnectionContract
    {
        return DB::connection($this->connection);
    }

    /**
     * Create a new query builder for this model.
     */
    public function newQuery(bool $withSoftDeletes = false): ModelBuilder
    {
        $builder = $this->getConnection()->table($this->getTable());
        return new ModelBuilder($builder, $this, $withSoftDeletes);
    }

    /**
     * Create a model instance hydrated from database row.
     */
    public function newFromBuilder(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->original = $attributes;
        $model->exists = true;
        return $model;
    }

    /**
     * Mass assign attributes with protection.
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    /**
     * Determine if attribute is mass assignable.
     */
    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        if ($this->guarded === ['*'] && empty($this->fillable)) {
            return false;
        }

        return !in_array($key, $this->guarded, true);
    }

    /**
     * Save the model to database (INSERT or UPDATE).
     */
    public function save(): bool
    {
        $now = date('Y-m-d H:i:s');

        if ($this->exists) {
            // Update existing record
            $dirty = $this->getDirty();
            if (empty($dirty)) {
                return true;
            }

            if ($this->timestamps) {
                $dirty['updated_at'] = $now;
                $this->attributes['updated_at'] = $now;
            }

            $affected = $this->newQuery(withSoftDeletes: true)->where($this->primaryKey, $this->getId())->update($dirty);
            $this->original = $this->attributes;
            return $affected >= 0;
        }

        // Insert new record
        if ($this->timestamps) {
            if (!isset($this->attributes['created_at'])) {
                $this->attributes['created_at'] = $now;
            }
            if (!isset($this->attributes['updated_at'])) {
                $this->attributes['updated_at'] = $now;
            }
        }

        $id = $this->getConnection()->table($this->getTable())->insertGetId($this->attributes);

        if ($this->incrementing && $id > 0) {
            $this->attributes[$this->primaryKey] = $id;
        }

        $this->exists = true;
        $this->original = $this->attributes;
        return true;
    }

    /**
     * Delete the model from database.
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->usesSoftDeletes()) {
            $this->setAttribute('deleted_at', date('Y-m-d H:i:s'));
            return $this->save();
        }

        $this->newQuery()->where($this->primaryKey, $this->getId())->delete();
        $this->exists = false;
        return true;
    }

    /**
     * Get dirty (modified) attributes.
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    /**
     * Attribute accessors and mutators.
     */
    public function getAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Check if dynamic relationship method exists
        if (method_exists($this, $key)) {
            $relation = $this->$key();
            if ($relation instanceof Relation) {
                $results = $relation->getResults();
                $this->setRelation($key, $results);
                return $results;
            }
        }

        return null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function setRelation(string $relation, mixed $value): void
    {
        $this->relations[$relation] = $value;
    }

    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Relationship definitions.
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->primaryKey;
        return new HasOne($this, $related, $foreignKey, $localKey);
    }

    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->primaryKey;
        return new HasMany($this, $related, $foreignKey, $localKey);
    }

    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        /** @var Model $instance */
        $instance = new $related();
        $foreignKey = $foreignKey ?? $instance->getForeignKey();
        $ownerKey = $ownerKey ?? $instance->getPrimaryKey();
        return new BelongsTo($this, $related, $foreignKey, $ownerKey);
    }

    public function getForeignKey(): string
    {
        $shortClass = basename(str_replace('\\', '/', static::class));
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortClass)) . '_id';
    }

    /**
     * Magic property access.
     */
    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->getAttribute($name) !== null;
    }

    public function __unset(string $name): void
    {
        unset($this->attributes[$name], $this->relations[$name]);
    }

    /**
     * ArrayAccess implementation.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->__unset((string) $offset);
    }

    public function toArray(): array
    {
        $array = $this->attributes;

        foreach ($this->relations as $key => $value) {
            if ($value instanceof Model) {
                $array[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $array[$key] = array_map(fn ($item) => $item instanceof Model ? $item->toArray() : $item, $value);
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Static query constructor methods.
     */
    public static function query(): ModelBuilder
    {
        return (new static())->newQuery();
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->find($id);
    }

    public static function findOrFail(mixed $id): static
    {
        return static::query()->findOrFail($id);
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static())->newQuery()->$method(...$parameters);
    }
}

if (!function_exists('Rocket\Database\ORM\class_uses_recursive')) {
    function class_uses_recursive(string|object $class): array
    {
        $results = [];
        $classes = array_merge([$class => $class], class_parents($class) ?: []);

        foreach ($classes as $c) {
            $traits = class_uses($c);
            if ($traits) {
                $results = array_merge($results, $traits);
            }
        }

        return array_unique($results);
    }
}
