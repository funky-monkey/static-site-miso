<?php

declare(strict_types=1);

namespace Miso\Site;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads and provides access to generator configuration.
 */
class SiteConfig
{
    private array $config;
    private array $menus;
    private array $env;

    private function __construct(array $config, array $menus = [], array $env = [])
    {
        $this->config = $config;
        $this->menus  = $menus;
        $this->env    = $env;
    }

    public static function load(string $projectRoot, ?string $configPath = null, ?string $envName = null): self
    {
        $configFile = $configPath
            ? $configPath
            : $projectRoot . DIRECTORY_SEPARATOR . '_config' . DIRECTORY_SEPARATOR . 'site.yaml';

        $defaults = [
            'site' => [
                'title' => 'My Site',
                'base_url' => '',
                'description' => '',
                'seo' => [
                    'author' => '',
                    'default_keywords' => [],
                    'canonical' => '',
                    'open_graph' => [
                        'title' => null,
                        'description' => null,
                        'image' => null,
                        'url' => null,
                        'type' => 'website',
                        'locale' => 'en_US',
                        'site_name' => null,
                    ],
                    'twitter' => [
                        'card' => 'summary_large_image',
                        'site' => '',
                        'creator' => '',
                        'title' => null,
                        'description' => null,
                        'image' => null,
                    ],
                ],
            ],
            'paths' => [
                'content' => 'content',
                'templates' => 'templates',
                'output' => '_site',
                'assets' => ['css'],
            ],
            'collections' => [],
        ];

        if (is_file($configFile)) {
            $loaded = Yaml::parseFile($configFile) ?? [];
            $config = static::mergeRecursive($defaults, $loaded);
        } else {
            $config = $defaults;
        }

        $menus = static::loadMenus($projectRoot);

        $env = static::resolveEnv($projectRoot, $envName);

        if (!empty($env)) {
            $config = static::substituteTokens($config, $env);
            $menus  = static::substituteTokens($menus, $env);
        }

        return new self($config, $menus, $env);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function all(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function menus(): array
    {
        return $this->menus;
    }

    /**
     * @return array<string, string>
     */
    public function env(): array
    {
        return $this->env;
    }

    public function path(string $key): string
    {
        $path = $this->get("paths.$key");

        if (!is_string($path)) {
            throw new \InvalidArgumentException("Path configuration for [$key] must be a string.");
        }

        return $path;
    }

    /**
     * @return string[]
     */
    public function assetDirectories(): array
    {
        $assets = $this->get('paths.assets', []);

        if (!is_array($assets)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($path) {
            return is_string($path) ? $path : null;
        }, $assets)));
    }

    public function collectionConfig(string $name): array
    {
        $collections = $this->get('collections', []);

        return $collections[$name] ?? [];
    }

    private static function mergeRecursive(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = static::mergeRecursive($defaults[$key], $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    /**
     * Load env vars from .env.<name> (or plain .env as fallback).
     * Hard-fails if an explicit name is given but the file is missing.
     *
     * @return array<string, string>
     */
    private static function resolveEnv(string $projectRoot, ?string $envName): array
    {
        if ($envName !== null) {
            $path = $projectRoot . DIRECTORY_SEPARATOR . '.env.' . $envName;

            if (!is_file($path)) {
                throw new \RuntimeException(
                    "Environment file [.env.{$envName}] not found in [{$projectRoot}]. " .
                    "Create the file or check the --env value."
                );
            }

            return EnvLoader::load($path);
        }

        // Fallback: plain .env (optional — no error if missing)
        $fallback = $projectRoot . DIRECTORY_SEPARATOR . '.env';

        return is_file($fallback) ? EnvLoader::load($fallback) : [];
    }

    /**
     * Recursively replace ${VAR} tokens in string values with env values.
     * Unresolved tokens are left as-is.
     */
    private static function substituteTokens(array $data, array $env): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = static::substituteTokens($value, $env);
            } elseif (is_string($value)) {
                $data[$key] = (string) preg_replace_callback(
                    '/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/',
                    static fn (array $m) => $env[$m[1]] ?? $m[0],
                    $value
                );
            }
        }

        return $data;
    }

    private static function loadMenus(string $projectRoot): array
    {
        $menuPath = $projectRoot . DIRECTORY_SEPARATOR . '_config' . DIRECTORY_SEPARATOR . 'menu.yaml';

        if (!is_file($menuPath)) {
            return [];
        }

        $menus = Yaml::parseFile($menuPath);

        if (!is_array($menus)) {
            return [];
        }

        // Ensure each menu is an array of items
        return array_map(static function ($items) {
            return is_array($items) ? array_values($items) : [];
        }, $menus);
    }
}
