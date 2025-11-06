<?php

declare(strict_types=1);

namespace Miso\Content;

use Miso\Site\SiteConfig;

class ContentLoader
{
    public function __construct(
        private readonly FrontMatterParser $frontMatter,
        private readonly MarkdownConverter $markdown,
    ) {
    }

    /**
     * @return array<string, Collection>
     */
    public function load(string $projectRoot, SiteConfig $config): array
    {
        $contentRoot = $projectRoot . DIRECTORY_SEPARATOR . rtrim($config->path('content'), DIRECTORY_SEPARATOR);

        if (!is_dir($contentRoot)) {
            throw new \RuntimeException("Content directory [$contentRoot] not found.");
        }

        $collectionMap = $this->configuredCollectionPaths($config, $contentRoot);

        $collections = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contentRoot, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !$this->isMarkdown($file->getFilename())) {
                continue;
            }

            $relativePath = ltrim(str_replace($contentRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);

            $collectionName = $this->determineCollection($relativePath, $collectionMap);

            $collectionConfig = $config->collectionConfig($collectionName);

            $document = $this->createDocument($file->getPathname(), $relativePath, $collectionName, $collectionConfig);

            if (!isset($collections[$collectionName])) {
                $collections[$collectionName] = new Collection($collectionName, $collectionConfig);
            }

            $collections[$collectionName]->add($document);
        }

        return $collections;
    }

    private function createDocument(string $absolutePath, string $relativePath, string $collection, array $collectionConfig): Document
    {
        $raw = file_get_contents($absolutePath);

        if ($raw === false) {
            throw new \RuntimeException("Failed to read [$absolutePath].");
        }

        $parsed = $this->frontMatter->parse($raw);

        $frontMatter = $parsed['data'];
        $body = $parsed['body'];
        $html = $this->markdown->convert($body);

        $slug = $frontMatter['slug'] ?? $this->slugFromPath($relativePath);
        $date = $this->resolveDate($frontMatter, $relativePath);

        $frontMatter += [
            'collection' => $collection,
            'slug' => $slug,
        ];

        if ($date) {
            $frontMatter['date'] = $date->format('c');
        }

        return new Document(
            sourcePath: $absolutePath,
            slug: $slug,
            collection: $collection,
            frontMatter: $frontMatter,
            contentHtml: $html,
            contentRaw: $body,
            date: $date
        );
    }

    private function slugFromPath(string $relativePath): string
    {
        $filename = pathinfo($relativePath, PATHINFO_FILENAME);
        $basename = preg_replace('/^\\d{4}-\\d{2}-\\d{2}-/', '', $filename) ?? $filename;

        return $basename === 'index' ? 'index' : trim($basename);
    }

    private function resolveDate(array $frontMatter, string $relativePath): ?\DateTimeImmutable
    {
        if (isset($frontMatter['date'])) {
            try {
                return new \DateTimeImmutable((string)$frontMatter['date']);
            } catch (\Exception) {
                // Ignore invalid date formats; fall back to filename pattern
            }
        }

        if (preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})-/', basename($relativePath), $matches)) {
            return new \DateTimeImmutable(sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]));
        }

        return null;
    }

    private function isMarkdown(string $filename): bool
    {
        return (bool)preg_match('/\\.(md|markdown)$/i', $filename);
    }

    /**
     * @return array<string, string>
     */
    private function configuredCollectionPaths(SiteConfig $config, string $contentRoot): array
    {
        $contentRelativeRoot = trim($config->path('content'), DIRECTORY_SEPARATOR);

        $map = [
            'pages' => '',
        ];

        $collections = $config->get('collections', []);

        foreach ($collections as $name => $details) {
            $path = $details['path'] ?? ($contentRoot . DIRECTORY_SEPARATOR . $name);
            $map[$name] = $this->normalizePath($path, $contentRoot, $contentRelativeRoot);
        }

        return $map;
    }

    private function determineCollection(string $relativePath, array $collectionMap): string
    {
        foreach ($collectionMap as $name => $path) {
            if ($path === '' || $path === '.') {
                continue;
            }

            if (str_starts_with($relativePath, $path . DIRECTORY_SEPARATOR)) {
                return $name;
            }
        }

        $segments = explode(DIRECTORY_SEPARATOR, $relativePath);

        return count($segments) > 1 ? $segments[0] : 'pages';
    }

    private function normalizePath(string $path, string $contentRoot, string $contentRelativeRoot): string
    {
        $relative = ltrim($path, DIRECTORY_SEPARATOR);

        if (str_starts_with($relative, $contentRelativeRoot . DIRECTORY_SEPARATOR)) {
            $relative = substr($relative, strlen($contentRelativeRoot) + 1);
        }

        if (str_starts_with($path, $contentRoot)) {
            $relative = ltrim(str_replace($contentRoot, '', $path), DIRECTORY_SEPARATOR);
        }

        return trim($relative, DIRECTORY_SEPARATOR);
    }
}
