<?php

declare(strict_types=1);

namespace Miso\Schema;

use Twig\Environment;

class SchemaRenderer
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * Renders all applicable schemas for a given page and returns an array of JSON-LD strings.
     *
     * @param list<array{vars: array<string, mixed>, schema: array<string, mixed>, collections: ?list<string>}> $schemas
     * @param array<string, mixed> $site
     * @param array<string, mixed> $page
     * @param array<string, mixed> $menus
     * @return list<string>
     */
    public function renderForPage(array $schemas, array $site, array $page, array $menus, ?string $collectionName): array
    {
        $output = [];

        foreach ($schemas as $schemaConfig) {
            // Filter by collection if specified
            if ($schemaConfig['collections'] !== null) {
                if ($collectionName === null || !in_array($collectionName, $schemaConfig['collections'], true)) {
                    continue;
                }
            }

            $json = $this->render($schemaConfig, $site, $page, $menus);

            if ($json !== null) {
                $output[] = $json;
            }
        }

        return $output;
    }

    /**
     * @param array{vars: array<string, mixed>, schema: array<string, mixed>, collections: ?list<string>} $schemaConfig
     * @param array<string, mixed> $site
     * @param array<string, mixed> $page
     * @param array<string, mixed> $menus
     */
    private function render(array $schemaConfig, array $site, array $page, array $menus): ?string
    {
        $context = [
            'vars' => $schemaConfig['vars'],
            'site' => $site,
            'page' => $page,
        ];

        $schema = $schemaConfig['schema'];

        // Auto-build itemListElement for BreadcrumbList from the primary menu
        if (($schema['@type'] ?? '') === 'BreadcrumbList') {
            $schema['itemListElement'] = $this->buildBreadcrumbItems($menus, $context);
        }

        try {
            $rendered = $this->renderValues($schema, $context);
        } catch (\Throwable) {
            return null;
        }

        $json = json_encode($rendered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json !== false ? $json : null;
    }

    private function renderValues(mixed $value, array $context): mixed
    {
        if (is_string($value)) {
            return $this->twig->createTemplate($value)->render($context);
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->renderValues($v, $context);
            }
            return $result;
        }

        return $value;
    }

    /**
     * Builds BreadcrumbList itemListElement entries from the primary menu.
     *
     * @param array<string, mixed> $menus
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function buildBreadcrumbItems(array $menus, array $context): array
    {
        $items = [];
        $primary = $menus['primary'] ?? [];
        $baseUrl = rtrim((string) ($context['vars']['base_url'] ?? $context['site']['base_url'] ?? ''), '/');
        $position = 1;

        foreach ($primary as $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = (string) ($item['url'] ?? $item['href'] ?? '/');
            $label = (string) ($item['label'] ?? $item['title'] ?? '');

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $label,
                'item'     => $this->absoluteUrl($url, $baseUrl),
            ];
        }

        return $items;
    }

    /**
     * Joins a menu URL onto the site base URL. Menu URLs may already be absolute
     * (e.g. an external app link injected via a ${VAR} token), in which case
     * prefixing base_url would produce a malformed "https://site/https://app/..."
     * value, so those are returned untouched.
     */
    private function absoluteUrl(string $url, string $baseUrl): string
    {
        if ($url === '') {
            return $baseUrl . '/';
        }

        // Absolute (https://…, http://…, //cdn…) or non-HTTP scheme (mailto:, tel:)
        if (preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $url) || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return $url;
        }

        // Fragment- or query-only links resolve against the base URL itself
        if ($url[0] === '#' || $url[0] === '?') {
            return $baseUrl . '/' . $url;
        }

        return $baseUrl . '/' . ltrim($url, '/');
    }
}
