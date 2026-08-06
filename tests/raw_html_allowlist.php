<?php

// Guard for the raw-HTML allowlist in src/markdown.php. Notes render <details>/<summary> the way
// a GitHub gist does, which means AllowlistedRawHtmlRenderer hands a slice of user HTML straight
// to the browser — and that HTML is echoed unescaped into views/note.php and assigned via
// innerHTML in the editor. One careless addition to ALLOWED_TAGS is stored XSS for every reader
// of every note, so the invariant gets a check rather than a comment.
// Run: php tests/raw_html_allowlist.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$failed = 0;
$fail = function (string $why) use (&$failed): void {
    $failed++;
    echo "FAIL: $why\n";
};

// Every tag markdown_to_html() may legitimately emit: markdown-produced plus the raw allowlist.
// Anything outside this list reaching the browser means the allowlist leaked.
$SAFE_TAGS = [
    'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'code', 'blockquote', 'div', 'hr', 'br',
    'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    'a', 'img', 'em', 'strong', 'del', 'input',
    // the allowlist itself
    'details', 'summary', 'b', 'i', 's', 'u', 'sub', 'sup', 'kbd', 'mark', 'ins', 'small',
];
// Attributes our own renderers emit. A raw allowlisted tag carries none at all, except <details open>.
$SAFE_ATTRS = ['class', 'href', 'src', 'alt', 'title', 'rel', 'target', 'type', 'checked', 'disabled', 'align', 'open'];

// --- 1. The real-world shape: raw disclosure, markdown still parsed inside it ---------------
$gist = "<details>\n<summary><b>B1</b> \xe2\x80\xa2 <code>secret</code> fields</summary>\n\n"
    . "**bold** and `<input type=\"password\">`\n\n</details>\n";
$out = markdown_to_html($gist);
foreach (['<details>', '<summary>', '<b>B1</b>', '<code>secret</code>', '</details>'] as $want) {
    str_contains($out, $want) || $fail("<details> support broke — expected $want in output");
}
str_contains($out, '<strong>bold</strong>')
    || $fail('markdown inside <details> is no longer parsed');
str_contains($out, '&lt;input type=&quot;password&quot;&gt;')
    || $fail('a code span containing HTML is no longer escaped');

// --- 2. Nothing outside the allowlist may render as markup ----------------------------------
$corpus = [
    // the positive case again, so the invariant sweep covers a passing input too
    $gist,
    '<script>alert(1)</script>',
    '<img src=x onerror=alert(1)>',
    '<iframe src="https://evil.test"></iframe>',
    '<svg onload=alert(1)></svg>',
    '<a href="javascript:alert(1)">x</a>',
    '[x](javascript:alert(1))',
    '<details onclick=alert(1)>' . "\n<summary>y</summary>\n",
    '<details open="x">' . "\n",
    '<summary onmouseover=alert(1)>y</summary>',
    'inline <b onclick=alert(1)>z</b>',
    "<!-- comment -->\n",
    "<?php echo 1; ?>\n",
    "<![CDATA[x]]>\n",
    "<!DOCTYPE html>\n",
    "<style>body{}</style>\n",
    "<textarea></textarea>\n",
    "<pre>\n<script>alert(1)</script>\n</pre>\n",
    'malformed <b<script> and < b> and <b/>',
    // Bare, attribute-free forms — mid-paragraph so they parse as inline HTML rather than being
    // swallowed into one whole-line HtmlBlock. These are what a widened ALLOWED_TAGS would leak,
    // so they are the corpus entries that actually exercise the invariant sweep below.
    'x <script> y </script> z',
    'x <iframe> y </iframe> z',
    'x <style> y </style> z',
    'x <textarea> y </textarea> z',
    'x <object> <embed> <form> <link> <meta> <base> <title> z',
    "> [!WARNING]\n> Careful.\n",
    "```mermaid\ngraph TD; A--><script>x</script>;\n```\n",
];

foreach ($corpus as $md) {
    $out = markdown_to_html($md);
    $label = substr(str_replace("\n", '\n', $md), 0, 48);

    // Real tags only — escaped payloads read as &lt;… and are invisible to this.
    preg_match_all('#<\s*/?\s*([a-zA-Z0-9-]+)([^>]*)>#', $out, $tags, PREG_SET_ORDER);
    foreach ($tags as [$whole, $name, $attrs]) {
        if (!in_array(strtolower($name), $SAFE_TAGS, true)) {
            $fail("tag <$name> escaped the allowlist via: $label");
        }
        preg_match_all('#([a-zA-Z-]+)\s*=#', $attrs, $found);
        foreach ($found[1] as $attr) {
            if (!in_array(strtolower($attr), $SAFE_ATTRS, true)) {
                $fail("attribute $attr survived in $whole via: $label");
            }
        }
        if (preg_match('#\bon[a-z]+\s*=#i', $attrs)) {
            $fail("event handler survived in $whole via: $label");
        }
        if (preg_match('#javascript\s*:#i', $attrs)) {
            $fail("javascript: URL survived in $whole via: $label");
        }
    }

    // 4. An unclosed <details> must never swallow the rest of the note.
    if (substr_count(strtolower($out), '<details') !== substr_count(strtolower($out), '</details>')) {
        $fail("unbalanced <details> in output of: $label");
    }
}

// --- 3. GitHub alerts and mermaid, without disturbing plain blockquotes/fences ---------------
str_contains(markdown_to_html("> [!WARNING]\n> Careful.\n"), 'md-alert md-alert-warning')
    || $fail('> [!WARNING] no longer renders an alert');
str_contains(markdown_to_html("> [!NOTE]\n> Heads up.\n"), '<p>Heads up.</p>')
    || $fail('alert body is missing');
$plain = markdown_to_html("> just a quote\n");
(str_contains($plain, '<blockquote>') && !str_contains($plain, 'md-alert'))
    || $fail('a plain blockquote is being treated as an alert');
$mermaid = markdown_to_html("```mermaid\ngraph TD; A-->B;\n```\n");
(str_contains($mermaid, '<pre class="mermaid">') && str_contains($mermaid, 'A--&gt;B'))
    || $fail('mermaid fences no longer render as a mermaid block with escaped content');
str_contains(markdown_to_html("```php\n\$x = 1;\n```\n"), '<code class="language-php">')
    || $fail('ordinary fenced code stopped being highlighted');

// --- 5. Outbound links are hardened, internal ones left alone -------------------------------
str_contains(markdown_to_html('[x](https://example.test/)'), 'rel="nofollow noopener noreferrer"')
    || $fail('outbound links lost their rel hardening');

echo $failed === 0
    ? "raw_html_allowlist: <details> renders, everything else stays escaped\n"
    : "raw_html_allowlist: $failed failing\n";
exit($failed === 0 ? 0 : 1);
