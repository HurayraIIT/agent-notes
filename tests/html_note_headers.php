<?php

// HTML notes are full pages served top-level on the app's own origin, so their response headers are the
// only thing between a note's scripts and a Reader's session. The CSP sandbox without allow-same-origin
// gives the page an opaque origin: no cookies, no same-origin fetches, no storage. Run: php tests/html_note_headers.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$failed = 0;
$fail = function (string $why) use (&$failed): void {
    $failed++;
    echo "FAIL: $why\n";
};

$headers = [];
foreach (html_note_headers() as $line) {
    [$name, $value] = array_map('trim', explode(':', $line, 2));
    $headers[strtolower($name)] = $value;
}

$csp = $headers['content-security-policy'] ?? '';
preg_match('/(?:^|;)\s*sandbox\b([^;]*)/', $csp, $m) || $fail('no sandbox directive in CSP: ' . $csp);
$tokens = preg_split('/\s+/', trim($m[1] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
sort($tokens);
$tokens === ['allow-downloads', 'allow-popups', 'allow-popups-to-escape-sandbox', 'allow-scripts']
    || $fail('sandbox tokens changed: ' . implode(' ', $tokens));
foreach (['allow-same-origin', 'allow-forms', 'allow-top-navigation', 'allow-modals', 'allow-storage-access-by-user-activation'] as $banned) {
    str_contains($csp, $banned) && $fail("CSP grants $banned");
}
($headers['x-content-type-options'] ?? '') === 'nosniff' || $fail('missing X-Content-Type-Options: nosniff');
($headers['referrer-policy'] ?? '') === 'no-referrer' || $fail('Referrer-Policy is not no-referrer');
str_starts_with($headers['content-type'] ?? '', 'text/html') || $fail('Content-Type is not text/html');

// Assets carry a sandbox too, in case one is opened top-level.
$asset = implode("\n", asset_headers('image/png', true));
str_contains($asset, 'X-Content-Type-Options: nosniff') || $fail('asset without nosniff');
str_contains($asset, 'Content-Type: image/png') || $fail('asset content type not fixed');
preg_match('/Content-Security-Policy:.*sandbox/', $asset) || $fail('asset without CSP sandbox');
str_contains($asset, 'Cache-Control:') || $fail('asset without Cache-Control');
str_contains(implode("\n", asset_headers('image/png', false)), 'private') || $fail('non-public asset is cacheable by shared caches');

echo $failed === 0 ? "html_note_headers: sandboxed, opaque origin, nosniff, no-referrer\n" : "html_note_headers: $failed failing\n";
exit($failed === 0 ? 0 : 1);
