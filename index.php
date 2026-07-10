<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// ---- MCP (core product) ----
if ($path === '/mcp') {
    mcp_handle();
}

// ---- Notes ----
if (preg_match('#^/n/([A-Za-z0-9-]+)(/raw|/download)?$#', $path, $m)) {
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
    http_response_code(200);
    echo view('note', ['note' => $note, 'html' => markdown_to_html($note['content'])]);
    exit;
}

// ---- Agent discovery ----
if ($path === '/.well-known/mcp/server-card.json') {
    json_response([
        'name' => 'agent-notes',
        'title' => env('APP_NAME', 'Agent Notes'),
        'description' => 'Note-publishing service for AI agents. Publish markdown over MCP, get back a clean unlisted URL that renders for humans.',
        'url' => app_url('/mcp'),
        'transport' => ['type' => 'streamable-http', 'endpoint' => app_url('/mcp')],
        'protocolVersion' => MCP_PROTOCOL_VERSION,
        'authentication' => [
            'type' => 'bearer',
            'description' => 'API token in the Authorization: Bearer header. Sign in at ' . app_url('/login') . ' to get one.',
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
            'service-meta' => [['href' => app_url('/llms.txt'), 'type' => 'text/plain', 'title' => 'LLM usage guide']],
            'item' => [['href' => app_url('/mcp'), 'title' => 'MCP endpoint (Streamable HTTP, JSON-RPC 2.0)']],
        ]],
    ]);
}

if ($path === '/.well-known/agent-skills') {
    json_response([
        'skills' => [[
            'name' => 'publish-notes',
            'description' => 'Publish markdown notes to shareable URLs via the Agent Notes MCP server.',
            'url' => app_url('/.well-known/agent-skills/publish-notes/SKILL.md'),
        ]],
    ]);
}

if ($path === '/.well-known/agent-skills/publish-notes/SKILL.md') {
    text_response(strtr(file_get_contents(__DIR__ . '/skills/publish-notes/SKILL.md'), ['{{URL}}' => app_url()]), 'text/markdown; charset=utf-8');
}

if ($path === '/robots.txt') { // served via PHP: Herd's nginx 404s static root files
    text_response(file_get_contents(__DIR__ . '/views/robots.txt'));
}

if ($path === '/llms.txt') {
    text_response(strtr(file_get_contents(__DIR__ . '/views/llms.txt'), ['{{URL}}' => app_url()]));
}

// ---- Pages ----
if ($path === '/' || $path === '/docs') {
    send_discovery_links();
    $page = $path === '/' ? 'home' : 'docs';
    if (wants_markdown()) {
        text_response(strtr(file_get_contents(__DIR__ . "/views/{$page}.md"), ['{{URL}}' => app_url()]), 'text/markdown; charset=utf-8');
    }
    render($page, ['title' => $path === '/' ? null : 'Docs']);
}

// ---- Auth ----
if ($path === '/login') {
    if (current_user()) {
        redirect('/dashboard');
    }
    if ($method === 'POST') {
        $email = $_POST['email'] ?? '';
        $error = login_send_code($email);
        render('login', ['title' => 'Sign in', 'step' => $error ? 'email' : 'code', 'email' => $email, 'error' => $error]);
    }
    render('login', ['title' => 'Sign in', 'step' => 'email', 'email' => '', 'error' => null]);
}

if ($path === '/login/verify' && $method === 'POST') {
    $email = $_POST['email'] ?? '';
    $result = login_verify($email, $_POST['code'] ?? '');
    if (is_string($result)) {
        render('login', ['title' => 'Sign in', 'step' => 'code', 'email' => $email, 'error' => $result]);
    }
    // First login: mint an API token automatically and show it once
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
    render('dashboard', ['title' => 'Dashboard', 'user' => $user, 'notes' => $notes, 'tokens' => $stmt->fetchAll()]);
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

// ---- Admin ----
if ($path === '/admin') {
    require_admin();
    $users = db()->query(
        'SELECT u.*, COUNT(n.id) AS note_count FROM users u LEFT JOIN notes n ON n.user_id = u.id
         GROUP BY u.id ORDER BY u.created_at DESC'
    )->fetchAll();
    render('admin/users', ['title' => 'Admin', 'users' => $users]);
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
    redirect($_POST['back'] ?? '/admin');
}

// ---- 404 ----
http_response_code(404);
render('error', ['title' => 'Not found', 'message' => 'The page you are looking for does not exist.']);
