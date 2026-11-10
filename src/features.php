<?php

declare(strict_types=1);

// Optional features. Each one is off unless its FEATURE_* key is "true" in .env, and with every
// flag off the app behaves exactly as it did before they existed: same MCP tools, same SQL columns,
// same pages. tests/features_off.php holds that line.

enum Feature: string
{
    case HtmlNotes = 'FEATURE_HTML_NOTES';   // format "html": a full page served top-level under a CSP sandbox
    case Assets = 'FEATURE_ASSETS';          // upload_asset: images stored per note, outside public/
    case Visibility = 'FEATURE_VISIBILITY';  // tailnet/public split across two listeners
    case ShareLinks = 'FEATURE_SHARE_LINKS'; // expiring read links to one tailnet note, on the public host
}

function feature(Feature $f): bool
{
    // Share links are served on the public host, so they need the visibility split to exist.
    if ($f === Feature::ShareLinks && !feature(Feature::Visibility)) {
        return false;
    }
    return strtolower((string) env($f->value, 'false')) === 'true';
}

enum NoteFormat: string
{
    case Markdown = 'markdown';
    case Html = 'html';

    public function extension(): string
    {
        return $this === self::Html ? 'html' : 'md';
    }
}

enum Visibility: string
{
    case Tailnet = 'tailnet'; // the default: readable only through the full (tailnet) app
    case Public = 'public';   // also readable on the public host
}

/** Which app a request reached. Full is the whole app; Public serves public notes, assets and share links only. */
enum Scope
{
    case Full;
    case Public;
}

/**
 * The scope of this request, from a FastCGI parameter the web server sets per listener
 * (docker/nginx.conf: one listen port per scope). HTTP request headers reach PHP only as HTTP_*
 * keys, so no client can set AGENT_NOTES_SCOPE. Anything but exactly "tailnet" is the public
 * scope: a misconfigured server fails closed.
 */
function request_scope(): Scope
{
    if (!feature(Feature::Visibility)) {
        return Scope::Full;
    }
    return ($_SERVER['AGENT_NOTES_SCOPE'] ?? null) === 'tailnet' ? Scope::Full : Scope::Public;
}

/** Base URL of the public host (no trailing slash), or null when it is not configured. */
function public_url(): ?string
{
    $url = rtrim((string) env('PUBLIC_URL', ''), '/');
    return $url !== '' ? $url : null;
}

function env_int(string $key, int $default): int
{
    $v = env($key);
    return ($v !== null && ctype_digit($v)) ? (int) $v : $default;
}
