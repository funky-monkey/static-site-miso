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
     * @param list<array{vars: array<string, mixed>, schema: array<string, mixed>, collections: ?list<string>, requires: ?string}> $schemas
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

            // Skip if a required front matter key is absent or empty
            $requires = $schemaConfig['requires'] ?? null;
            if ($requires !== null && empty($page[$requires])) {
                continue;
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

        // Auto-build mainEntity for FAQPage from page.faq front matter
        if (($schema['@type'] ?? '') === 'FAQPage') {
            $schema['mainEntity'] = $this->buildFaqMainEntity($page);
        }

        // Auto-build step for HowTo from page.howto_steps front matter
        if (($schema['@type'] ?? '') === 'HowTo') {
            $schema['step'] = $this->buildHowToSteps($page);
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
     * Builds FAQPage mainEntity from page.faq front matter.
     * Expected front matter format:
     *   faq:
     *     - question: "What is X?"
     *       answer: "X is..."
     *
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function buildFaqMainEntity(array $page): array
    {
        $faqItems = $page['faq'] ?? [];
        if (!is_array($faqItems)) {
            return [];
        }

        $mainEntity = [];
        foreach ($faqItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mainEntity[] = [
                '@type' => 'Question',
                'name'  => (string) ($item['question'] ?? ''),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => (string) ($item['answer'] ?? ''),
                ],
            ];
        }

        return $mainEntity;
    }

    /**
     * Builds HowTo step array from page.howto_steps front matter.
     * Expected front matter format:
     *   howto_steps:
     *     - name: "Install the plugin"
     *       text: "Download and activate the plugin..."
     *
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function buildHowToSteps(array $page): array
    {
        $steps = $page['howto_steps'] ?? [];
        if (!is_array($steps)) {
            return [];
        }

        $howToSteps = [];
        foreach ($steps as $i => $step) {
            if (!is_array($step)) {
                continue;
            }
            $howToSteps[] = [
                '@type'    => 'HowToStep',
                'position' => $i + 1,
                'name'     => (string) ($step['name'] ?? $step['title'] ?? ''),
                'text'     => (string) ($step['text'] ?? $step['description'] ?? ''),
            ];
        }

        return $howToSteps;
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
                'item'     => $baseUrl . '/' . ltrim($url, '/'),
            ];
        }

        return $items;
    }
}
