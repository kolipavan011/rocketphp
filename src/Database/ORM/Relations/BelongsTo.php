<?php

declare(strict_types=1);

namespace Rocket\Database\ORM\Relations;

use Rocket\Database\ORM\Model;

class BelongsTo extends Relation
{
    protected string $foreignKey;
    protected string $ownerKey;

    public function __construct(Model $parent, string $related, string $foreignKey, string $ownerKey)
    {
        parent::__construct($parent, $related);
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    public function getResults(): ?Model
    {
        $foreignVal = $this->parent->getAttribute($this->foreignKey);
        if ($foreignVal === null) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $this->related();
        return $instance->newQuery()->where($this->ownerKey, $foreignVal)->first();
    }

    public function match(array &$models, array $results, string $relation): void
    {
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->ownerKey);
            $dictionary[$key] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            $model->setRelation($relation, $dictionary[$key] ?? null);
        }
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }
}

