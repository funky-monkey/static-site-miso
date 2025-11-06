<?php

declare(strict_types=1);

namespace Miso\Content;

use Symfony\Component\Yaml\Yaml;

class FrontMatterParser
{
    public function parse(string $contents): array
    {
        if (preg_match('/^---\\s*\\n(.+?)\\n---\\s*\\n(.*)$/s', $contents, $matches)) {
            $data = Yaml::parse($matches[1]) ?? [];
            $body = $matches[2];

            return [
                'data' => is_array($data) ? $data : [],
                'body' => $body,
            ];
        }

        return [
            'data' => [],
            'body' => $contents,
        ];
    }
}
