<?php

declare(strict_types=1);

namespace Rocket\Database\ORM\Relations;

use Rocket\Database\ORM\Model;

class HasOne extends Relation
{
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(Model $parent, string $related, string $foreignKey, string $localKey)
    {
        parent::__construct($parent, $related);
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function getResults(): ?Model
    {
        $parentVal = $this->parent->getAttribute($this->localKey);
        if ($parentVal === null) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $this->related();
        return $instance->newQuery()->where($this->foreignKey, $parentVal)->first();
    }

    public function match(array &$models, array $results, string $relation): void
    {
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->foreignKey);
            $dictionary[$key] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($relation, $dictionary[$key] ?? null);
        }
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }
}

