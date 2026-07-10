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

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

/** Creates a 7-day session for a VERIFIED user and sets the cookie. */
function create_session(array $user): void
{
    $token = random_token();
    db()->prepare('INSERT INTO sessions (token_hash, user_id, ip, user_agent, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL ? DAY)')
        ->execute([hash('sha256', $token), $user['id'], client_ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), SESSION_DAYS]);
    db()->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ?, last_login_user_agent = ? WHERE id = ?')
        ->execute([client_ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), $user['id']]);
    setcookie(SESSION_COOKIE, $token, [
        'expires' => time() + SESSION_DAYS * 86400,
        'path' => '/',
        'secure' => str_starts_with(env('APP_URL', ''), 'https://'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[SESSION_COOKIE] = $token; // so csrf_token()/current_user() work in this request
}

/** Sends a 6-digit OTP for 'login' or 'verify'. Returns an error string, or null on success. */
function send_otp(string $email, string $purpose = 'login'): ?string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    if (rate_limited('code:' . $email, 3, 600) || rate_limited('code-ip:' . client_ip(), 10, 600)) {
        return 'Too many code requests. Please wait a few minutes.';
    }

    $code = (string) random_int(100000, 999999);
    db()->prepare('INSERT INTO login_codes (email, code_hash, purpose, expires_at, ip) VALUES (?, ?, ?, NOW() + INTERVAL 10 MINUTE, ?)')
        ->execute([$email, hash('sha256', $code), $purpose, client_ip()]);

    $intro = $purpose === 'verify' ? 'Your Agent Notes verification code is:' : 'Your Agent Notes sign-in code is:';
    try {
        send_mail(
            $email,
            "{$code} is your Agent Notes code",
            "<p>{$intro}</p><p style=\"font-size:28px;font-weight:bold;letter-spacing:4px\">{$code}</p><p>It expires in 10 minutes. If you didn't request this, you can ignore this email.</p>"
        );
    } catch (Throwable $e) {
        error_log('send_otp: ' . $e->getMessage());
        return 'Could not send the email. Please try again later.';
    }
    return null;
}

/**
 * Consumes an OTP, marks the email verified (inbox ownership proven), and signs the user in.
 * Returns the user row on success, or an error string.
 */
function otp_verify(string $email, string $code): array|string
{
    $email = strtolower(trim($email));
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
    if (!hash_equals($row['code_hash'], hash('sha256', trim($code)))) {
        return 'Incorrect code. Please try again.';
    }
    db()->prepare('UPDATE login_codes SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);

    $user = find_user_by_email($email);
    if (!$user) {
        return 'No account found for this email. Please register first.';
    }
    if (!$user['email_verified_at']) {
        db()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$user['id']]);
        $user['email_verified_at'] = date('Y-m-d H:i:s');
    }
    create_session($user);
    return $user;
}

/** Registers an unverified account and sends a verification OTP. Returns error string or null. */
function register_user(string $username, string $email, string $password): ?string
{
    $username = strtolower(trim($username));
    $email = strtolower(trim($email));
    if (!preg_match('/^[a-z0-9_-]{3,50}$/', $username)) {
        return 'Username must be 3–50 characters: lowercase letters, numbers, hyphens, underscores.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (rate_limited('register-ip:' . client_ip(), 5, 600)) {
        return 'Too many registrations from your network. Please wait a few minutes.';
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        return 'That email or username is already taken. Try signing in instead.';
    }

    db()->prepare('INSERT INTO users (email, username, password_hash) VALUES (?, ?, ?)')
        ->execute([$email, $username, password_hash($password, PASSWORD_DEFAULT)]);

    // account exists now even if mail delivery fails — caller sends user to the verify page either way
    return send_otp($email, 'verify') === null ? null : 'MAIL_FAILED';
}

/**
 * Password login with username OR email. Returns the user row on success,
 * 'unverified' if the account still needs email verification (OTP re-sent),
 * or a generic error string.
 */
function password_login(string $identifier, string $password): array|string
{
    $identifier = strtolower(trim($identifier));
    if (rate_limited('pwlogin:' . $identifier, 10, 600) || rate_limited('pwlogin-ip:' . client_ip(), 20, 600)) {
        return 'Too many attempts. Please wait a few minutes.';
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();
    if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
        return 'Invalid credentials.'; // generic: no account enumeration
    }
    if (!$user['email_verified_at']) {
        send_otp($user['email'], 'verify');
        return 'unverified';
    }
    create_session($user);
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

/** Updates username and/or password from Account settings. Returns error string or null. */
function update_account(array $user, string $username, string $newPassword, string $currentPassword): ?string
{
    $username = strtolower(trim($username));
    if ($username !== ($user['username'] ?? '')) {
        if (!preg_match('/^[a-z0-9_-]{3,50}$/', $username)) {
            return 'Username must be 3–50 characters: lowercase letters, numbers, hyphens, underscores.';
        }
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $user['id']]);
        if ($stmt->fetch()) {
            return 'That username is already taken.';
        }
        db()->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$username, $user['id']]);
    }
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            return 'New password must be at least 8 characters.';
        }
        if ($user['password_hash'] && !password_verify($currentPassword, $user['password_hash'])) {
            return 'Current password is incorrect.';
        }
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
    }
    return null;
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
