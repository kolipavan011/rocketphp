<?php

declare(strict_types=1);

namespace Rocket\Database\ORM\Relations;

use Rocket\Database\ORM\Model;
use Rocket\Database\Query\Builder;

abstract class Relation
{
    protected Model $parent;
    protected string $related;
    protected ?Builder $query = null;

    public function __construct(Model $parent, string $related)
    {
        $this->parent = $parent;
        $this->related = $related;
    }

    abstract public function getResults(): mixed;
    abstract public function match(array &$models, array $results, string $relation): void;

    /**
     * Get related model instance.
     */
    public function getRelated(): Model
    {
        return new $this->related();
    }

    public function getParent(): Model
    {
        return $this->parent;
    }
}

