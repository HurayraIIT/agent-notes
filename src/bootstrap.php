<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// ponytail: ~15-line .env loader, swap for vlucas/phpdotenv if it ever falls short
function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
            $value = $m[2];
        }
        $_ENV[trim($key)] = $value;
    }
}

load_env(__DIR__ . '/../.env');

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function env(string $key, ?string $default = null): ?string
{
    return $_ENV[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', '127.0.0.1'),
            env('DB_PORT', '3306'),
            env('DB_DATABASE', 'agent_notes')
        );
        $pdo = new PDO($dsn, env('DB_USERNAME', 'root'), env('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Store & read all datetimes as UTC, so views can stamp them with Z and localize per viewer.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]);
    }
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = env('APP_URL');
    if (!$base && isset($_SERVER['HTTP_HOST'])) { // derive from the request when APP_URL is unset
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    return rtrim($base ?: 'http://agent-notes.test', '/') . $path;
}

function random_token(int $bytes = 24): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

// Human-readable UTC datetime, e.g. "July 12, 2026 07:50:06 AM UTC" (DB datetimes are UTC — see db()).
// Used where JS can't localize (PDF header). Passes through non-datetime strings unchanged.
function fmt_dt(?string $v): string
{
    $ts = $v ? strtotime($v . ' UTC') : false;
    return $ts ? gmdate('F j, Y h:i:s A', $ts) . ' UTC' : (string) $v;
}

// <time> element carrying the UTC instant; the layout/note.php JS rewrites its text to a relative
// "x ago" string, with the full localized datetime on hover (title). No-JS: shows the full date.
// Falls back to the escaped raw string for non-datetime input.
function dt_tag(?string $v): string
{
    $ts = $v ? strtotime($v . ' UTC') : false;
    if (!$ts) {
        return e((string) $v);
    }
    return '<time datetime="' . e(gmdate('c', $ts)) . '" data-ago title="' . e(fmt_dt($v)) . '">' . e(fmt_dt($v)) . '</time>';
}

// Human-readable byte size, e.g. "512 B", "2 KB", "3 MB".
function fmt_bytes(int $n): string
{
    if ($n < 1024) return $n . ' B';
    if ($n < 1048576) return round($n / 1024, 1) . ' KB';
    return round($n / 1048576, 1) . ' MB';
}

// Brand mark — the gradient "note" icon (same art as the favicon), inline SVG so it stays crisp at any size.
function brand_icon(): string
{
    return '<svg class="inline-block align-[-0.2em] w-[1.35em] h-[1.35em]" viewBox="0 0 32 32" aria-hidden="true">'
        . '<defs><linearGradient id="brandGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4f46e5"/><stop offset="1" stop-color="#7c3aed"/></linearGradient></defs>'
        . '<rect width="32" height="32" rx="7" fill="url(#brandGrad)"/>'
        . '<g stroke="#fff" stroke-width="2.5" stroke-linecap="round"><line x1="9" y1="11" x2="23" y2="11"/><line x1="9" y1="16" x2="23" y2="16"/><line x1="9" y1="21" x2="18" y2="21"/></g>'
        . '</svg>';
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// Keyboard focus ring, shared by every interactive element so the treatment stays identical
// (and legible in both themes). Views are required inside view()/render(), so they can't see
// index.php's $path — hence nav_active() re-parses the request instead.
function focus_ring(): string
{
    return 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 '
        . 'focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900';
}

/** Is the current request on $prefix (exact for '/', prefix-with-boundary otherwise)? */
function nav_active(string $prefix): bool
{
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/') ?: '/';
    if ($prefix === '/') {
        return $path === '/';
    }
    return $path === $prefix || str_starts_with($path, $prefix . '/');
}

/** Header/sidebar link carrying the current-page state. */
function nav_link(string $href, string $label, string $extra = ''): string
{
    $active = nav_active($href);
    $state = $active
        ? 'text-slate-900 dark:text-white font-semibold'
        : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white';
    return '<a href="' . e($href) . '"' . ($active ? ' aria-current="page"' : '')
        . ' class="rounded ' . $state . ' ' . focus_ring() . ' ' . $extra . '">' . e($label) . '</a>';
}

// "Chrome on macOS" from a raw User-Agent.
// ponytail: substring match over the common tokens, ordered so the overlapping ones (Edge/Opera
// carry "Chrome"; iOS carries "Mac OS X"; Android carries "Linux") are tested first. An unrecognised
// UA falls back to showing itself, so this can never invent a wrong answer. Swap for a real UA
// parser only if admins start needing versions or exotic clients. See tests/ua_label.php.
function ua_label(?string $ua): string
{
    $ua = trim((string) $ua);
    if ($ua === '') {
        return '—';
    }
    $browser = '';
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'HeadlessChrome' => 'Headless Chrome',
              'Chrome' => 'Chrome', 'Safari' => 'Safari', 'curl' => 'curl', 'Python' => 'Python'] as $needle => $name) {
        if (stripos($ua, $needle) !== false) {
            $browser = $name;
            break;
        }
    }
    $os = '';
    foreach (['Windows NT' => 'Windows', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'CrOS' => 'ChromeOS',
              'Android' => 'Android', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'] as $needle => $name) {
        if (stripos($ua, $needle) !== false) {
            $os = $name;
            break;
        }
    }
    if ($browser !== '' && $os !== '') {
        return $browser . ' on ' . $os;
    }
    return $browser ?: ($os ?: $ua);
}

function view(string $name, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/../views/' . $name . '.php';
    return ob_get_clean();
}

function render(string $name, array $data = []): never
{
    $data['content'] = view($name, $data);
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/../views/layout.php';
    echo ob_get_clean();
    exit;
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 302);
    exit;
}

/** A command block that fully wraps (no horizontal scrolling) with a working copy button. */
function cmd_block(string $command, string $label = ''): string
{
    $html = '<div data-cmd class="relative rounded-xl bg-slate-900 dark:bg-black/50 border border-slate-700/60 my-3">';
    if ($label !== '') {
        $html .= '<div class="px-4 pt-3 text-xs font-medium uppercase tracking-wider text-slate-400">' . e($label) . '</div>';
    }
    $html .= '<pre class="whitespace-pre-wrap break-all px-4 py-3 pr-20 text-sm font-mono text-emerald-300 leading-relaxed">' . e($command) . '</pre>'
        . '<button onclick="copyCmd(this)" class="absolute top-2.5 right-2.5 rounded-md bg-slate-700/70 hover:bg-slate-600 text-slate-200 text-xs px-2.5 py-1.5 '
        . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">Copy</button>'
        . '</div>';
    return $html;
}

require __DIR__ . '/http.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/markdown.php';
require __DIR__ . '/notes.php';
require __DIR__ . '/ratelimit.php';
require __DIR__ . '/mcp.php';
require __DIR__ . '/pdf.php';
require __DIR__ . '/export.php';
