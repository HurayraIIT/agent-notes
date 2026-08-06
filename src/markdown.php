<?php

declare(strict_types=1);

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

function markdown_to_html(string $markdown): string
{
    static $converter = null;
    if ($converter === null) {
        // Same config and extensions GithubFlavoredMarkdownConverter would have built — spelled
        // out because we need the Environment to hang custom renderers off.
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            'external_link' => [
                'internal_hosts' => parse_url(app_url(), PHP_URL_HOST) ?: '',
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        // Notes are public user content: outbound links get rel="nofollow noopener noreferrer".
        $environment->addExtension(new ExternalLinkExtension());
        // Priority 100 beats DisallowedRawHtmlExtension (50) and the core renderers (0). Each of
        // these returns null when it doesn't apply, and HtmlRenderer::renderNode() then falls
        // through to the next renderer — so anything we don't claim keeps its stock behaviour.
        $renderer = new AllowlistedRawHtmlRenderer();
        $environment->addRenderer(HtmlBlock::class, $renderer, 100);
        $environment->addRenderer(HtmlInline::class, $renderer, 100);
        $environment->addRenderer(BlockQuote::class, new GithubAlertRenderer(), 10);
        $environment->addRenderer(FencedCode::class, new MermaidRenderer(), 10);

        $converter = new MarkdownConverter($environment);
    }

    return balance_details((string) $converter->convert($markdown));
}

/**
 * Appends any missing </details> so the fragment is well-formed for the two parsers that are not
 * a browser: dompdf's, and the innerHTML assignment in the editor. It does NOT rescue an agent's
 * unclosed <details> — the trailing content still ends up inside the disclosure, exactly as it
 * would on GitHub. Escaped openers read as &lt;details, so counting the raw form only ever sees
 * tags the allowlist let through. A stray </details> needs no handling — parsers drop unmatched
 * end tags. ponytail: no stack scan to escape the guilty opener; matching GitHub is the brief.
 */
function balance_details(string $html): string
{
    $open = preg_match_all('#<details(?:\s++open)?+\s*+/?+>#i', $html);
    $close = preg_match_all('#</details\s*+>#i', $html);

    return $open > $close ? $html . str_repeat("\n</details>", $open - $close) : $html;
}

/**
 * Lets a tiny, attribute-free subset of raw HTML through so notes can use <details>/<summary>
 * the way a GitHub gist does — including the inline formatting people put inside <summary>,
 * where markdown doesn't apply. Everything else stays escaped by html_input=escape.
 *
 * The invariant: after stripping every allowlisted tag, a surviving '<' rejects the whole
 * literal. So every '<' that reaches the browser opens one of these tags, and no attacker-chosen
 * element name or attribute can ever get through.
 */
final class AllowlistedRawHtmlRenderer implements NodeRendererInterface
{
    // `open` is permitted on <details> only. No /u modifier: invalid UTF-8 would make
    // preg_replace() return null. Possessive quantifiers keep the match provably linear.
    private const ALLOWED_TAGS = '#<(?:/?+(?:summary|strong|small|em|code|kbd|mark|sub|sup|del|ins|br|b|i|s|u)|details(?:\s++open)?+|/details)\s*+/?+>#i';

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        // HtmlBlock types 1-5 are <script>/<pre>/<style>/<textarea> containers, comments,
        // processing instructions, declarations and CDATA. Never raw, whatever they contain.
        if ($node instanceof HtmlBlock && $node->getType() < HtmlBlock::TYPE_6_BLOCK_ELEMENT) {
            return null;
        }

        $literal = $node instanceof HtmlBlock || $node instanceof HtmlInline ? $node->getLiteral() : null;
        if ($literal === null) {
            return null;
        }

        $residue = preg_replace(self::ALLOWED_TAGS, '', $literal);
        // preg_replace() returns null on PCRE error — fail closed, never raw.
        if ($residue === null || str_contains($residue, '<')) {
            return null; // → DisallowedRawHtml (50) → core (0) → htmlspecialchars
        }

        return $literal;
    }
}

/**
 * GitHub alerts: `> [!NOTE]` and friends become a callout instead of a plain blockquote.
 * Matched against the rendered paragraph rather than the inline AST — we generated that HTML a
 * line earlier, so the shape is exact, and it's a fraction of the code AST surgery would take.
 */
final class GithubAlertRenderer implements NodeRendererInterface
{
    private const KINDS = 'NOTE|TIP|IMPORTANT|WARNING|CAUTION';

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (!$node instanceof BlockQuote) {
            return null;
        }

        $inner = $childRenderer->renderNodes($node->children());
        // Group 2 tells the two shapes apart: "\n" means the body shares the marker's paragraph,
        // "</p>" means the marker had a paragraph to itself and that paragraph now goes away.
        if (!preg_match('#^<p>\[!(' . self::KINDS . ')\](\n|</p>\n?)#i', $inner, $m)) {
            return null; // ordinary blockquote — core renderer handles it
        }

        $kind = strtolower($m[1]);
        $body = substr($inner, strlen($m[0]));
        $inner = $m[2] === "\n" ? '<p>' . $body : $body;

        return (string) new HtmlElement('div', ['class' => 'md-alert md-alert-' . $kind], "\n"
            . '<p class="md-alert-title">' . ucfirst($kind) . "</p>\n" . $inner . "\n");
    }
}

/** ```mermaid fences render as diagrams client-side. Content stays escaped; mermaid reads textContent. */
final class MermaidRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (!$node instanceof FencedCode || ($node->getInfoWords()[0] ?? '') !== 'mermaid') {
            return null;
        }

        return (string) new HtmlElement('pre', ['class' => 'mermaid'], Xml::escape($node->getLiteral()));
    }
}
