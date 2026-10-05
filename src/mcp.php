<?php

declare(strict_types=1);

const MCP_PROTOCOL_VERSION = '2025-06-18';

function mcp_tools(): array
{
    return [
        [
            'name' => 'create_note',
            'description' => 'Publish a markdown note and get back a shareable URL. Notes render as GitHub-flavored markdown for humans, with raw view, .md download, and print-to-PDF. URLs are unlisted (cryptographically random), so sharing stays deliberate.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Note title, shown as the page heading'],
                    'content' => ['type' => 'string', 'description' => 'Note body as GitHub-flavored markdown (max 1 MB)'],
                    'filename' => ['type' => 'string', 'description' => 'Optional download filename, e.g. "incident-report" downloads as incident-report.md. Slugified; defaults to the title. Does NOT affect the URL.'],
                ],
                'required' => ['title', 'content'],
            ],
        ],
        [
            'name' => 'update_note',
            'description' => 'Update the title and/or content of one of your notes. The slug and URL stay the same.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'description' => 'The note slug (from create_note or list_notes)'],
                    'title' => ['type' => 'string', 'description' => 'New title (optional)'],
                    'content' => ['type' => 'string', 'description' => 'New markdown content, replaces the old content entirely (optional)'],
                    'filename' => ['type' => 'string', 'description' => 'New download filename, slugified (optional)'],
                ],
                'required' => ['slug'],
            ],
        ],
        [
            'name' => 'get_note',
            'description' => 'Retrieve one of your notes, including its full markdown content.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'description' => 'The note slug'],
                ],
                'required' => ['slug'],
            ],
        ],
        [
            'name' => 'list_notes',
            'description' => 'List your notes, most recently updated first.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'description' => 'Max notes to return (default 50, max 200)'],
                    'offset' => ['type' => 'integer', 'description' => 'Number of notes to skip, for pagination (default 0)'],
                ],
            ],
        ],
        [
            'name' => 'delete_note',
            'description' => 'Permanently delete one of your notes. The URL stops working immediately.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'description' => 'The note slug'],
                ],
                'required' => ['slug'],
            ],
        ],
    ];
}

function mcp_error(mixed $id, int $code, string $message, int $httpStatus = 200): never
{
    json_response(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]], $httpStatus);
}

function mcp_result(mixed $id, array $result): never
{
    json_response(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
}

function mcp_tool_result(mixed $id, array $data, bool $isError = false): never
{
    $result = [
        'content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
    ];
    if ($isError) {
        $result['isError'] = true;
    }
    mcp_result($id, $result);
}

function note_summary(array $note, bool $withContent = false): array
{
    $out = [
        'slug' => $note['slug'],
        'title' => $note['title'],
        'filename' => $note['filename'] . '.md',
        'url' => note_url($note['slug']),
        'size_bytes' => (int) $note['size_bytes'],
        'created_at' => $note['created_at'],
        'updated_at' => $note['updated_at'],
    ];
    if ($withContent) {
        $out['content'] = $note['content'];
    }
    return $out;
}

function mcp_handle(): never
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        json_response(['error' => 'Method not allowed. MCP requests must be POSTed as JSON-RPC 2.0.'], 405);
    }

    $bearer = null;
    if (preg_match('/^Bearer\s+(\S+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) {
        $bearer = $m[1];
    }
    $user = user_from_bearer_token($bearer);
    if (!$user) {
        header('WWW-Authenticate: Bearer realm="Agent Notes MCP", error="invalid_token"');
        mcp_error(null, -32001, 'Unauthorized: provide a valid API token in the Authorization: Bearer header. Get one at ' . app_url('/login'), 401);
    }

    if (rate_limited('mcp:' . $user['token_id'], (int) env('RATE_LIMIT_PER_MIN', '60'))) {
        header('Retry-After: 60');
        mcp_error(null, -32029, 'Rate limit exceeded (' . env('RATE_LIMIT_PER_MIN', '60') . ' requests/minute). Retry in a minute.', 429);
    }

    $request = json_decode(file_get_contents('php://input'), true);
    if (!is_array($request)) {
        mcp_error(null, -32700, 'Parse error: request body must be a JSON-RPC 2.0 object', 400);
    }

    $method = $request['method'] ?? '';
    $id = $request['id'] ?? null;
    $params = $request['params'] ?? [];

    if (str_starts_with($method, 'notifications/')) {
        http_response_code(202);
        exit;
    }

    switch ($method) {
        case 'initialize':
            mcp_result($id, [
                'protocolVersion' => MCP_PROTOCOL_VERSION,
                'capabilities' => ['tools' => (object) []],
                'serverInfo' => ['name' => 'agent-notes', 'title' => env('APP_NAME', 'Agent Notes'), 'version' => '1.0.0'],
                'instructions' => 'Agent Notes publishes markdown notes to clean, unlisted, shareable URLs. Use create_note to publish and get a URL you can hand to humans; update_note, get_note, list_notes, and delete_note manage the lifecycle. Notes are private-by-obscurity: anyone with the URL can read, nothing is listed or indexed.',
            ]);

        case 'ping':
            mcp_result($id, []);

        case 'tools/list':
            mcp_result($id, ['tools' => mcp_tools()]);

        case 'tools/call':
            mcp_tool_call($id, $params['name'] ?? '', $params['arguments'] ?? [], (int) $user['id']);

        default:
            mcp_error($id, -32601, "Method not found: {$method}");
    }
}

function mcp_tool_call(mixed $id, string $tool, array $args, int $userId): never
{
    switch ($tool) {
        case 'create_note':
            if (!isset($args['title'], $args['content']) || !is_string($args['title']) || !is_string($args['content'])) {
                mcp_tool_result($id, ['error' => 'title and content are required strings'], true);
            }
            [$note, $error] = create_note($userId, $args['title'], $args['content'], isset($args['filename']) && is_string($args['filename']) ? $args['filename'] : null);
            if ($error) {
                mcp_tool_result($id, ['error' => $error], true);
            }
            mcp_tool_result($id, [
                'message' => 'Note published. Share the URL with anyone — it renders as a clean web page.',
                ...note_summary($note),
            ]);

        case 'update_note':
            if (empty($args['slug']) || !is_string($args['slug'])) {
                mcp_tool_result($id, ['error' => 'slug is required'], true);
            }
            [$note, $error] = update_note(
                $userId,
                $args['slug'],
                isset($args['title']) && is_string($args['title']) ? $args['title'] : null,
                isset($args['content']) && is_string($args['content']) ? $args['content'] : null,
                isset($args['filename']) && is_string($args['filename']) ? $args['filename'] : null
            );
            if ($error) {
                mcp_tool_result($id, ['error' => $error], true);
            }
            mcp_tool_result($id, ['message' => 'Note updated. The URL is unchanged.', ...note_summary($note)]);

        case 'get_note':
            if (empty($args['slug']) || !is_string($args['slug'])) {
                mcp_tool_result($id, ['error' => 'slug is required'], true);
            }
            $note = get_note($args['slug'], $userId);
            if (!$note) {
                mcp_tool_result($id, ['error' => "note '{$args['slug']}' not found"], true);
            }
            mcp_tool_result($id, note_summary($note, withContent: true));

        case 'list_notes':
            $notes = list_notes($userId, (int) ($args['limit'] ?? 50), (int) ($args['offset'] ?? 0));
            mcp_tool_result($id, [
                'count' => count($notes),
                'notes' => array_map(fn($n) => [
                    'slug' => $n['slug'],
                    'title' => $n['title'],
                    'filename' => $n['filename'] . '.md',
                    'url' => note_url($n['slug']),
                    'size_bytes' => (int) $n['size_bytes'],
                    'created_at' => $n['created_at'],
                    'updated_at' => $n['updated_at'],
                ], $notes),
            ]);

        case 'delete_note':
            if (empty($args['slug']) || !is_string($args['slug'])) {
                mcp_tool_result($id, ['error' => 'slug is required'], true);
            }
            if (!delete_note($userId, $args['slug'])) {
                mcp_tool_result($id, ['error' => "note '{$args['slug']}' not found"], true);
            }
            mcp_tool_result($id, ['message' => "Note '{$args['slug']}' deleted. Its URL no longer works."]);

        default:
            mcp_error($id, -32602, "Unknown tool: {$tool}");
    }
}
