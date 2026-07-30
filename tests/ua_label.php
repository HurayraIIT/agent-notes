<?php

// Self-check for ua_label(). Run: php tests/ua_label.php
// The overlapping tokens are what make this non-trivial: Edge/Opera UAs also say "Chrome",
// iOS UAs also say "Mac OS X", and Android UAs also say "Linux".

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$cases = [
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36' => 'Chrome on macOS',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15' => 'Safari on macOS',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0' => 'Edge on Windows',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0' => 'Firefox on Windows',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1' => 'Safari on iOS',
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36' => 'Chrome on Android',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/150.0.0.0 Safari/537.36' => 'Headless Chrome on macOS',
    'curl/8.7.1' => 'curl',
    'SomeUnknownAgent/1.0' => 'SomeUnknownAgent/1.0', // never invents an answer
    '' => '—',
];

$failed = 0;
if (ua_label(null) !== '—') {
    $failed++;
    echo "FAIL: null should render as —\n";
}
foreach ($cases as $ua => $expected) {
    $got = ua_label((string) $ua);
    if ($got !== $expected) {
        $failed++;
        echo "FAIL: " . var_export($ua, true) . "\n  expected: $expected\n  got:      $got\n";
    }
}

echo $failed === 0 ? "ua_label: all " . count($cases) . " cases pass\n" : "ua_label: $failed failing\n";
exit($failed === 0 ? 0 : 1);
