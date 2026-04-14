<?php

declare(strict_types=1);

namespace Miso\Site;

class EnvLoader
{
    /**
     * Parse a .env file and return key/value pairs.
     *
     * Supports:
     *   KEY=value
     *   KEY="quoted value"
     *   KEY='single quoted'
     *   # full-line comments
     *   inline # comments on unquoted values
     *
     * @return array<string, string>
     */
    public static function load(string $filePath): array
    {
        $lines = @file($filePath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        $vars = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            // Strip surrounding quotes (matched pairs only)
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[-1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                } else {
                    // No quotes — strip inline comment
                    $value = trim((string) preg_replace('/#.*$/', '', $value));
                }
            }

            $vars[$key] = $value;
        }

        return $vars;
    }
}
