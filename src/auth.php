<?php

declare(strict_types=1);

const SESSION_COOKIE = 'an_session';
const SESSION_DAYS = 7;

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $token = $_COOKIE[SESSION_COOKIE] ?? '';
    if ($token !== '') {
        $stmt = db()->prepare(
            'SELECT u.* FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = ? AND s.expires_at > NOW()'
        );
        $stmt->execute([hash('sha256', $token)]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login(): array
{
    return current_user() ?? redirect('/login');
}

function require_admin(): array
{
    $user = require_login();
    if (!$user['is_admin']) {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function csrf_token(): string
{
    return hash_hmac('sha256', 'csrf', $_COOKIE[SESSION_COOKIE] ?? '');
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), $_POST['_csrf'] ?? '')) {
        http_response_code(419);
        exit('CSRF token mismatch');
    }
}

/** Returns an error string, or null on success. */
function login_send_code(string $email): ?string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    if (rate_limited('code:' . $email, 3, 600) || rate_limited('code-ip:' . client_ip(), 10, 600)) {
        return 'Too many code requests. Please wait a few minutes.';
    }

    $code = (string) random_int(100000, 999999);
    $stmt = db()->prepare(
        'INSERT INTO login_codes (email, code_hash, expires_at, ip) VALUES (?, ?, NOW() + INTERVAL 10 MINUTE, ?)'
    );
    $stmt->execute([$email, hash('sha256', $code), client_ip()]);

    try {
        send_mail(
            $email,
            "{$code} is your Agent Notes sign-in code",
            "<p>Your Agent Notes sign-in code is:</p><p style=\"font-size:28px;font-weight:bold;letter-spacing:4px\">{$code}</p><p>It expires in 10 minutes. If you didn't request this, you can ignore this email.</p>"
        );
    } catch (Throwable $e) {
        error_log('login_send_code: ' . $e->getMessage());
        return 'Could not send the email. Please try again later.';
    }
    return null;
}

/** Returns the user row on success, or an error string. */
function login_verify(string $email, string $code): array|string
{
    $email = strtolower(trim($email));
    $code = trim($code);
    $stmt = db()->prepare(
        'SELECT * FROM login_codes WHERE email = ? AND consumed_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) {
        return 'Code expired or not found. Request a new one.';
    }
    if ($row['attempts'] >= 5) {
        return 'Too many attempts. Request a new code.';
    }
    db()->prepare('UPDATE login_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
    if (!hash_equals($row['code_hash'], hash('sha256', $code))) {
        return 'Incorrect code. Please try again.';
    }
    db()->prepare('UPDATE login_codes SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        db()->prepare('INSERT INTO users (email) VALUES (?)')->execute([$email]);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    db()->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ?, last_login_user_agent = ? WHERE id = ?')
        ->execute([client_ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), $user['id']]);

    $token = random_token();
    db()->prepare('INSERT INTO sessions (token_hash, user_id, ip, user_agent, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL ? DAY)')
        ->execute([hash('sha256', $token), $user['id'], client_ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), SESSION_DAYS]);
    setcookie(SESSION_COOKIE, $token, [
        'expires' => time() + SESSION_DAYS * 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[SESSION_COOKIE] = $token; // so csrf_token()/current_user() work in this request

    return $user;
}

function logout(): void
{
    $token = $_COOKIE[SESSION_COOKIE] ?? '';
    if ($token !== '') {
        db()->prepare('DELETE FROM sessions WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    }
    setcookie(SESSION_COOKIE, '', ['expires' => 1, 'path' => '/']);
}

/** Mints a new API token and returns its plaintext (only time it exists). */
function create_api_token(int $userId, string $name = 'default'): string
{
    $plaintext = 'an_' . random_token(24);
    db()->prepare('INSERT INTO api_tokens (user_id, name, token_hash, prefix) VALUES (?, ?, ?, ?)')
        ->execute([$userId, substr($name, 0, 100), hash('sha256', $plaintext), substr($plaintext, 0, 12)]);
    return $plaintext;
}

/** Resolves a bearer token to its user, updating last_used_at. */
function user_from_bearer_token(?string $token): ?array
{
    if (!$token) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT u.*, t.id AS token_id FROM api_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.revoked_at IS NULL'
    );
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();
    if ($user) {
        db()->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?')->execute([$user['token_id']]);
    }
    return $user ?: null;
}
