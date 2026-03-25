<?php

declare(strict_types=1);

namespace Miso\Schema;

use Symfony\Component\Yaml\Yaml;

class SchemaLoader
{
    /**
     * Scans the _config directory for *-schema.yml files and returns their parsed contents.
     *
     * @return list<array{vars: array<string, mixed>, schema: array<string, mixed>, collections?: list<string>}>
     */
    public function load(string $configDir): array
    {
        $schemas = [];

        $files = glob($configDir . DIRECTORY_SEPARATOR . '*-schema.yml') ?: [];
        sort($files);

        foreach ($files as $file) {
            try {
                $data = Yaml::parseFile($file);
            } catch (\Throwable) {
                continue;
            }

            if (!is_array($data) || !isset($data['schema']) || !is_array($data['schema'])) {
                continue;
            }

            $schemas[] = [
                'vars'        => is_array($data['vars'] ?? null) ? $data['vars'] : [],
                'schema'      => $data['schema'],
                'collections' => is_array($data['collections'] ?? null) ? $data['collections'] : null,
            ];
        }

        return $schemas;
    }
}
