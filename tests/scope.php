<?php

// The public host must never be able to claim the full app. Scope comes from a FastCGI parameter
// the web server sets per listener; this checks a client header can't stand in for it and that an
// unset or odd value fails closed. Run: php tests/scope.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$failed = 0;
$fail = function (string $why) use (&$failed): void {
    $failed++;
    echo "FAIL: $why\n";
};
$_ENV['FEATURE_VISIBILITY'] = 'true';
$case = function (array $server, Scope $want, string $label) use ($fail): void {
    unset($_SERVER['AGENT_NOTES_SCOPE'], $_SERVER['HTTP_AGENT_NOTES_SCOPE'], $_SERVER['HTTP_X_AGENT_NOTES_SCOPE']);
    foreach ($server as $k => $v) {
        $_SERVER[$k] = $v;
    }
    request_scope() === $want || $fail("$label: expected " . $want->name);
};

$case([], Scope::Public, 'no parameter (misconfigured server)');
$case(['AGENT_NOTES_SCOPE' => 'tailnet'], Scope::Full, 'tailnet listener');
$case(['AGENT_NOTES_SCOPE' => 'public'], Scope::Public, 'public listener');
$case(['AGENT_NOTES_SCOPE' => 'TAILNET'], Scope::Public, 'wrong case');
$case(['AGENT_NOTES_SCOPE' => 'tailnet '], Scope::Public, 'trailing space');
$case(['HTTP_AGENT_NOTES_SCOPE' => 'tailnet'], Scope::Public, 'client header AGENT_NOTES_SCOPE');
$case(['HTTP_X_AGENT_NOTES_SCOPE' => 'tailnet'], Scope::Public, 'client header X-Agent-Notes-Scope');

// The public scope has no sessions at all, whatever cookie arrives.
$_SERVER['AGENT_NOTES_SCOPE'] = 'public';
$_COOKIE[SESSION_COOKIE] = 'anything';
current_user() === null || $fail('public scope resolved a session');

echo $failed === 0 ? "scope: only the server-set parameter selects the full app\n" : "scope: $failed failing\n";
exit($failed === 0 ? 0 : 1);
