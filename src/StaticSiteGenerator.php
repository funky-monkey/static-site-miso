<?php

declare(strict_types=1);

namespace Miso;

use Miso\Content\Collection;
use Miso\Content\ContentLoader;
use Miso\Schema\SchemaLoader;
use Miso\Schema\SchemaRenderer;
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
        private readonly SchemaLoader $schemaLoader = new SchemaLoader(),
        private readonly SchemaRenderer|null $schemaRenderer = null,
    ) {
    }

    /**
     * @param array{sitemap?: bool, robots?: bool, llms?: bool, rss?: bool} $flags
     */
    public function build(array $flags = []): void
    {
        $outputDir = $this->absolutePath($this->config->path('output'));
        $this->filesystem->ensureDirectory($outputDir);
        $this->filesystem->emptyDirectory($outputDir);

        $configDir = $this->projectRoot . DIRECTORY_SEPARATOR . '_config';
        $schemas = $this->schemaLoader->load($configDir);

        $pages = [];
        $collections = $this->loader->load($this->projectRoot, $this->config);

        foreach ($collections as $collection) {
            $this->sortCollection($collection);
            $pages = array_merge($pages, $this->renderCollectionItems($collection, $outputDir, $schemas));
            $pages = array_merge($pages, $this->renderCollectionListing($collection, $outputDir));
        }

        $this->copyAssets($outputDir);

        if ($flags['sitemap'] ?? false) {
            $this->generateSitemap($outputDir, $pages);
        }
        if ($flags['robots'] ?? false) {
            $this->generateRobots($outputDir);
        }
        if ($flags['llms'] ?? false) {
            $this->generateLlmsTxt($outputDir, $pages);
        }
        if ($flags['rss'] ?? false) {
            $this->generateRss($outputDir, $pages);
        }
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

    /**
     * @param list<array{vars: array<string, mixed>, schema: array<string, mixed>, collections: ?list<string>}> $schemas
     * @return list<array{permalink: string, title: string, description: string, date: ?\DateTimeImmutable, collection: string, type: string}>
     */
    private function renderCollectionItems(Collection $collection, string $outputDir, array $schemas = []): array
    {
        $siteMeta = $this->config->get('site', []);
        $itemLayout = $collection->itemLayout() ?? ($collection->name === 'pages' ? 'page.twig.html' : 'collection-item.twig.html');

        $menus = $this->config->menus();
        $pages = [];

        foreach ($collection as $document) {
            $layout = $document->frontMatter['layout'] ?? $itemLayout;

            $pageMeta = array_merge($document->frontMatter, ['date' => $document->date]);

            $schemaBlocks = $this->schemaRenderer !== null
                ? $this->schemaRenderer->renderForPage($schemas, is_array($siteMeta) ? $siteMeta : [], $pageMeta, $menus, $collection->name)
                : [];

            try {
                $html = $this->twig->render($layout, [
                    'site' => $siteMeta,
                    'page' => $document->frontMatter,
                    'content' => $document->contentHtml,
                    'collection' => $collection,
                    'menus' => $menus,
                    'schemas' => $schemaBlocks,
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

            $pages[] = [
                'permalink'   => $permalink,
                'title'       => (string) ($document->frontMatter['title'] ?? $document->slug),
                'description' => (string) ($document->frontMatter['excerpt'] ?? $document->frontMatter['description'] ?? $document->frontMatter['meta_description'] ?? ''),
                'date'        => $document->date,
                'collection'  => $collection->name,
                'type'        => 'item',
            ];
        }

        return $pages;
    }

    /**
     * @return list<array{permalink: string, title: string, description: string, date: ?\\DateTimeImmutable, collection: string, type: string}>
     */
    private function renderCollectionListing(Collection $collection, string $outputDir): array
    {
        if ($collection->name === 'pages') {
            return [];
        }

        $listingPages = [];

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

            $listingPages[] = [
                'permalink'   => $permalink,
                'title'       => (string) ($collection->config['title'] ?? $collection->name),
                'description' => '',
                'date'        => null,
                'collection'  => $collection->name,
                'type'        => 'listing',
            ];
        }

        return $listingPages;
    }

    /**
     * @param list<array{permalink: string, title: string, description: string, date: ?\DateTimeImmutable, collection: string, type: string}> $pages
     */
    private function generateSitemap(string $outputDir, array $pages): void
    {
        $baseUrl = rtrim((string) ($this->config->get('site.base_url') ?? ''), '/');
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($pages as $page) {
            $loc = $baseUrl . '/' . ltrim($page['permalink'], '/');
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>';
            if ($page['date'] !== null) {
                $lines[] = '    <lastmod>' . $page['date']->format('Y-m-d') . '</lastmod>';
            }
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';
        $this->filesystem->writeFile($outputDir . DIRECTORY_SEPARATOR . 'sitemap.xml', implode("\n", $lines) . "\n");
    }

    private function generateRobots(string $outputDir): void
    {
        $baseUrl = rtrim((string) ($this->config->get('site.base_url') ?? ''), '/');
        $content = "User-agent: *\nAllow: /\n";
        if ($baseUrl !== '') {
            $content .= "\nSitemap: {$baseUrl}/sitemap.xml\n";
        }
        $this->filesystem->writeFile($outputDir . DIRECTORY_SEPARATOR . 'robots.txt', $content);
    }

    /**
     * @param list<array{permalink: string, title: string, description: string, date: ?\DateTimeImmutable, collection: string, type: string}> $pages
     */
    private function generateLlmsTxt(string $outputDir, array $pages): void
    {
        $site = $this->config->get('site', []);
        $title = (string) ($site['title'] ?? 'Site');
        $description = (string) ($site['description'] ?? '');
        $baseUrl = rtrim((string) ($site['base_url'] ?? ''), '/');

        $lines = ["# {$title}"];
        if ($description !== '') {
            $lines[] = '';
            $lines[] = "> {$description}";
        }
        $lines[] = '';

        $byCollection = [];
        foreach ($pages as $page) {
            $byCollection[$page['collection']][] = $page;
        }

        foreach ($byCollection as $collectionName => $collectionPages) {
            $lines[] = '## ' . ucfirst($collectionName);
            $lines[] = '';
            foreach ($collectionPages as $page) {
                $url = $baseUrl . '/' . ltrim($page['permalink'], '/');
                $desc = $page['description'] !== '' ? ': ' . $page['description'] : '';
                $lines[] = "- [{$page['title']}]({$url}){$desc}";
            }
            $lines[] = '';
        }

        $this->filesystem->writeFile($outputDir . DIRECTORY_SEPARATOR . 'llms.txt', implode("\n", $lines));
    }

    /**
     * Generate an RSS 2.0 feed from all dated collection items.
     * Validates against https://validator.w3.org/feed/
     *
     * @param list<array{permalink: string, title: string, description: string, date: ?\DateTimeImmutable, collection: string, type: string}> $pages
     */
    private function generateRss(string $outputDir, array $pages): void
    {
        $site    = $this->config->get('site', []);
        $title   = htmlspecialchars((string) ($site['title'] ?? 'Feed'), ENT_XML1);
        $baseUrl = rtrim((string) ($site['base_url'] ?? ''), '/');
        $desc    = htmlspecialchars((string) ($site['description'] ?? $title), ENT_XML1);
        $feedUrl = $baseUrl . '/feed.xml';

        // Only dated collection items (not listing pages, not the 'pages' collection)
        $items = array_filter(
            $pages,
            fn ($p) => $p['type'] === 'item' && $p['collection'] !== 'pages' && $p['date'] !== null
        );

        // Newest first
        usort($items, fn ($a, $b) => $b['date']->getTimestamp() <=> $a['date']->getTimestamp());

        $lastBuild = (new \DateTimeImmutable())->format(\DateTimeInterface::RSS);

        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">',
            '  <channel>',
            "    <title>{$title}</title>",
            '    <link>' . htmlspecialchars($baseUrl ?: '/', ENT_XML1) . '</link>',
            "    <description>{$desc}</description>",
            '    <language>en</language>',
            "    <lastBuildDate>{$lastBuild}</lastBuildDate>",
            '    <atom:link href="' . htmlspecialchars($feedUrl, ENT_XML1) . '" rel="self" type="application/rss+xml"/>',
        ];

        foreach ($items as $page) {
            $link    = $baseUrl . '/' . ltrim($page['permalink'], '/');
            $pubDate = $page['date']->format(\DateTimeInterface::RSS);
            $itemTitle = htmlspecialchars($page['title'], ENT_XML1);
            $itemDesc  = '<![CDATA[' . $page['description'] . ']]>';

            $lines[] = '    <item>';
            $lines[] = "      <title>{$itemTitle}</title>";
            $lines[] = '      <link>' . htmlspecialchars($link, ENT_XML1) . '</link>';
            $lines[] = "      <description>{$itemDesc}</description>";
            $lines[] = "      <pubDate>{$pubDate}</pubDate>";
            $lines[] = '      <guid isPermaLink="true">' . htmlspecialchars($link, ENT_XML1) . '</guid>';
            $lines[] = '    </item>';
        }

        $lines[] = '  </channel>';
        $lines[] = '</rss>';

        $this->filesystem->writeFile($outputDir . DIRECTORY_SEPARATOR . 'feed.xml', implode("\n", $lines) . "\n");
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
