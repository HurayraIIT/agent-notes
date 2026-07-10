<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// ---- MCP (core product) ----
if ($path === '/mcp') {
    mcp_handle();
}

// ---- Notes ----
if (preg_match('#^/n/([A-Za-z0-9-]+)(/raw|/download|/pdf)?$#', $path, $m)) {
    $note = get_note($m[1]);
    if (!$note) {
        http_response_code(404);
        render('error', ['title' => 'Note not found', 'message' => 'This note does not exist or was deleted.']);
    }
    header('X-Robots-Tag: noindex, nofollow');
    $mode = $m[2] ?? '';
    if ($mode === '/raw' || ($mode === '' && wants_markdown())) {
        text_response($note['content'], 'text/markdown; charset=utf-8');
    }
    if ($mode === '/download') {
        header('Content-Disposition: attachment; filename="' . $note['slug'] . '.md"');
        text_response($note['content'], 'text/markdown; charset=utf-8');
    }
    if ($mode === '/pdf') {
        note_pdf($note);
    }
    http_response_code(200);
    echo view('note', ['note' => $note, 'html' => markdown_to_html($note['content'])]);
    exit;
}

// ---- Agent discovery ----
if ($path === '/.well-known/mcp/server-card.json') {
    json_response([
        'serverInfo' => [
            'name' => 'agent-notes',
            'version' => '1.0.0',
        ],
        'title' => env('APP_NAME', 'Agent Notes'),
        'description' => 'Note-publishing service for AI agents. Publish markdown over MCP, get back a clean unlisted URL that renders for humans.',
        'url' => app_url('/mcp'),
        'transport' => ['type' => 'streamable-http', 'endpoint' => app_url('/mcp')],
        'protocolVersion' => MCP_PROTOCOL_VERSION,
        'authentication' => [
            'type' => 'bearer',
            'description' => 'API token in the Authorization: Bearer header. Register at ' . app_url('/register') . ' to get one.',
        ],
        'capabilities' => ['tools' => array_map(fn($t) => ['name' => $t['name'], 'description' => $t['description']], mcp_tools())],
        'documentation' => app_url('/docs'),
    ]);
}

if ($path === '/.well-known/api-catalog') {
    json_response(contentType: 'application/linkset+json', data: [
        'linkset' => [[
            'anchor' => app_url('/'),
            'service-desc' => [['href' => app_url('/.well-known/mcp/server-card.json'), 'type' => 'application/json', 'title' => 'MCP Server Card']],
            'service-doc' => [['href' => app_url('/docs'), 'type' => 'text/html', 'title' => 'Agent Setup Docs']],
            'service-meta' => [
                ['href' => app_url('/llms.txt'), 'type' => 'text/plain', 'title' => 'LLM usage guide'],
                ['href' => app_url('/auth.md'), 'type' => 'text/markdown', 'title' => 'Agent authentication guide'],
                ['href' => app_url('/.well-known/oauth-protected-resource'), 'type' => 'application/json', 'title' => 'OAuth Protected Resource Metadata (RFC 9728)'],
                ['href' => app_url('/.well-known/agent-skills/index.json'), 'type' => 'application/json', 'title' => 'Agent Skills discovery index'],
                ['href' => app_url('/sitemap.xml'), 'type' => 'application/xml', 'title' => 'Sitemap'],
            ],
            'item' => [['href' => app_url('/mcp'), 'title' => 'MCP endpoint (Streamable HTTP, JSON-RPC 2.0)']],
        ]],
    ]);
}

if ($path === '/.well-known/openid-configuration') {
    json_response([
        'issuer' => app_url(),
        'authorization_endpoint' => app_url('/login'),
        'token_endpoint' => app_url('/tokens/create'),
        'registration_endpoint' => app_url('/register'),
        'jwks_uri' => app_url('/.well-known/jwks.json'),
        'response_types_supported' => ['token'],
        'grant_types_supported' => ['implicit'],
        'subject_types_supported' => ['public'],
        'id_token_signing_alg_values_supported' => ['none'],
        'token_endpoint_auth_methods_supported' => ['client_secret_post'],
        'scopes_supported' => ['openid', 'notes:read', 'notes:write'],
        'service_documentation' => app_url('/docs'),
        'x_bearer_token_info' => [
            'description' => 'This service uses manually-provisioned Bearer API tokens. Register at ' . app_url('/register') . ', then create tokens on your dashboard.',
            'token_prefix' => 'an_',
            'header' => 'Authorization: Bearer <token>',
        ],
    ]);
}

if ($path === '/.well-known/oauth-authorization-server') {
    json_response([
        'issuer' => app_url(),
        'authorization_endpoint' => app_url('/login'),
        'token_endpoint' => app_url('/tokens/create'),
        'registration_endpoint' => app_url('/register'),
        'jwks_uri' => app_url('/.well-known/jwks.json'),
        'response_types_supported' => ['token'],
        'grant_types_supported' => ['implicit'],
        'token_endpoint_auth_methods_supported' => ['client_secret_post'],
        'scopes_supported' => ['notes:read', 'notes:write'],
        'service_documentation' => app_url('/docs'),
        'agent_auth' => [ // auth.md (workos.com/auth-md) agent registration block
            'skill' => app_url('/auth.md'),
            'register_uri' => app_url('/register'),
            'instructions_uri' => app_url('/auth.md'),
            'methods_supported' => ['manual_provisioning'], // human registers, mints bearer token, hands it to the agent
            'identity_types_supported' => ['email'],
            'credential_types_supported' => ['bearer_token'],
            'revocation_uri' => app_url('/dashboard'),
        ],
    ]);
}

if ($path === '/.well-known/jwks.json') {
    json_response(['keys' => []]);
}

if ($path === '/.well-known/agent-skills' || $path === '/.well-known/agent-skills/index.json') {
    // digest is computed over the exact bytes the SKILL.md route serves (post-{{URL}} substitution)
    $skillMd = strtr(file_get_contents(__DIR__ . '/../skills/publish-notes/SKILL.md'), ['{{URL}}' => app_url()]);
    json_response([
        '$schema' => 'https://schemas.agentskills.io/discovery/0.2.0/schema.json',
        'skills' => [[
            'name' => 'publish-notes',
            'type' => 'skill-md',
            'description' => 'Publish markdown notes to shareable URLs via the Agent Notes MCP server.',
            'url' => app_url('/.well-known/agent-skills/publish-notes/SKILL.md'),
            'digest' => 'sha256:' . hash('sha256', $skillMd),
        ]],
    ]);
}

if ($path === '/.well-known/agent-skills/publish-notes/SKILL.md') {
    text_response(strtr(file_get_contents(__DIR__ . '/../skills/publish-notes/SKILL.md'), ['{{URL}}' => app_url()]), 'text/markdown; charset=utf-8');
}

if ($path === '/.well-known/oauth-protected-resource') {
    json_response([
        'resource' => app_url('/'),
        'authorization_servers' => [app_url()], // must byte-match the issuer in oauth-authorization-server
        'scopes_supported' => ['notes:read', 'notes:write'],
        'bearer_methods_supported' => ['header'],
    ]);
}

if ($path === '/llms.txt') {
    text_response(strtr(file_get_contents(__DIR__ . '/../views/llms.txt'), ['{{URL}}' => app_url()]));
}

if ($path === '/auth.md') {
    text_response(strtr(file_get_contents(__DIR__ . '/../views/auth.md'), ['{{URL}}' => app_url()]), 'text/markdown; charset=utf-8');
}

if ($path === '/sitemap.xml') {
    $urls = [
        ['loc' => app_url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['loc' => app_url('/docs'), 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['loc' => app_url('/register'), 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['loc' => app_url('/login'), 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['loc' => app_url('/auth.md'), 'changefreq' => 'monthly', 'priority' => '0.3'],
    ];
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $xml .= "  <url>\n"
              . "    <loc>" . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . "</loc>\n"
              . "    <changefreq>{$u['changefreq']}</changefreq>\n"
              . "    <priority>{$u['priority']}</priority>\n"
              . "  </url>\n";
    }
    $xml .= "</urlset>\n";
    text_response($xml, 'application/xml; charset=utf-8');
}

if ($path === '/robots.txt') { // PHP-served: the Sitemap line needs the dynamic site URL
    $body = file_get_contents(__DIR__ . '/../views/robots.txt');
    $body .= "\nSitemap: " . app_url('/sitemap.xml') . "\n";
    text_response($body);
}

// ---- Pages ----
if ($path === '/' || $path === '/docs') {
    send_discovery_links();
    $page = $path === '/' ? 'home' : 'docs';
    if (wants_markdown()) {
        text_response(strtr(file_get_contents(__DIR__ . "/../views/{$page}.md"), ['{{URL}}' => app_url()]), 'text/markdown; charset=utf-8');
    }
    render($page, ['title' => $path === '/' ? null : 'Docs']);
}

// ---- Registration ----
if ($path === '/register') {
    if (current_user()) {
        redirect('/dashboard');
    }
    if ($method === 'POST') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $error = register_user($_POST['username'] ?? '', $email, $_POST['password'] ?? '');
        if ($error === 'MAIL_FAILED') {
            render('verify', ['title' => 'Verify your email', 'email' => $email, 'error' => 'Your account was created, but we could not send the email. Use "Resend code" in a minute.', 'notice' => null]);
        }
        if ($error) {
            render('register', ['title' => 'Register', 'error' => $error, 'old' => $_POST]);
        }
        render('verify', ['title' => 'Verify your email', 'email' => $email, 'error' => null, 'notice' => 'We sent a 6-digit code to your email.']);
    }
    render('register', ['title' => 'Register', 'error' => null, 'old' => []]);
}

// ---- Email verification (also completes OTP sign-in) ----
if ($path === '/verify' && $method === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (isset($_POST['resend'])) {
        $error = send_otp($email, 'verify');
        render('verify', ['title' => 'Verify your email', 'email' => $email, 'error' => $error, 'notice' => $error ? null : 'A new code is on its way.']);
    }
    $result = otp_verify($email, $_POST['code'] ?? '');
    if (is_string($result)) {
        render('verify', ['title' => 'Verify your email', 'email' => $email, 'error' => $result, 'notice' => null]);
    }
    // First verification: mint an API token automatically and show it once
    $stmt = db()->prepare('SELECT COUNT(*) FROM api_tokens WHERE user_id = ?');
    $stmt->execute([$result['id']]);
    if ((int) $stmt->fetchColumn() === 0) {
        $token = create_api_token((int) $result['id'], 'default');
        render('token_created', ['title' => 'Your API token', 'token' => $token, 'first' => true]);
    }
    redirect('/dashboard');
}

// ---- Login (password OR email OTP) ----
if ($path === '/login') {
    if (current_user()) {
        redirect('/dashboard');
    }
    if ($method === 'POST') {
        if (($_POST['mode'] ?? '') === 'password') {
            $result = password_login($_POST['identifier'] ?? '', $_POST['password'] ?? '');
            if ($result === 'unverified') {
                render('verify', ['title' => 'Verify your email', 'email' => strtolower(trim($_POST['identifier'] ?? '')), 'error' => null, 'notice' => 'Your email is not verified yet — we just sent you a new code.']);
            }
            if (is_string($result)) {
                render('login', ['title' => 'Sign in', 'tab' => 'password', 'step' => 'start', 'email' => '', 'identifier' => $_POST['identifier'] ?? '', 'error' => $result]);
            }
            redirect('/dashboard');
        }
        // OTP mode: send a code
        $email = strtolower(trim($_POST['email'] ?? ''));
        $error = send_otp($email, 'login');
        render('login', ['title' => 'Sign in', 'tab' => 'otp', 'step' => $error ? 'start' : 'code', 'email' => $email, 'identifier' => '', 'error' => $error]);
    }
    render('login', ['title' => 'Sign in', 'tab' => 'password', 'step' => 'start', 'email' => '', 'identifier' => '', 'error' => null]);
}

if ($path === '/login/verify' && $method === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $result = otp_verify($email, $_POST['code'] ?? '');
    if (is_string($result)) {
        render('login', ['title' => 'Sign in', 'tab' => 'otp', 'step' => 'code', 'email' => $email, 'identifier' => '', 'error' => $result]);
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM api_tokens WHERE user_id = ?');
    $stmt->execute([$result['id']]);
    if ((int) $stmt->fetchColumn() === 0) {
        $token = create_api_token((int) $result['id'], 'default');
        render('token_created', ['title' => 'Your API token', 'token' => $token, 'first' => true]);
    }
    redirect('/dashboard');
}

if ($path === '/logout' && $method === 'POST') {
    csrf_check();
    logout();
    redirect('/');
}

// ---- Dashboard ----
if ($path === '/dashboard') {
    $user = require_login();
    $notes = list_notes((int) $user['id'], 200);
    $stmt = db()->prepare('SELECT id, name, prefix, last_used_at, created_at FROM api_tokens WHERE user_id = ? AND revoked_at IS NULL ORDER BY id');
    $stmt->execute([$user['id']]);
    render('dashboard', [
        'title' => 'Dashboard',
        'user' => $user,
        'notes' => $notes,
        'tokens' => $stmt->fetchAll(),
        'settings_error' => null,
        'settings_saved' => isset($_GET['saved']),
    ]);
}

if ($path === '/settings' && $method === 'POST') {
    $user = require_login();
    csrf_check();
    $error = update_account($user, $_POST['username'] ?? '', $_POST['new_password'] ?? '', $_POST['current_password'] ?? '');
    if ($error === null) {
        redirect('/dashboard?saved=1');
    }
    $notes = list_notes((int) $user['id'], 200);
    $stmt = db()->prepare('SELECT id, name, prefix, last_used_at, created_at FROM api_tokens WHERE user_id = ? AND revoked_at IS NULL ORDER BY id');
    $stmt->execute([$user['id']]);
    render('dashboard', [
        'title' => 'Dashboard',
        'user' => $user,
        'notes' => $notes,
        'tokens' => $stmt->fetchAll(),
        'settings_error' => $error,
        'settings_saved' => false,
    ]);
}

if ($path === '/notes/delete' && $method === 'POST') {
    $user = require_login();
    csrf_check();
    delete_note((int) $user['id'], $_POST['slug'] ?? '');
    redirect('/dashboard');
}

if ($path === '/tokens/create' && $method === 'POST') {
    $user = require_login();
    csrf_check();
    $token = create_api_token((int) $user['id'], trim($_POST['name'] ?? '') ?: 'token');
    render('token_created', ['title' => 'Your API token', 'token' => $token, 'first' => false]);
}

if ($path === '/tokens/revoke' && $method === 'POST') {
    $user = require_login();
    csrf_check();
    db()->prepare('UPDATE api_tokens SET revoked_at = NOW() WHERE id = ? AND user_id = ?')
        ->execute([(int) ($_POST['id'] ?? 0), $user['id']]);
    redirect('/dashboard');
}

if ($path === '/account/delete' && $method === 'POST') {
    $user = require_login();
    csrf_check();
    logout();
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]); // cascades to notes/tokens/sessions
    redirect('/');
}

// ---- WebMCP (browser-session variant of the MCP tools) ----
if ($path === '/webmcp' && $method === 'POST') {
    $user = current_user();
    if (!$user) {
        json_response(['error' => ['message' => 'Sign in at ' . app_url('/login') . ' to use these tools.']], 401);
    }
    $request = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!hash_equals(csrf_token(), (string) ($request['_csrf'] ?? ''))) {
        json_response(['error' => ['message' => 'CSRF token mismatch — reload the page.']], 419);
    }
    if (!is_array($request['arguments'] ?? null)) {
        $request['arguments'] = [];
    }
    mcp_tool_call($request['id'] ?? null, $request['name'] ?? '', $request['arguments'], (int) $user['id']);
}

// ---- Admin ----
if ($path === '/admin') {
    require_admin();
    $users = db()->query(
        'SELECT u.*, COUNT(n.id) AS note_count FROM users u LEFT JOIN notes n ON n.user_id = u.id
         GROUP BY u.id ORDER BY u.created_at DESC'
    )->fetchAll();
    render('admin/users', ['title' => 'Admin', 'users' => $users, 'export_error' => $_GET['export_error'] ?? null]);
}

if (preg_match('#^/admin/user/(\d+)$#', $path, $m)) {
    require_admin();
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int) $m[1]]);
    $target = $stmt->fetch();
    if (!$target) {
        http_response_code(404);
        render('error', ['title' => 'Not found', 'message' => 'No such user.']);
    }
    $stmt = db()->prepare('SELECT id, name, prefix, last_used_at, created_at, revoked_at FROM api_tokens WHERE user_id = ? ORDER BY id');
    $stmt->execute([$target['id']]);
    render('admin/user', [
        'title' => 'Admin · ' . $target['email'],
        'target' => $target,
        'notes' => list_notes((int) $target['id'], 200),
        'tokens' => $stmt->fetchAll(),
    ]);
}

if ($path === '/admin/user/delete' && $method === 'POST') {
    $admin = require_admin();
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id !== (int) $admin['id']) { // don't let the admin nuke itself from here
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
    redirect('/admin');
}

if ($path === '/admin/note/delete' && $method === 'POST') {
    require_admin();
    csrf_check();
    db()->prepare('DELETE FROM notes WHERE slug = ?')->execute([$_POST['slug'] ?? '']);
    $back = $_POST['back'] ?? '/admin';
    redirect(str_starts_with($back, '/') && !str_starts_with($back, '//') ? $back : '/admin');
}

if ($path === '/admin/export' && $method === 'POST') {
    require_admin();
    csrf_check();
    admin_db_export(); // streams .sql, or redirects to /admin with an error
}

// ---- 404 ----
http_response_code(404);
render('error', ['title' => 'Not found', 'message' => 'The page you are looking for does not exist.']);
