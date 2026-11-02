<?php

declare(strict_types=1);

namespace Rocket\Database\ORM;

use Rocket\Database\ORM\Relations\BelongsTo;
use Rocket\Database\ORM\Relations\HasMany;
use Rocket\Database\ORM\Relations\HasOne;
use Rocket\Database\Paginator;
use Rocket\Database\Query\Builder;

/**
 * Class ModelBuilder
 *
 * ORM Query Builder that hydrates active record models and executes eager loading.
 */
class ModelBuilder
{
    protected Builder $query;
    protected Model $model;
    protected array $eagerLoad = [];
    protected bool $withTrashed = false;
    protected bool $onlyTrashed = false;

    public function __construct(Builder $query, Model $model, bool $withSoftDeletes = false)
    {
        $this->query = $query;
        $this->model = $model;

        if (!$withSoftDeletes && $this->model->usesSoftDeletes()) {
            $this->query->whereNull('deleted_at');
        }
    }

    /**
     * Include relationships for eager loading to prevent N+1 queries.
     */
    public function with(array|string ...$relations): static
    {
        foreach ($relations as $relation) {
            if (is_array($relation)) {
                $this->eagerLoad = array_merge($this->eagerLoad, $relation);
            } else {
                $this->eagerLoad[] = $relation;
            }
        }
        return $this;
    }

    public function withTrashed(): static
    {
        $this->withTrashed = true;
        // Rebuild query without deleted_at whereNull
        return $this;
    }

    public function onlyTrashed(): static
    {
        $this->onlyTrashed = true;
        $this->query->whereNotNull('deleted_at');
        return $this;
    }

    public function get(): array
    {
        $records = $this->query->get();
        $models = [];

        foreach ($records as $record) {
            $models[] = $this->model->newFromBuilder($record);
        }

        if (!empty($models) && !empty($this->eagerLoad)) {
            $this->eagerLoadRelations($models);
        }

        return $models;
    }

    public function first(): ?Model
    {
        $record = $this->query->first();
        if ($record === null) {
            return null;
        }

        $model = $this->model->newFromBuilder($record);

        if (!empty($this->eagerLoad)) {
            $models = [$model];
            $this->eagerLoadRelations($models);
        }

        return $model;
    }

    public function find(mixed $id): ?Model
    {
        return $this->where($this->model->getPrimaryKey(), $id)->first();
    }

    public function findOrFail(mixed $id): Model
    {
        $result = $this->find($id);
        if ($result === null) {
            throw new ModelNotFoundException("No query results for model [" . get_class($this->model) . "] with ID {$id}.");
        }
        return $result;
    }

    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $paginator = $this->query->paginate($perPage, $page);
        $models = [];

        foreach ($paginator->items() as $record) {
            $models[] = $this->model->newFromBuilder($record);
        }

        if (!empty($models) && !empty($this->eagerLoad)) {
            $this->eagerLoadRelations($models);
        }

        return new Paginator($models, $paginator->total(), $perPage, $page);
    }

    /**
     * Eager load relationships using batch WHERE IN queries (preventing N+1).
     */
    protected function eagerLoadRelations(array &$models): void
    {
        foreach ($this->eagerLoad as $relationName) {
            if (!method_exists($this->model, $relationName)) {
                continue;
            }

            $relation = $this->model->$relationName();

            if ($relation instanceof HasMany || $relation instanceof HasOne) {
                $foreignKey = $relation->getForeignKey();
                $localKey = $relation->getLocalKey();

                $parentKeys = array_values(array_unique(array_filter(array_map(
                    fn ($m) => $m->getAttribute($localKey),
                    $models
                ))));

                if (!empty($parentKeys)) {
                    $relatedModel = $relation->getRelated();
                    $results = $relatedModel->newQuery()
                        ->whereIn($foreignKey, $parentKeys)
                        ->get();

                    $relation->match($models, $results, $relationName);
                }
            } elseif ($relation instanceof BelongsTo) {
                $foreignKey = $relation->getForeignKey();
                $ownerKey = $relation->getOwnerKey();

                $foreignKeys = array_values(array_unique(array_filter(array_map(
                    fn ($m) => $m->getAttribute($foreignKey),
                    $models
                ))));

                if (!empty($foreignKeys)) {
                    $relatedModel = $relation->getRelated();
                    $results = $relatedModel->newQuery()
                        ->whereIn($ownerKey, $foreignKeys)
                        ->get();

                    $relation->match($models, $results, $relationName);
                }
            }
        }
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Forward calls to underlying Query Builder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}
