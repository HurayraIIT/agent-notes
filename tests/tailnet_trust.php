<?php

// FEATURE_TAILNET_TRUST signs tailnet visitors in as one configured user, with no code and no mail.
// That is only safe while the decision rests on the server-set AGENT_NOTES_SCOPE alone. This pins the
// truth table, and that mail-less email codes fail closed before touching anything.
// Run: php tests/tailnet_trust.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php'; // also proves the app boots with no MAIL_* configured

$failed = 0;
$fail = function (string $why) use (&$failed): void {
    $failed++;
    echo "FAIL: $why\n";
};
foreach (['MAIL_PRIMARY_HOST', 'MAIL_FAILOVER_HOST'] as $k) {
    unset($_ENV[$k]);
}
$case = function (array $env, array $server, ?string $want, string $label) use ($fail): void {
    foreach (['FEATURE_TAILNET_TRUST', 'FEATURE_VISIBILITY', 'TAILNET_TRUST_USER_EMAIL'] as $k) {
        unset($_ENV[$k]);
    }
    foreach (['AGENT_NOTES_SCOPE', 'HTTP_AGENT_NOTES_SCOPE', 'HTTP_X_AGENT_NOTES_SCOPE', 'HTTP_HOST', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST'] as $k) {
        unset($_SERVER[$k]);
    }
    $_ENV = $env + $_ENV;
    $_SERVER = $server + $_SERVER;
    $got = tailnet_trust_email();
    $got === $want || $fail("$label: expected " . var_export($want, true) . ', got ' . var_export($got, true));
};
$on = ['FEATURE_TAILNET_TRUST' => 'true', 'TAILNET_TRUST_USER_EMAIL' => 'Notes-Admin@Example.test'];

$case([], ['AGENT_NOTES_SCOPE' => 'tailnet'], null, 'flag off, tailnet listener');
$case(['TAILNET_TRUST_USER_EMAIL' => 'a@b.test'], ['AGENT_NOTES_SCOPE' => 'tailnet'], null, 'flag unset but email set');
$case($on, ['AGENT_NOTES_SCOPE' => 'tailnet'], 'notes-admin@example.test', 'flag on, tailnet listener');
$case($on + ['FEATURE_VISIBILITY' => 'true'], ['AGENT_NOTES_SCOPE' => 'tailnet'], 'notes-admin@example.test', 'flag on with visibility, tailnet listener');
$case($on, ['AGENT_NOTES_SCOPE' => 'public'], null, 'flag on, public listener');
$case($on, [], null, 'flag on, no scope parameter (e.g. Herd, php -S)');
$case($on, ['AGENT_NOTES_SCOPE' => 'Tailnet'], null, 'flag on, wrong case');
$case($on, ['HTTP_AGENT_NOTES_SCOPE' => 'tailnet'], null, 'flag on, client header AGENT_NOTES_SCOPE');
$case($on, ['HTTP_X_AGENT_NOTES_SCOPE' => 'tailnet'], null, 'flag on, client header X-Agent-Notes-Scope');
$case($on, ['AGENT_NOTES_SCOPE' => 'public', 'HTTP_HOST' => 'notes.example.internal', 'HTTP_X_FORWARDED_HOST' => 'notes.example.internal', 'HTTP_X_FORWARDED_FOR' => '100.64.0.1'],
    null, 'flag on, public listener with tailnet-looking Host/X-Forwarded-*');
$case(['FEATURE_TAILNET_TRUST' => 'true'], ['AGENT_NOTES_SCOPE' => 'tailnet'], null, 'flag on, no TAILNET_TRUST_USER_EMAIL');
$case(['FEATURE_TAILNET_TRUST' => 'true', 'TAILNET_TRUST_USER_EMAIL' => 'not-an-email'], ['AGENT_NOTES_SCOPE' => 'tailnet'], null, 'flag on, invalid email');

// The public scope has no sessions, so even a trusted-looking request there gets none.
$_ENV = $on + ['FEATURE_VISIBILITY' => 'true'] + $_ENV;
$_SERVER['AGENT_NOTES_SCOPE'] = 'public';
current_user() === null || $fail('public scope resolved a user with trust on');
tailnet_trust_sign_in() === null || $fail('public scope signed someone in');

// With trust on and no mail, an email code fails closed with a clear message, before any database write.
mail_configured() && $fail('mail_configured() is true with no MAIL_*_HOST');
$msg = send_otp('someone@example.test', 'login');
(is_string($msg) && str_contains(strtolower($msg), 'not configured')) || $fail('send_otp without mail did not fail closed: ' . var_export($msg, true));

echo $failed === 0 ? "tailnet_trust: only the tailnet listener with the flag on signs in; no mail needed\n" : "tailnet_trust: $failed failing\n";
exit($failed === 0 ? 0 : 1);
