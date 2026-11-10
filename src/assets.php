<?php

declare(strict_types=1);

// Note assets (FEATURE_ASSETS): images an agent uploads for one note, stored on disk outside public/
// at ASSET_STORAGE_PATH/<note id>/<name>. Names are made here, never by the client: the first 20 hex
// of the bytes' sha256 plus the extension the bytes prove. So a re-upload is idempotent, and no client
// string ever reaches a filesystem path.

const ASSET_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];

function asset_max_bytes(): int
{
    return env_int('ASSET_MAX_BYTES', 5242880);
}

function asset_root(): string
{
    $path = (string) env('ASSET_STORAGE_PATH', '');
    return rtrim($path !== '' ? $path : __DIR__ . '/../storage/assets', '/');
}

function asset_dir(int $noteId): string
{
    return asset_root() . '/' . $noteId;
}

function valid_asset_name(string $name): bool
{
    return (bool) preg_match('/^[a-f0-9]{20}\.(png|jpg|gif|webp)$/D', $name);
}

/** The image type the bytes themselves prove, or null. Structure checks, then PHP's own parser must agree. */
function sniff_image(string $bytes): ?string
{
    $mime = match (true) {
        str_starts_with($bytes, "\x89PNG\r\n\x1a\n") && substr($bytes, 12, 4) === 'IHDR' => 'image/png',
        str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
        (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) => 'image/gif',
        str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP'
            && in_array(substr($bytes, 12, 4), ['VP8 ', 'VP8L', 'VP8X'], true) => 'image/webp',
        default => null,
    };
    if ($mime === null) {
        return null;
    }
    $info = @getimagesizefromstring($bytes);
    return ($info && ($info['mime'] ?? '') === $mime && $info[0] > 0 && $info[1] > 0) ? $mime : null;
}

/**
 * Decodes and checks an upload. $data is base64, or a data: URI. $declared is the agent's claimed type,
 * if any; it must match what the bytes prove.
 * @return array{0: ?array{bytes: string, mime: string, name: string, size: int}, 1: ?string} [asset, error]
 */
function parse_asset_upload(string $data, ?string $declared): array
{
    $data = trim($data);
    if (preg_match('#^data:([^;,]*);base64,#i', $data, $m)) {
        $uriType = strtolower($m[1]);
        if ($declared !== null && strtolower($declared) !== $uriType) {
            return [null, "mime_type '{$declared}' disagrees with the data: URI type '{$uriType}'"];
        }
        $declared = $uriType;
        $data = substr($data, strlen($m[0]));
    }
    $declared = $declared === null ? null : strtolower(trim($declared));
    if ($declared === 'image/jpg') {
        $declared = 'image/jpeg';
    }
    if ($declared === 'image/svg+xml') {
        return [null, 'SVG is not accepted (it can carry scripts). Inline the <svg> in an HTML note, or upload a PNG.'];
    }
    $max = asset_max_bytes();
    $data = preg_replace('/\s+/', '', $data);
    if (strlen($data) > intdiv($max + 2, 3) * 4) { // refuse before decoding: base64 is 4 chars per 3 bytes
        return [null, "image is over the {$max}-byte limit"];
    }
    $bytes = base64_decode($data, true);
    if ($bytes === false || $bytes === '') {
        return [null, 'data must be non-empty base64 (or a data: URI)'];
    }
    $size = strlen($bytes);
    if ($size > $max) {
        return [null, "image is {$size} bytes; the maximum is {$max} bytes"];
    }
    $mime = sniff_image($bytes);
    if ($mime === null) {
        return [null, 'data is not a PNG, JPEG, GIF or WebP image'];
    }
    if ($declared !== null && $declared !== $mime) {
        return [null, "mime_type says {$declared} but the bytes are {$mime}"];
    }
    $name = substr(hash('sha256', $bytes), 0, 20) . '.' . ASSET_TYPES[$mime];
    return [['bytes' => $bytes, 'mime' => $mime, 'name' => $name, 'size' => $size], null];
}

/** @return array{0: ?array, 1: ?string} [asset, error] */
function store_asset(array $note, string $data, ?string $declared): array
{
    [$asset, $error] = parse_asset_upload($data, $declared);
    if ($error) {
        return [null, $error];
    }
    // Hold the note's row lock while writing, the lock delete_notes() takes: an upload either finishes
    // before the note's files are purged or finds the note gone. It also serialises the per-note cap.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM notes WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $note['id']]);
        [$asset, $error] = $stmt->fetch() ? write_asset($note, $asset) : [null, 'note not found'];
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return [$asset, $error];
}

/** @return array{0: ?array, 1: ?string} [asset, error]; the caller holds the note's row lock */
function write_asset(array $note, array $asset): array
{
    $dir = asset_dir((int) $note['id']);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        error_log('store_asset: cannot create ' . $dir);
        return [null, 'asset storage is unavailable'];
    }
    $path = $dir . '/' . $asset['name'];
    if (!is_file($path)) {
        $limit = env_int('ASSET_MAX_PER_NOTE', 50);
        if (count(list_assets($note)) >= $limit) {
            return [null, "this note already has {$limit} assets; delete one with delete_asset first"];
        }
        // Same-directory temp file + rename: a crash never leaves a half-written asset under its real name.
        $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $asset['bytes']) !== $asset['size'] || !rename($tmp, $path)) {
            @unlink($tmp);
            return [null, 'could not write the asset'];
        }
    }
    unset($asset['bytes']);
    return [$asset, null];
}

/** @return list<array{name: string, size: int}> */
function list_assets(array $note): array
{
    $out = [];
    foreach (@scandir(asset_dir((int) $note['id'])) ?: [] as $name) {
        if (valid_asset_name($name)) {
            $out[] = ['name' => $name, 'size' => (int) filesize(asset_dir((int) $note['id']) . '/' . $name)];
        }
    }
    return $out;
}

function delete_asset(array $note, string $name): bool
{
    return valid_asset_name($name) && @unlink(asset_dir((int) $note['id']) . '/' . $name);
}

/** Removes a note's asset directory. Safe to rerun; a missing directory is already done. */
function purge_note_assets(int $noteId): void
{
    $dir = asset_dir($noteId);
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $f) {
        if ($f !== '.' && $f !== '..') {
            @unlink($dir . '/' . $f);
        }
    }
    @rmdir($dir);
}

/** Root-relative path: works on whichever host serves the note (app, public host, share link). */
function asset_path(array $note, string $name): string
{
    return '/n/' . $note['slug'] . '/assets/' . $name;
}

function asset_summary(array $note, array $asset): array
{
    $path = asset_path($note, $asset['name']);
    return [
        'name' => $asset['name'],
        'path' => $path,
        'url' => preg_replace('#/n/[^/]+$#', '', note_url($note['slug'], $note['visibility'] ?? null)) . $path,
        'size_bytes' => $asset['size'],
    ];
}

/** @return list<string> */
function asset_headers(string $mime, bool $public): array
{
    return [
        'Content-Type: ' . $mime,
        'X-Content-Type-Options: nosniff',
        // Opened top-level, the image still gets an opaque origin and no script.
        "Content-Security-Policy: default-src 'none'; sandbox",
        'Content-Disposition: inline',
        'Referrer-Policy: no-referrer',
        'X-Robots-Tag: ' . robots_tag(),
        // Names are content hashes, so a cached copy is never stale. Non-public notes stay out of shared caches.
        'Cache-Control: ' . ($public ? 'public, max-age=86400' : 'private, max-age=3600'),
    ];
}

/** Streams one asset of $note, or returns false when there is no such asset. */
function serve_asset(array $note, string $name, bool $public): bool
{
    if (!feature(Feature::Assets) || !valid_asset_name($name)) {
        return false;
    }
    $path = asset_dir((int) $note['id']) . '/' . $name;
    if (!is_file($path)) {
        return false;
    }
    $mime = array_search(pathinfo($name, PATHINFO_EXTENSION), ASSET_TYPES, true);
    foreach (asset_headers($mime, $public) as $h) {
        header($h);
    }
    header('Content-Length: ' . filesize($path));
    header('ETag: "' . substr($name, 0, 20) . '"');
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === '"' . substr($name, 0, 20) . '"') {
        http_response_code(304);
        exit;
    }
    readfile($path);
    exit;
}
