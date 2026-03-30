<?php

declare(strict_types=1);

namespace Miso\Content;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdown\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter as LeagueMarkdownConverter;

class MarkdownConverter
{
    private CommonMarkConverter|GithubFlavoredMarkdownConverter|LeagueMarkdownConverter $converter;

    public function __construct(CommonMarkConverter|GithubFlavoredMarkdownConverter|LeagueMarkdownConverter|null $converter = null)
    {
        if ($converter !== null) {
            $this->converter = $converter;
            return;
        }

        // GFM without DisallowedRawHtmlExtension so that <textarea> and similar
        // tags are passed through when html_input is set to 'allow'.
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());

        $this->converter = new LeagueMarkdownConverter($environment);
    }

    public function convert(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
