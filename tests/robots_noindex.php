<?php

// With ROBOTS_NOINDEX=true, every PHP response carries exactly one `X-Robots-Tag: noindex, nofollow,
// noarchive`, and every page from layout.php a robots meta tag. It runs the app under `php -S` on
// both scopes and requests every route literal in public/index.php, plus samples of the pattern
// routes, with GET and POST. The database is pointed at a closed port on purpose: a request that
// reaches the database fails with a 500, which must still carry the header, since it is set before
// routing. With the flag off, the home page stays indexable. Run: php tests/robots_noindex.php

declare(strict_types=1);

const WANT = 'noindex, nofollow, noarchive';
$root = dirname(__DIR__);
$failed = 0;
$fail = function (string $why) use (&$failed): void {
    $failed++;
    echo "FAIL: $why\n";
};

$index = file_get_contents("$root/public/index.php");
preg_match_all("/\\\$path === '(\\/[^']*)'/", $index, $m1);
preg_match_all("/str_starts_with\\(\\\$path, '(\\/[^']*)'\\)/", $index, $m2);
$slug = 'AbCdEfGh';
$paths = array_unique(array_merge($m1[1], array_map(fn($p) => $p . 'x', $m2[1]), [
    "/n/$slug", "/n/$slug/raw", "/n/$slug/download", "/n/$slug/pdf", "/n/$slug/edit",
    "/n/$slug/assets/0123456789abcdef0123.png", '/s/' . str_repeat('A', 43), '/s/short', '/no-such-route',
]));
count($m1[1]) >= 20 || $fail('found only ' . count($m1[1]) . " route literals in public/index.php; did the routing change shape?");

$router = tempnam(sys_get_temp_dir(), 'an-robots-') . '.php';
file_put_contents($router, '<?php if (getenv("AGENT_NOTES_SCOPE")) { $_SERVER["AGENT_NOTES_SCOPE"] = getenv("AGENT_NOTES_SCOPE"); }'
    . ' chdir(' . var_export("$root/public", true) . '); require ' . var_export("$root/public/index.php", true) . ';');

/** Starts the app; returns [process, port]. */
function serve(string $router, array $env): array
{
    for ($try = 0; $try < 20; $try++) {
        $port = random_int(20000, 29999);
        $env += ['PATH' => getenv('PATH'), 'APP_URL' => "http://127.0.0.1:$port", 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'DB_DATABASE' => 'none'];
        $cmd = [PHP_BINARY, '-d', 'variables_order=EGPCS', '-d', 'display_errors=0', '-d', 'log_errors=0', '-S', "127.0.0.1:$port", $router];
        $proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);
        for ($i = 0; $i < 50; $i++) {
            usleep(100000);
            if ($s = @fsockopen('127.0.0.1', $port)) {
                fclose($s);
                return [$proc, $port];
            }
        }
        proc_terminate($proc);
    }
    throw new RuntimeException('php -S never came up');
}

/** @return array{0: int, 1: list<string>, 2: string} [status, header lines, body] */
function fetch(int $port, string $method, string $path): array
{
    $ctx = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $method === 'POST' ? 'x=1' : '']]);
    $body = (string) @file_get_contents("http://127.0.0.1:$port$path", false, $ctx);
    $headers = $http_response_header ?? [];
    preg_match('/^HTTP\/\S+ (\d+)/', $headers[0] ?? '', $s);
    return [(int) ($s[1] ?? 0), $headers, $body];
}

$robots = fn(array $h) => array_values(array_map(fn($l) => trim(substr($l, strlen('X-Robots-Tag:'))),
    array_filter($h, fn($l) => stripos($l, 'X-Robots-Tag:') === 0)));
$flags = ['FEATURE_HTML_NOTES' => 'true', 'FEATURE_ASSETS' => 'true', 'FEATURE_VISIBILITY' => 'true', 'FEATURE_SHARE_LINKS' => 'true', 'PUBLIC_URL' => 'http://127.0.0.1:1'];

foreach (['tailnet', 'public'] as $scope) {
    [$proc, $port] = serve($router, ['ROBOTS_NOINDEX' => 'true', 'AGENT_NOTES_SCOPE' => $scope] + $flags);
    $checked = 0;
    foreach ($paths as $path) {
        foreach (['GET', 'POST'] as $method) {
            [$status, $headers, $body] = fetch($port, $method, $path);
            if ($status === 0) {
                $fail("$scope $method $path: no response");
                continue;
            }
            $checked++;
            $tags = $robots($headers);
            $tags === [WANT] || $fail("$scope $method $path ($status): X-Robots-Tag is " . json_encode($tags));
            $html = (bool) preg_grep('/^Content-Type:\s*text\/html/i', $headers);
            if ($status === 200 && $html && str_contains($body, '<html') && !str_contains($body, '<meta name="robots" content="noindex, nofollow">')) {
                $fail("$scope $method $path: HTML page without the robots meta tag");
            }
        }
    }
    [, , $txt] = fetch($port, 'GET', '/robots.txt');
    $txt === "User-agent: *\nDisallow: /\n" || $fail("$scope robots.txt is not Disallow: / (" . json_encode(substr($txt, 0, 60)) . ')');
    $checked >= 40 || $fail("$scope: only $checked responses checked");
    proc_terminate($proc);
}

// Off by default: the home page carries no X-Robots-Tag and robots.txt keeps its Sitemap line.
[$proc, $port] = serve($router, []);
[$status, $headers] = fetch($port, 'GET', '/');
$robots($headers) === [] || $fail("flag off: GET / ($status) carries X-Robots-Tag " . json_encode($robots($headers)));
str_contains(fetch($port, 'GET', '/robots.txt')[2], 'Sitemap:') || $fail('flag off: robots.txt lost its Sitemap line');
proc_terminate($proc);
@unlink($router);

if ($failed) {
    exit(1);
}
echo "ok: robots_noindex\n";
