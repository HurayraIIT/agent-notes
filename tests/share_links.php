<?php

// Share-link rules that don't need a database: token strength, what gets stored, TTL bounds, and the
// cookie that replaces the token after the first visit. Run: php tests/share_links.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$failed = 0;
$fail = function (string $why) use (&$failed): void {
    $failed++;
    echo "FAIL: $why\n";
};

[$token, $hash] = new_share_token();
strlen(base64_decode(strtr($token, '-_', '+/'), true) ?: '') >= 16 || $fail('token carries under 128 bits');
preg_match('/^[A-Za-z0-9_-]+$/', $token) || $fail('token is not URL-safe');
$hash === hash('sha256', $token) || $fail('stored hash is not sha256(token)');
str_contains($hash, $token) && $fail('hash contains the token');
[$token2] = new_share_token();
$token !== $token2 || $fail('two tokens are equal');

// TTL: default 7 days, max 30, both configurable; out-of-range is an error, not a silent clamp.
share_ttl_hours(null) === [168, null] || $fail('default TTL is not 168 hours');
share_ttl_hours(720) === [720, null] || $fail('30 days refused');
share_ttl_hours(721)[1] !== null || $fail('over 30 days accepted');
share_ttl_hours(0)[1] !== null || $fail('zero TTL accepted');
$_ENV['SHARE_LINK_TTL_DEFAULT_HOURS'] = '24';
$_ENV['SHARE_LINK_TTL_MAX_HOURS'] = '48';
share_ttl_hours(null) === [24, null] || $fail('SHARE_LINK_TTL_DEFAULT_HOURS ignored');
share_ttl_hours(49)[1] !== null || $fail('SHARE_LINK_TTL_MAX_HOURS ignored');
unset($_ENV['SHARE_LINK_TTL_DEFAULT_HOURS'], $_ENV['SHARE_LINK_TTL_MAX_HOURS']);

// The cookie: one note's path, unreadable to scripts, short-lived, never longer than the link.
$_ENV['PUBLIC_URL'] = 'https://share.example.test';
$now = time();
$o = share_cookie_options('AbCd1234', $now + 7 * 86400);
$o['path'] === '/n/AbCd1234' || $fail('cookie path is not the note path');
$o['httponly'] === true || $fail('cookie readable by scripts');
$o['secure'] === true || $fail('cookie not Secure on an https public host');
isset($o['domain']) && $fail('cookie has a Domain attribute (must be host-only)');
$o['expires'] <= $now + 12 * 3600 + 5 || $fail('cookie outlives SHARE_COOKIE_TTL_HOURS (12h default)');
share_cookie_options('AbCd1234', $now + 600)['expires'] <= $now + 600 || $fail('cookie outlives its link');

echo $failed === 0 ? "share_links: 256-bit tokens, hash-only storage, bounded TTL, path-scoped cookie\n" : "share_links: $failed failing\n";
exit($failed === 0 ? 0 : 1);
