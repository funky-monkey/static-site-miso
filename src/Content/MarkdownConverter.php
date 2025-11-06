<?php

declare(strict_types=1);

namespace Miso\Content;

use League\CommonMark\CommonMarkConverter;

class MarkdownConverter
{
    private CommonMarkConverter $converter;

    public function __construct(?CommonMarkConverter $converter = null)
    {
        $this->converter = $converter ?? new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
    }

    public function convert(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
