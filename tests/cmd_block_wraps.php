<?php

// Guard for the product requirement in CLAUDE.md: "Commands shown in the UI must fully wrap
// — no horizontal scrolling." This regressed once already (cmd_block drifted to
// overflow-x-auto/whitespace-pre, clipping the `claude mcp add` command mid-flag on the
// homepage), so it gets a check rather than just a comment. Run: php tests/cmd_block_wraps.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$html = cmd_block('claude mcp add --transport http --scope user agent-notes https://example.test/mcp --header "Authorization: Bearer some-very-long-token-value"', 'Label');

// The <pre> is what holds the command; only its classes decide whether text wraps.
preg_match('/<pre class="([^"]*)"/', $html, $m) || exit("FAIL: cmd_block emitted no <pre>\n");
$classes = explode(' ', $m[1]);

$failed = 0;
foreach (['whitespace-pre-wrap', 'break-all'] as $required) {
    if (!in_array($required, $classes, true)) {
        $failed++;
        echo "FAIL: cmd_block <pre> is missing $required — long commands will not wrap\n";
    }
}
foreach (['overflow-x-auto', 'whitespace-pre', 'whitespace-nowrap', 'overflow-x-scroll'] as $banned) {
    if (in_array($banned, $classes, true)) {
        $failed++;
        echo "FAIL: cmd_block <pre> has $banned — that reintroduces horizontal scrolling\n";
    }
}

echo $failed === 0 ? "cmd_block: commands wrap, no horizontal scroll\n" : "cmd_block: $failed failing\n";
exit($failed === 0 ? 0 : 1);
