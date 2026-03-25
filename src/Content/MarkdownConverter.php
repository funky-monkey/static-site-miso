<?php

declare(strict_types=1);

namespace Miso\Content;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class MarkdownConverter
{
    private CommonMarkConverter|GithubFlavoredMarkdownConverter $converter;

    public function __construct(CommonMarkConverter|GithubFlavoredMarkdownConverter|null $converter = null)
    {
        $this->converter = $converter ?? new GithubFlavoredMarkdownConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
    }

    public function convert(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
