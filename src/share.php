<?php

declare(strict_types=1);

// Share links (FEATURE_SHARE_LINKS, needs FEATURE_VISIBILITY): an expiring, revocable read link to one
// tailnet note, opened on the public host by someone with no tailnet and no account.
//
// Flow: GET /s/<token> checks the link, sets a cookie holding the token (HttpOnly, host-only, Path=/n/<slug>)
// and redirects to the clean /n/<slug>. After that the cookie is checked again on every request, so
// revoking the link or deleting the note ends access at once. Only sha256(token) is stored.

const SHARE_COOKIE = 'an_share';

/** @return array{0: string, 1: string} [token, sha256 hash] — 256 random bits */
function new_share_token(): array
{
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    return [$token, hash('sha256', $token)];
}

/** @return array{0: ?int, 1: ?string} [hours, error] */
function share_ttl_hours(?int $requested): array
{
    $max = env_int('SHARE_LINK_TTL_MAX_HOURS', 720);
    $hours = $requested ?? min(env_int('SHARE_LINK_TTL_DEFAULT_HOURS', 168), $max);
    if ($hours < 1 || $hours > $max) {
        return [null, "ttl_hours must be between 1 and {$max}"];
    }
    return [$hours, null];
}

function share_cookie_options(string $slug, int $linkExpiresAt): array
{
    $secure = str_starts_with((string) public_url(), 'https://');
    return [
        'expires' => min($linkExpiresAt, time() + env_int('SHARE_COOKIE_TTL_HOURS', 12) * 3600),
        'path' => '/n/' . $slug,
        'secure' => $secure,
        'httponly' => true,
        // None, not Lax: an HTML note runs with an opaque origin (CSP sandbox), so its own <img> requests
        // count as cross-site and a Lax cookie would not reach its assets. Exposure stays small: the
        // cookie is HttpOnly, only sent under this one note's path, and every response under that path
        // is read-only and unreadable cross-origin (no CORS headers anywhere in the app).
        'samesite' => $secure ? 'None' : 'Lax',
    ];
}

function share_url(string $token): string
{
    return (public_url() ?? app_url()) . '/s/' . $token;
}

function share_audit(?int $linkId, int $noteId, string $event, ?int $actorId = null): void
{
    db()->prepare('INSERT INTO share_link_events (share_link_id, note_id, event, actor_user_id, client) VALUES (?, ?, ?, ?, ?)')
        ->execute([$linkId, $noteId, $event, $actorId, substr(ua_label($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 60)]);
}

/** @return array{0: ?array, 1: ?string} [link with its one-time 'url', error] */
function create_share_link(int $userId, string $slug, ?int $ttlHours, string $via): array
{
    if (public_url() === null) {
        return [null, 'share links need PUBLIC_URL (the public host) to be configured'];
    }
    $note = get_note($slug, $userId);
    if (!$note) {
        return [null, "note '{$slug}' not found"];
    }
    if (note_visibility($note) === Visibility::Public) {
        return [null, 'this note is already public; share its url instead'];
    }
    [$hours, $error] = share_ttl_hours($ttlHours);
    if ($error) {
        return [null, $error];
    }
    [$token, $hash] = new_share_token();
    db()->prepare('INSERT INTO share_links (note_id, token_hash, created_by, created_via, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL ? HOUR)')
        ->execute([$note['id'], $hash, $userId, $via, $hours]);
    $id = (int) db()->lastInsertId();
    share_audit($id, (int) $note['id'], 'created', $userId);
    $link = share_links_for($userId, null, $id)[0];
    $link['url'] = share_url($token); // the only time the token leaves the server
    return [$link, null];
}

/** The Owner's links, newest first, optionally for one note or one link id. Never includes tokens. */
function share_links_for(int $userId, ?string $slug = null, ?int $id = null): array
{
    $sql = 'SELECT l.id, n.slug, n.title, l.created_via, l.created_at, l.expires_at, l.revoked_at, l.last_used_at, l.use_count,
                   CASE WHEN l.revoked_at IS NOT NULL THEN \'revoked\' WHEN l.expires_at <= NOW() THEN \'expired\' ELSE \'active\' END AS status
            FROM share_links l JOIN notes n ON n.id = l.note_id WHERE n.user_id = ?';
    $params = [$userId];
    if ($slug !== null) {
        $sql .= ' AND n.slug = ?';
        $params[] = $slug;
    }
    if ($id !== null) {
        $sql .= ' AND l.id = ?';
        $params[] = $id;
    }
    $stmt = db()->prepare($sql . ' ORDER BY l.id DESC LIMIT 200');
    $stmt->execute($params);
    return array_map(fn($r) => ['id' => (int) $r['id'], 'use_count' => (int) $r['use_count']] + $r, $stmt->fetchAll());
}

function revoke_share_link(int $userId, int $id): bool
{
    $stmt = db()->prepare('UPDATE share_links l JOIN notes n ON n.id = l.note_id SET l.revoked_at = NOW()
                           WHERE l.id = ? AND n.user_id = ? AND l.revoked_at IS NULL');
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    $note = db()->prepare('SELECT note_id FROM share_links WHERE id = ?');
    $note->execute([$id]);
    share_audit($id, (int) $note->fetchColumn(), 'revoked', $userId);
    return true;
}

/** The live (unrevoked, unexpired) link a token belongs to, with its note's slug; null otherwise. */
function share_link_by_token(string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) {
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT l.id, l.note_id, l.token_hash, UNIX_TIMESTAMP(l.expires_at) AS expires_ts, n.slug
                           FROM share_links l JOIN notes n ON n.id = l.note_id
                           WHERE l.token_hash = ? AND l.revoked_at IS NULL AND l.expires_at > NOW()');
    $stmt->execute([$hash]);
    $link = $stmt->fetch();
    return ($link && hash_equals($link['token_hash'], $hash)) ? $link : null;
}

/**
 * Token guesses share one per-IP budget per minute: every /s/ request, and every share cookie that
 * fails to check out. A valid cookie costs nothing, so a note with many images never trips it.
 */
function share_guess(bool $count): bool
{
    $key = 'share-ip:' . client_ip();
    $limit = env_int('SHARE_LINK_RATE_PER_MIN', 30);
    if ($count) {
        return rate_limited($key, $limit);
    }
    $stmt = db()->prepare('SELECT count FROM rate_limits WHERE bucket_key = ? AND window_start = ?');
    $stmt->execute([$key, date('Y-m-d H:i:s', intdiv(time(), 60) * 60)]);
    return (int) $stmt->fetchColumn() > $limit;
}

/** GET /s/<token> on the public host: swap the token for a cookie, then go to the clean note URL. */
function share_open(string $token): never
{
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store');
    if (share_guess(count: true)) {
        header('Retry-After: 60');
        text_response('Too many requests. Try again in a minute.', status: 429);
    }
    $link = share_link_by_token($token);
    if (!$link) {
        public_not_found();
    }
    db()->prepare('UPDATE share_links SET last_used_at = NOW(), use_count = use_count + 1 WHERE id = ?')->execute([$link['id']]);
    share_audit((int) $link['id'], (int) $link['note_id'], 'opened');
    setcookie(SHARE_COOKIE, $token, share_cookie_options($link['slug'], (int) $link['expires_ts']));
    header('Location: /n/' . $link['slug'], true, 303);
    exit;
}

/** Does this request carry a live share cookie for exactly $note? Checked on every request. */
function share_cookie_grants(array $note): ?array
{
    $token = $_COOKIE[SHARE_COOKIE] ?? '';
    if (!is_string($token) || $token === '' || share_guess(count: false)) {
        return null;
    }
    $link = share_link_by_token($token);
    if ($link && (int) $link['note_id'] === (int) $note['id']) {
        return $link;
    }
    share_guess(count: true);
    return null;
}
