<?php

declare(strict_types=1);

namespace Miso\Twig;

use League\CommonMark\CommonMarkConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Built-in Twig filters shipped with Miso.
 *
 * Filters that depend on site context (base_url, project root) are injected
 * via the constructor so they work correctly without global state.
 */
class CoreFiltersExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $baseUrl = '',
        private readonly string $projectRoot = '',
    ) {}

    public function getFilters(): array
    {
        return [
            // --- String helpers ---
            new TwigFilter('slugify',        $this->slugify(...)),
            new TwigFilter('truncate_words', $this->truncateWords(...)),
            new TwigFilter('strip_html',     $this->stripHtml(...)),
            new TwigFilter('widont',         $this->widont(...),     ['is_safe' => ['html']]),
            new TwigFilter('titlecase',      $this->titlecase(...)),
            new TwigFilter('encode_email',   $this->encodeEmail(...), ['is_safe' => ['html']]),

            // --- Content helpers ---
            new TwigFilter('reading_time',   $this->readingTime(...)),
            new TwigFilter('excerpt',        $this->excerpt(...)),
            new TwigFilter('markdown',       $this->markdown(...),   ['is_safe' => ['html']]),

            // --- Date helpers ---
            new TwigFilter('date_format',    $this->dateFormat(...)),
            new TwigFilter('ago',            $this->ago(...)),

            // --- URL helpers ---
            new TwigFilter('relative_url',   $this->relativeUrl(...)),
            new TwigFilter('absolute_url',   $this->absoluteUrl(...)),
            new TwigFilter('cache_bust',     $this->cacheBust(...)),

            // --- Number helpers ---
            new TwigFilter('number_format',  $this->numberFormat(...)),
            new TwigFilter('pluralize',      $this->pluralize(...)),

            // --- Collection helpers ---
            new TwigFilter('where',          $this->where(...)),
            new TwigFilter('sort_by',        $this->sortBy(...)),
            new TwigFilter('limit',          $this->limit(...)),
            new TwigFilter('group_by',       $this->groupBy(...)),
        ];
    }

    // -------------------------------------------------------------------------
    // String helpers
    // -------------------------------------------------------------------------

    /**
     * Convert a string to a URL-safe slug.
     * "Hello World!" → "hello-world"
     */
    public function slugify(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9\s-]/u', '', $value) ?? $value;
        $value = preg_replace('/[\s-]+/', '-', $value) ?? $value;
        return trim($value, '-');
    }

    /**
     * Truncate to N words, appending an ellipsis if truncated.
     * {{ post.content | strip_html | truncate_words(25) }}
     */
    public function truncateWords(string $value, int $words = 20, string $ellipsis = '…'): string
    {
        $clean = strip_tags($value);
        $parts = preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) <= $words) {
            return $clean;
        }
        return implode(' ', array_slice($parts, 0, $words)) . $ellipsis;
    }

    /**
     * Strip all HTML tags, leaving plain text.
     */
    public function stripHtml(string $value): string
    {
        return strip_tags($value);
    }

    /**
     * Prevent a typographic widow by replacing the last space with &nbsp;
     * so the last word never sits alone on its own line.
     */
    public function widont(string $value): string
    {
        return preg_replace('/\s+(\S+)\s*$/u', '&nbsp;$1', $value) ?? $value;
    }

    /**
     * Convert a string to Title Case, respecting minor words.
     * "the quick brown fox" → "The Quick Brown Fox"
     */
    public function titlecase(string $value): string
    {
        $minor = ['a', 'an', 'the', 'and', 'but', 'or', 'for', 'nor', 'on', 'at', 'to', 'by', 'in', 'of', 'up', 'as'];
        $words = explode(' ', mb_strtolower($value));
        $last  = count($words) - 1;

        foreach ($words as $i => $word) {
            $words[$i] = ($i === 0 || $i === $last || !in_array($word, $minor, true))
                ? mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1)
                : $word;
        }

        return implode(' ', $words);
    }

    /**
     * HTML-entity encode every character of an email address to deter scrapers.
     * Renders correctly in browsers; most simple scrapers skip it.
     */
    public function encodeEmail(string $email): string
    {
        $out = '';
        for ($i = 0, $len = strlen($email); $i < $len; $i++) {
            $out .= '&#' . ord($email[$i]) . ';';
        }
        return $out;
    }

    // -------------------------------------------------------------------------
    // Content helpers
    // -------------------------------------------------------------------------

    /**
     * Estimate reading time based on average words-per-minute.
     * {{ post.content | reading_time }} → "4 min read"
     */
    public function readingTime(string $value, int $wpm = 200): string
    {
        $words   = str_word_count(strip_tags($value));
        $minutes = max(1, (int) round($words / $wpm));
        return $minutes . ' min read';
    }

    /**
     * Extract the first N sentences from a block of HTML/Markdown content.
     * Strips tags before extracting so you get clean plain-text output.
     */
    public function excerpt(string $value, int $sentences = 1): string
    {
        $text = strip_tags($value);
        preg_match_all('/[^.!?]+[.!?]+/u', $text, $matches);

        if (empty($matches[0])) {
            return trim($text);
        }

        return trim(implode(' ', array_slice($matches[0], 0, $sentences)));
    }

    /**
     * Render an inline Markdown string to HTML.
     * Useful for frontmatter fields that contain lightweight Markdown.
     * {{ page.description | markdown }}
     */
    public function markdown(string $value): string
    {
        static $converter = null;
        $converter ??= new CommonMarkConverter(['html_input' => 'allow']);
        return $converter->convert($value)->getContent();
    }

    // -------------------------------------------------------------------------
    // Date helpers
    // -------------------------------------------------------------------------

    /**
     * Format a date using strftime-style % codes (familiar from Jekyll/Liquid)
     * OR standard PHP date() codes — both work.
     *
     * {{ page.date | date_format("%B %d, %Y") }} → "March 30, 2026"
     * {{ page.date | date_format("F j, Y") }}    → "March 30, 2026"
     */
    public function dateFormat(\DateTimeInterface|string|int $value, string $format): string
    {
        $strftimeMap = [
            '%Y' => 'Y', '%y' => 'y',
            '%m' => 'm', '%d' => 'd', '%e' => 'j',
            '%B' => 'F', '%b' => 'M',
            '%A' => 'l', '%a' => 'D',
            '%H' => 'H', '%I' => 'h',
            '%M' => 'i', '%S' => 's',
            '%p' => 'A', '%P' => 'a',
            '%j' => 'z',
        ];

        $phpFormat = strtr($format, $strftimeMap);

        if ($value instanceof \DateTimeInterface) {
            return $value->format($phpFormat);
        }

        $dt = new \DateTime(is_numeric($value) ? '@' . $value : (string) $value);
        return $dt->format($phpFormat);
    }

    /**
     * Return a human-readable relative time string.
     * {{ post.date | ago }} → "3 days ago"
     */
    public function ago(\DateTimeInterface|string|int $value): string
    {
        if (is_int($value)) {
            $dt = new \DateTime('@' . $value);
        } elseif (is_string($value)) {
            $dt = new \DateTime($value);
        } else {
            $dt = $value;
        }

        $diff = (new \DateTime())->diff($dt);

        return match (true) {
            $diff->y > 0              => $diff->y . ' year'   . ($diff->y > 1 ? 's' : '') . ' ago',
            $diff->m > 0              => $diff->m . ' month'  . ($diff->m > 1 ? 's' : '') . ' ago',
            (int)($diff->d / 7) > 0  => (int)($diff->d / 7) . ' week' . ((int)($diff->d / 7) > 1 ? 's' : '') . ' ago',
            $diff->d > 0              => $diff->d . ' day'    . ($diff->d > 1 ? 's' : '') . ' ago',
            $diff->h > 0              => $diff->h . ' hour'   . ($diff->h > 1 ? 's' : '') . ' ago',
            $diff->i > 0              => $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago',
            default                   => 'just now',
        };
    }

    // -------------------------------------------------------------------------
    // URL helpers
    // -------------------------------------------------------------------------

    /**
     * Prepend site.base_url to a root-relative path.
     * Leaves already-absolute URLs untouched.
     * {{ "/about/" | relative_url }} → "https://example.com/about/"
     */
    public function relativeUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Alias of relative_url — ensures a full absolute URL including scheme and host.
     */
    public function absoluteUrl(string $path): string
    {
        return $this->relativeUrl($path);
    }

    /**
     * Append a short content hash to an asset path for cache busting.
     * Falls back to the original path if the file doesn't exist on disk.
     * {{ "/css/main.css" | cache_bust }} → "/css/main.css?v=a3f92c1b"
     */
    public function cacheBust(string $path): string
    {
        $relative = ltrim($path, '/');
        $fullPath = rtrim($this->projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (is_file($fullPath)) {
            $hash = substr(md5_file($fullPath) ?: '', 0, 8);
            return $path . '?v=' . $hash;
        }

        return $path;
    }

    // -------------------------------------------------------------------------
    // Number helpers
    // -------------------------------------------------------------------------

    /**
     * Format a number with thousands separators.
     * {{ 14999 | number_format }} → "14,999"
     */
    public function numberFormat(
        int|float $value,
        int $decimals = 0,
        string $decPoint = '.',
        string $thousandsSep = ','
    ): string {
        return number_format($value, $decimals, $decPoint, $thousandsSep);
    }

    /**
     * Choose singular or plural form based on count, with the count prepended.
     * {{ total | pluralize("post", "posts") }} → "1 post" or "3 posts"
     */
    public function pluralize(int|float $count, string $singular, string $plural = ''): string
    {
        if ($plural === '') {
            $plural = $singular . 's';
        }
        return $count . ' ' . ($count == 1 ? $singular : $plural);
    }

    // -------------------------------------------------------------------------
    // Collection helpers
    // -------------------------------------------------------------------------

    /**
     * Filter an array of pages/items by a frontmatter field value.
     * {{ collections.blog | where("category", "news") }}
     */
    public function where(array $items, string $field, mixed $value): array
    {
        // loose comparison intentional — frontmatter values are often strings
        return array_values(
            array_filter($items, fn ($item) => $this->getField($item, $field) == $value)
        );
    }

    /**
     * Sort an array of pages/items by a frontmatter field.
     * {{ collections.blog | sort_by("date", "desc") }}
     */
    public function sortBy(array $items, string $field, string $direction = 'asc'): array
    {
        usort($items, function ($a, $b) use ($field, $direction) {
            $cmp = $this->getField($a, $field) <=> $this->getField($b, $field);
            return $direction === 'desc' ? -$cmp : $cmp;
        });
        return $items;
    }

    /**
     * Return the first N items from an array.
     * {{ collections.blog | limit(3) }}
     */
    public function limit(array $items, int $count): array
    {
        return array_slice($items, 0, $count);
    }

    /**
     * Group an array of pages/items by a frontmatter field.
     * Returns an associative array keyed by the field value.
     * {{ collections.blog | group_by("category") }}
     */
    public function groupBy(array $items, string $field): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key           = (string) ($this->getField($item, $field) ?? '');
            $groups[$key][] = $item;
        }
        return $groups;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /** Read a property from an array element or object. */
    private function getField(mixed $item, string $field): mixed
    {
        if (is_array($item)) {
            return $item[$field] ?? null;
        }
        if (is_object($item)) {
            return $item->$field ?? null;
        }
        return null;
    }
}
