<?php

declare(strict_types=1);

namespace Miso\Twig;

use Twig\Environment;
use Twig\Extension\ExtensionInterface;

/**
 * Loads project-level Twig extension plugins from the _plugins/ directory.
 *
 * Any *.php file in _plugins/ that returns an instance of
 * Twig\Extension\ExtensionInterface is automatically registered.
 *
 * Example plugin (_plugins/my-filters.php):
 *
 *   <?php
 *   use Twig\Extension\AbstractExtension;
 *   use Twig\TwigFilter;
 *
 *   return new class extends AbstractExtension {
 *       public function getFilters(): array {
 *           return [
 *               new TwigFilter('shout', fn(string $v) => strtoupper($v) . '!!!'),
 *           ];
 *       }
 *   };
 */
class PluginLoader
{
    public function __construct(
        private readonly string $pluginsDir,
    ) {}

    public function registerPlugins(Environment $twig): void
    {
        if (!is_dir($this->pluginsDir)) {
            return;
        }

        $files = glob($this->pluginsDir . DIRECTORY_SEPARATOR . '*.php') ?: [];

        foreach ($files as $file) {
            $extension = require $file;

            if ($extension instanceof ExtensionInterface) {
                $twig->addExtension($extension);
            }
        }
    }
}
