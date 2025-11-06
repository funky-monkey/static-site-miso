<?php

declare(strict_types=1);

namespace Miso\Content;

use Miso\Support\Paginator;

/**
 * A logical grouping of documents.
 *
 * @implements \IteratorAggregate<int, Document>
 */
class Collection implements \IteratorAggregate
{
    /** @var Document[] */
    private array $documents = [];

    public function __construct(
        public readonly string $name,
        public readonly array $config = [],
    ) {
    }

    public function add(Document $document): void
    {
        $this->documents[] = $document;
    }

    /**
     * @return Document[]
     */
    public function documents(): array
    {
        return $this->documents;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->documents);
    }

    public function sort(callable $comparator): void
    {
        usort($this->documents, $comparator);
    }

    public function paginate(?int $perPage = null): Paginator
    {
        $perPage ??= (int)($this->config['pagination']['per_page'] ?? 10);

        return new Paginator($this->documents, $perPage);
    }

    public function itemLayout(): ?string
    {
        return $this->config['layout'] ?? null;
    }

    public function listingLayout(): ?string
    {
        return $this->config['list_layout'] ?? null;
    }

    public function permalinkPattern(): ?string
    {
        return $this->config['permalink'] ?? null;
    }
}
