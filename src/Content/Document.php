<?php

declare(strict_types=1);

namespace Miso\Content;

/**
 * Represents a Markdown document with metadata.
 */
class Document
{
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $slug,
        public readonly string $collection,
        public readonly array $frontMatter,
        public readonly string $contentHtml,
        public readonly string $contentRaw,
        public readonly \DateTimeImmutable|null $date = null,
    ) {
    }

    public function title(): string
    {
        return $this->frontMatter['title'] ?? ucfirst(str_replace('-', ' ', $this->slug));
    }

    public function permalink(string $base = '', ?string $permalinkPattern = null): string
    {
        $base = rtrim($base, '/');

        if ($permalinkPattern) {
            $replacements = [
                '{collection}' => $this->collection,
                '{slug}' => $this->slug,
                '{filename}' => $this->slug,
                '{year}' => $this->date?->format('Y') ?? '',
                '{month}' => $this->date?->format('m') ?? '',
                '{day}' => $this->date?->format('d') ?? '',
            ];

            $path = strtr($permalinkPattern, $replacements);
        } else {
            $segments = array_filter([$this->collection !== 'pages' ? $this->collection : null, $this->slug]);
            $path = implode('/', $segments);
        }

        return $base . '/' . trim($path, '/') . '/';
    }
}
