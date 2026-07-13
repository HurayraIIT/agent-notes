<?php

declare(strict_types=1);

use League\CommonMark\GithubFlavoredMarkdownConverter;

function markdown_to_html(string $markdown): string
{
    static $converter = null;
    if ($converter === null) {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            // ponytail: render soft line breaks as <br>, matching GitHub's own GFM
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
    }
    return (string) $converter->convert($markdown);
}
