<?php

declare(strict_types=1);

namespace Miso\Support;

/**
 * Simple paginator for arrays.
 */
class Paginator implements \IteratorAggregate
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        private array $items,
        private int $perPage
    ) {
        if ($this->perPage < 1) {
            throw new \InvalidArgumentException('Paginator perPage must be at least 1.');
        }
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function pages(): int
    {
        return (int)ceil(count($this->items) / $this->perPage);
    }

    /**
     * @return array<int, mixed>
     */
    public function page(int $number): array
    {
        if ($number < 1) {
            $number = 1;
        }

        $offset = ($number - 1) * $this->perPage;

        return array_slice($this->items, $offset, $this->perPage);
    }

    public function perPage(): int
    {
        return $this->perPage;
    }
}
