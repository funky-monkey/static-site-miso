<?php

/**
 * Example Miso plugin — add your own Twig filters and functions here.
 *
 * This file is auto-loaded by Miso on every build. Return any class that
 * extends Twig\Extension\AbstractExtension and it will be registered.
 *
 * Usage in templates once registered:
 *   {{ "hello world" | shout }}   → "HELLO WORLD!!!"
 *   {{ items | first_n(3) }}      → first 3 items
 *
 * Rename this file (e.g. my-filters.php) and replace the examples below
 * with your own filters. Add multiple files if you prefer to group by concern.
 *
 * Documentation: https://twig.symfony.com/doc/3.x/advanced.html#filters
 */

declare(strict_types=1);

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

return new class extends AbstractExtension {
    public function getFilters(): array
    {
        return [
            // Uppercase a string and append exclamation marks.
            new TwigFilter('shout', fn (string $value) => strtoupper($value) . '!!!'),
        ];
    }
};
