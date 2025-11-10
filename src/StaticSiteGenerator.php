<?php

declare(strict_types=1);

namespace Miso;

use Miso\Content\Collection;
use Miso\Content\ContentLoader;
use Miso\Site\SiteConfig;
use Miso\Support\Filesystem;
use Twig\Environment;

class StaticSiteGenerator
{
    public function __construct(
        private readonly SiteConfig $config,
        private readonly ContentLoader $loader,
        private readonly Filesystem $filesystem,
        private readonly Environment $twig,
        private readonly string $projectRoot,
    ) {
    }

    public function build(): void
    {
        $outputDir = $this->absolutePath($this->config->path('output'));
        $this->filesystem->ensureDirectory($outputDir);
        $this->filesystem->emptyDirectory($outputDir);

        $collections = $this->loader->load($this->projectRoot, $this->config);

        foreach ($collections as $collection) {
            $this->sortCollection($collection);
            $this->renderCollectionItems($collection, $outputDir);
            $this->renderCollectionListing($collection, $outputDir);
        }

        $this->copyAssets($outputDir);
    }

    private function sortCollection(Collection $collection): void
    {
        $collection->sort(static function ($a, $b) {
            $aDate = $a->date?->getTimestamp() ?? 0;
            $bDate = $b->date?->getTimestamp() ?? 0;

            if ($aDate === $bDate) {
                return strcmp($b->slug, $a->slug);
            }

            return $bDate <=> $aDate;
        });
    }

    private function renderCollectionItems(Collection $collection, string $outputDir): void
    {
        $siteMeta = $this->config->get('site', []);
        $itemLayout = $collection->itemLayout() ?? ($collection->name === 'pages' ? 'page.twig.html' : 'collection-item.twig.html');

        $menus = $this->config->menus();

        foreach ($collection as $document) {
            $layout = $document->frontMatter['layout'] ?? $itemLayout;

            try {
                $html = $this->twig->render($layout, [
                    'site' => $siteMeta,
                    'page' => $document->frontMatter,
                    'content' => $document->contentHtml,
                    'collection' => $collection,
                    'menus' => $menus,
                ]);
            } catch (\Twig\Error\LoaderError $e) {
                $message = sprintf(
                    'Failed rendering "%s" using layout "%s": %s. Update the file front matter (layout key) or collection layout to reference an existing template (for example post.twig.html).',
                    $document->sourcePath,
                    $layout,
                    $e->getMessage()
                );
                throw new \RuntimeException($message, 0, $e);
            }

            $permalink = $document->frontMatter['permalink'] ?? $document->permalink(
                base: '',
                permalinkPattern: $collection->permalinkPattern()
            );

            $path = $this->destinationPathFromPermalink($outputDir, $permalink, $document->slug === 'index' && $collection->name === 'pages');

            $this->filesystem->writeFile($path, $html);
        }
    }

    private function renderCollectionListing(Collection $collection, string $outputDir): void
    {
        if ($collection->name === 'pages') {
            return;
        }

        $listingLayout = $collection->listingLayout() ?? 'collection.twig.html';
        $siteMeta = $this->config->get('site', []);

        $paginator = $collection->paginate();
        $totalPages = $paginator->pages();

        $menus = $this->config->menus();

        for ($page = 1; $page <= $totalPages; $page++) {
            $documents = $paginator->page($page);

            try {
                $html = $this->twig->render($listingLayout, [
                    'site' => $siteMeta,
                    'collection' => $collection,
                    'documents' => $documents,
                    'menus' => $menus,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $paginator->perPage(),
                        'total_pages' => $totalPages,
                        'next_page' => $page < $totalPages ? $page + 1 : null,
                        'previous_page' => $page > 1 ? $page - 1 : null,
                    ],
                ]);
            } catch (\Twig\Error\LoaderError $e) {
                $message = sprintf(
                    'Failed rendering listing for collection "%s" (page %d) using layout "%s": %s. Check the collection list_layout setting in _config/site.yaml.',
                    $collection->name,
                    $page,
                    $listingLayout,
                    $e->getMessage()
                );
                throw new \RuntimeException($message, 0, $e);
            }

            $permalink = $collection->config['list_permalink'] ?? '/' . $collection->name . '/';

            if ($page > 1) {
                $permalink = rtrim($permalink, '/') . '/page/' . $page . '/';
            }

            $path = $this->destinationPathFromPermalink($outputDir, $permalink, false);

            $this->filesystem->writeFile($path, $html);
        }
    }

    private function copyAssets(string $outputDir): void
    {
        foreach ($this->config->assetDirectories() as $relative) {
            $source = $this->absolutePath($relative);

            if (!is_dir($source)) {
                continue;
            }

            $destination = $outputDir . DIRECTORY_SEPARATOR . $relative;
            $this->filesystem->copyDirectory($source, $destination);
        }
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . rtrim($relativePath, DIRECTORY_SEPARATOR);
    }

    private function destinationPathFromPermalink(string $outputDir, string $permalink, bool $isRootIndex): string
    {
        $path = trim($permalink, '/');

        if ($path === '' || $isRootIndex) {
            return $outputDir . DIRECTORY_SEPARATOR . 'index.html';
        }

        return $outputDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path) . DIRECTORY_SEPARATOR . 'index.html';
    }
}
