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
    return rtrim(env('APP_URL', 'http://agent-notes.test'), '/') . $path;
}

function random_token(int $bytes = 24): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
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

require __DIR__ . '/http.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/markdown.php';
require __DIR__ . '/notes.php';
require __DIR__ . '/ratelimit.php';
require __DIR__ . '/mcp.php';
