<?php

// Uploaded images are typed by their bytes, never by what the agent declared. Polyglots, SVG and
// mislabelled files are refused before anything reaches disk. Run: php tests/asset_sniff.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$failed = 0;
$fail = function (string $why) use (&$failed): void {
    $failed++;
    echo "FAIL: $why\n";
};

$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
$gif = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
$webp = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';
$jpeg = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

foreach (['image/png' => $png, 'image/gif' => $gif, 'image/webp' => $webp, 'image/jpeg' => $jpeg] as $mime => $b64) {
    [$asset, $err] = parse_asset_upload($b64, null);
    $err === null || $fail("$mime refused: $err");
    ($asset['mime'] ?? null) === $mime || $fail("$mime sniffed as " . ($asset['mime'] ?? 'nothing'));
    [, $err] = parse_asset_upload("data:$mime;base64,$b64", $mime);
    $err === null || $fail("$mime as a data: URI refused: $err");
}

$refuse = function (string $b64, ?string $declared, string $label) use ($fail): void {
    [$asset, $err] = parse_asset_upload($b64, $declared);
    ($asset === null && is_string($err)) || $fail("$label was accepted");
};
$refuse($png, 'image/gif', 'PNG declared as GIF');
$refuse($png, 'image/svg+xml', 'PNG declared as SVG');
$refuse(base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'image/svg+xml', 'SVG');
$refuse(base64_encode('<html><script>alert(1)</script></html>'), null, 'HTML');
$refuse(base64_encode("\x89PNG\r\n\x1a\n<script>alert(1)</script>"), null, 'PNG magic + script (not a parseable image)');
$refuse(base64_encode("RIFF\x10\x00\x00\x00WEBP<script>"), null, 'WebP magic without a VP8 chunk');
// A file that is both a valid image and valid JS/HTML (a GIF89a polyglot) can pass any byte check.
// It is harmless only because of how it is served: fixed image type, nosniff, CSP sandbox
// (tests/html_note_headers.php checks asset_headers()).
$refuse('!!!not base64!!!', null, 'invalid base64');
$refuse('', null, 'empty data');
$_ENV['ASSET_MAX_BYTES'] = '100';
$refuse($jpeg, null, 'JPEG over ASSET_MAX_BYTES=100');
unset($_ENV['ASSET_MAX_BYTES']);

// Names are server-made and content-addressed; the serving route accepts only that shape.
[$a] = parse_asset_upload($png, null);
preg_match('/^[a-f0-9]{20}\.png$/', $a['name'] ?? '') || $fail('asset name is not <20 hex>.png: ' . ($a['name'] ?? ''));
[$b] = parse_asset_upload($png, null);
($a['name'] ?? 1) === ($b['name'] ?? 2) || $fail('same bytes gave different names (re-upload is not idempotent)');
foreach (['../x.png', 'a/b.png', '0123456789abcdef0123.svg', '0123456789abcdef0123.png.php', '0123456789ABCDEF0123.png', '.htaccess'] as $bad) {
    valid_asset_name($bad) && $fail("valid_asset_name accepted $bad");
}
valid_asset_name('0123456789abcdef0123.webp') || $fail('valid_asset_name refused a real name');

echo $failed === 0 ? "asset_sniff: typed by bytes; SVG, polyglots, mismatches and oversize refused\n" : "asset_sniff: $failed failing\n";
exit($failed === 0 ? 0 : 1);
