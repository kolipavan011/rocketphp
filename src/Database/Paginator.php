<?php

declare(strict_types=1);

namespace Rocket\Database;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Class Paginator
 *
 * Lightweight pagination representation.
 */
class Paginator implements Countable, IteratorAggregate, JsonSerializable
{
    protected array $items;
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected int $lastPage;

    public function __construct(array $items, int $total, int $perPage = 15, int $currentPage = 1)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->lastPage = max(1, (int) ceil($total / $this->perPage));
    }

    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function toArray(): array
    {
        return [
            'data'         => $this->items,
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage,
            'has_more'     => $this->hasMorePages(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

