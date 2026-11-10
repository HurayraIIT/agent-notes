<?php

declare(strict_types=1);

// Serving a note to a Reader, shared by the full app and the public host.

/**
 * Headers for an HTML note. The page is served top-level on the app's origin, and the CSP sandbox
 * (no allow-same-origin) is what keeps it from acting as the app: the browser gives it a unique opaque
 * origin, so it cannot read cookies or storage, and its requests to the app are cross-origin, carry no
 * SameSite=Lax session cookie and cannot be read back. No allow-forms (no cross-site form posts), no
 * allow-top-navigation, no allow-modals. tests/html_note_headers.php pins the list.
 * @return list<string>
 */
function html_note_headers(): array
{
    return [
        'Content-Type: text/html; charset=utf-8',
        'Content-Security-Policy: sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox allow-downloads',
        'X-Content-Type-Options: nosniff',
        'Referrer-Policy: no-referrer',
        'X-Robots-Tag: ' . ROBOTS_TAG,
        'Cache-Control: private, no-cache',
    ];
}

/** Serves /n/<slug> and its /raw, /download and /pdf forms. $share is the share link that granted access, if any. */
function serve_note(array $note, string $mode, ?array $share = null): never
{
    header('X-Robots-Tag: ' . ROBOTS_TAG);
    if ($mode === '' && $share) {
        share_audit((int) $share['id'], (int) $note['id'], 'viewed');
    }
    if (note_format($note) === NoteFormat::Html) {
        foreach (html_note_headers() as $h) {
            header($h);
        }
        if ($mode === '/raw') {
            text_response($note['content'], 'text/plain; charset=utf-8');
        }
        if ($mode === '/download') {
            header('Content-Disposition: attachment; filename="' . $note['filename'] . '.html"');
            text_response($note['content'], 'text/html; charset=utf-8');
        }
        if ($mode === '/pdf') {
            text_response("PDF export is available for markdown notes only.\n", status: 404);
        }
        count_note_view($note);
        text_response($note['content'], 'text/html; charset=utf-8');
    }

    if ($mode === '/raw' || ($mode === '' && wants_markdown())) {
        text_response($note['content'], 'text/markdown; charset=utf-8');
    }
    if ($mode === '/download') {
        header('Content-Disposition: attachment; filename="' . $note['filename'] . '.md"');
        text_response($note['content'], 'text/markdown; charset=utf-8');
    }
    if ($mode === '/pdf') {
        note_pdf($note);
    }
    $note['views'] += count_note_view($note);
    http_response_code(200);
    echo view('note', ['note' => $note, 'html' => markdown_to_html($note['content'])]);
    exit;
}

/** A view is not an edit (updated_at = updated_at); the Owner viewing their own note doesn't count. */
function count_note_view(array $note): int
{
    $viewer = current_user();
    if ($viewer && (int) $viewer['id'] === (int) $note['user_id']) {
        return 0;
    }
    db()->prepare('UPDATE notes SET views = views + 1, updated_at = updated_at WHERE id = ?')->execute([$note['id']]);
    return 1;
}

/** The public host's only "no": unknown, tailnet-only, expired and revoked all get these same bytes. */
function public_not_found(): never
{
    header('Cache-Control: no-store');
    text_response("Not found.\n", 'text/plain; charset=utf-8', 404);
}

/**
 * The whole public host (FEATURE_VISIBILITY, request_scope() === Scope::Public). An allowlist: public
 * notes and their assets, share links and the notes they open, robots.txt. No sessions, no forms, no
 * MCP, no dashboard; every other path is public_not_found().
 */
function public_scope_handle(string $path, string $method): never
{
    header('Referrer-Policy: no-referrer');
    if ($method !== 'GET' && $method !== 'HEAD') {
        public_not_found();
    }
    if ($path === '/robots.txt') {
        text_response("User-agent: *\nDisallow: /\n");
    }
    if (feature(Feature::ShareLinks) && preg_match('#^/s/([^/]+)$#', $path, $m)) {
        share_open($m[1]);
    }
    if (preg_match('#^/n/([A-Za-z0-9-]+)(?:(/raw|/download|/pdf)|/assets/([^/]+))?$#', $path, $m)) {
        $note = get_note($m[1]);
        $share = null;
        if ($note && note_visibility($note) !== Visibility::Public) {
            $share = feature(Feature::ShareLinks) ? share_cookie_grants($note) : null;
            $note = $share ? $note : null;
        }
        if ($note && ($m[3] ?? '') !== '') {
            serve_asset($note, $m[3], public: $share === null);
            public_not_found();
        }
        if ($note) {
            serve_note($note, $m[2] ?? '', $share);
        }
    }
    public_not_found();
}
