<?php

declare(strict_types=1);

namespace Rocket\Database\ORM;

/**
 * Trait SoftDeletes
 *
 * Implements soft deleting logic for models.
 */
trait SoftDeletes
{
    /**
     * Boot the soft deleting trait.
     */
    public static function bootSoftDeletes(): void
    {
        // Trait hook
    }

    /**
     * Restore a soft-deleted model.
     */
    public function restore(): bool
    {
        $this->setAttribute('deleted_at', null);
        return $this->save();
    }

    /**
     * Force a hard delete on a soft-deleted model.
     */
    public function forceDelete(): int
    {
        return $this->newQuery(true)->where($this->getPrimaryKey(), $this->getId())->delete();
    }

    /**
     * Determine if the model is currently trashed.
     */
    public function trashed(): bool
    {
        return $this->getAttribute('deleted_at') !== null;
    }
}

