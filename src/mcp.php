<?php

declare(strict_types=1);

const MCP_PROTOCOL_VERSION = '2025-06-18';

function mcp_tools(): array
{
    $tools = [
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
    return mcp_feature_tools($tools);
}

/** What each optional feature adds to the MCP surface. With every flag off this returns $tools unchanged. */
function mcp_feature_tools(array $tools): array
{
    $byName = array_column($tools, null, 'name');
    $slug = ['type' => 'string', 'description' => 'The note slug'];
    if (feature(Feature::HtmlNotes)) {
        $format = ['type' => 'string', 'enum' => ['markdown', 'html'], 'description' => '"markdown" (default) or "html". An html note is one complete HTML document served as its own page, scripts allowed, inside a browser sandbox (no cookies or storage; forms do not submit). Put images inline as data: URIs or upload them with upload_asset.'];
        $byName['create_note']['inputSchema']['properties']['format'] = $format;
        $byName['update_note']['inputSchema']['properties']['format'] = ['description' => 'Change the format (optional). ' . $format['description']] + $format;
    }
    if (feature(Feature::Visibility)) {
        $vis = ['type' => 'string', 'enum' => ['tailnet', 'public'],
            'description' => 'Who can open the URL. "tailnet" (default): only people on the private network. "public": anyone on the internet, at the public host. Choose "public" only when the note contains no secrets, credentials, tokens, private or internal hostnames and IPs, customer data or other personal data. When unsure, keep "tailnet" and use a share link instead.'];
        $byName['create_note']['inputSchema']['properties']['visibility'] = $vis;
        $byName['update_note']['inputSchema']['properties']['visibility'] = ['description' => 'Change who can open the note (optional; omitted leaves it as it is). ' . $vis['description']] + $vis;
    }
    if (feature(Feature::Assets)) {
        $max = asset_max_bytes();
        $byName['upload_asset'] = [
            'name' => 'upload_asset',
            'description' => "Attach an image to one of your notes and get back a path to embed: ![alt](path) in markdown, <img src=\"path\"> in an html note. PNG, JPEG, GIF or WebP up to {$max} bytes; the type is read from the bytes. SVG is refused (inline <svg> in an html note instead). The same image uploaded twice returns the same path. Assets are deleted with their note.",
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => $slug,
                    'data' => ['type' => 'string', 'description' => 'The image as base64, or as a data:image/...;base64, URI'],
                    'mime_type' => ['type' => 'string', 'description' => 'Optional: image/png, image/jpeg, image/gif or image/webp. Must match the bytes.'],
                ],
                'required' => ['slug', 'data'],
            ],
        ];
        $byName['delete_asset'] = [
            'name' => 'delete_asset',
            'description' => 'Delete one image from one of your notes. Pages that embed it show a broken image.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['slug' => $slug, 'name' => ['type' => 'string', 'description' => 'The asset name from upload_asset or get_note']],
                'required' => ['slug', 'name'],
            ],
        ];
    }
    if (feature(Feature::ShareLinks)) {
        [$default] = share_ttl_hours(null);
        $maxHours = env_int('SHARE_LINK_TTL_MAX_HOURS', 720);
        $byName['create_share_link'] = [
            'name' => 'create_share_link',
            'description' => "Make an expiring read-only link to one of your tailnet notes, for someone with no network access or account. The link opens that note and its images on the public host and nothing else. The URL is returned only once; treat it like a password. Revoke it with revoke_share_link.",
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => $slug,
                    'ttl_hours' => ['type' => 'integer', 'description' => "Hours until the link expires (default {$default}, max {$maxHours})"],
                ],
                'required' => ['slug'],
            ],
        ];
        $byName['list_share_links'] = [
            'name' => 'list_share_links',
            'description' => 'List share links on your notes (newest first) with status, expiry and use count. Link URLs are not shown again.',
            'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string', 'description' => 'Only this note (optional)']]],
        ];
        $byName['revoke_share_link'] = [
            'name' => 'revoke_share_link',
            'description' => 'Revoke a share link at once. Anyone holding it gets "not found" from then on.',
            'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer', 'description' => 'The link id from create_share_link or list_share_links']], 'required' => ['id']],
        ];
    }
    return array_values($byName);
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
        'filename' => $note['filename'] . '.' . note_format($note)->extension(),
        'url' => note_url($note['slug'], $note['visibility'] ?? null),
        'size_bytes' => (int) $note['size_bytes'],
        'created_at' => $note['created_at'],
        'updated_at' => $note['updated_at'],
    ];
    if (feature(Feature::HtmlNotes)) {
        $out['format'] = note_format($note)->value;
    }
    if (feature(Feature::Visibility)) {
        $out['visibility'] = note_visibility($note)->value;
    }
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
            [$format, $visibility, $error] = mcp_note_options($args);
            if ($error) {
                mcp_tool_result($id, ['error' => $error], true);
            }
            [$note, $error] = create_note($userId, $args['title'], $args['content'], isset($args['filename']) && is_string($args['filename']) ? $args['filename'] : null, $format, $visibility);
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
            [$format, $visibility, $error] = mcp_note_options($args);
            if ($error) {
                mcp_tool_result($id, ['error' => $error], true);
            }
            [$note, $error] = update_note(
                $userId,
                $args['slug'],
                isset($args['title']) && is_string($args['title']) ? $args['title'] : null,
                isset($args['content']) && is_string($args['content']) ? $args['content'] : null,
                isset($args['filename']) && is_string($args['filename']) ? $args['filename'] : null,
                $format,
                $visibility
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
            $out = note_summary($note, withContent: true);
            if (feature(Feature::Assets)) {
                $out['assets'] = array_map(fn($a) => asset_summary($note, $a), list_assets($note));
            }
            mcp_tool_result($id, $out);

        case 'list_notes':
            $notes = list_notes($userId, (int) ($args['limit'] ?? 50), (int) ($args['offset'] ?? 0));
            mcp_tool_result($id, [
                'count' => count($notes),
                'notes' => array_map(fn($n) => note_summary($n), $notes),
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
            mcp_feature_tool_call($id, $tool, $args, $userId);
    }
}

/** @return array{0: ?NoteFormat, 1: ?Visibility, 2: ?string} format and visibility args, when their features are on */
function mcp_note_options(array $args): array
{
    $format = $visibility = null;
    if (feature(Feature::HtmlNotes) && isset($args['format'])) {
        $format = is_string($args['format']) ? NoteFormat::tryFrom($args['format']) : null;
        if (!$format) {
            return [null, null, 'format must be "markdown" or "html"'];
        }
    }
    if (feature(Feature::Visibility) && isset($args['visibility'])) {
        $visibility = is_string($args['visibility']) ? Visibility::tryFrom($args['visibility']) : null;
        if (!$visibility) {
            return [null, null, 'visibility must be "tailnet" or "public"'];
        }
    }
    return [$format, $visibility, null];
}

/** Tools that exist only while their feature flag is on; anything else is an unknown tool, as before. */
function mcp_feature_tool_call(mixed $id, string $tool, array $args, int $userId): never
{
    $slug = isset($args['slug']) && is_string($args['slug']) ? $args['slug'] : '';
    if (feature(Feature::Assets) && ($tool === 'upload_asset' || $tool === 'delete_asset')) {
        $note = $slug !== '' ? get_note($slug, $userId) : null;
        if (!$note) {
            mcp_tool_result($id, ['error' => $slug === '' ? 'slug is required' : "note '{$slug}' not found"], true);
        }
        if ($tool === 'delete_asset') {
            if (!delete_asset($note, is_string($args['name'] ?? null) ? $args['name'] : '')) {
                mcp_tool_result($id, ['error' => 'no such asset on this note'], true);
            }
            mcp_tool_result($id, ['message' => 'Asset deleted.']);
        }
        if (!is_string($args['data'] ?? null)) {
            mcp_tool_result($id, ['error' => 'data is required: the image as base64'], true);
        }
        [$asset, $error] = store_asset($note, $args['data'], is_string($args['mime_type'] ?? null) ? $args['mime_type'] : null);
        if ($error) {
            mcp_tool_result($id, ['error' => $error], true);
        }
        mcp_tool_result($id, ['message' => 'Asset stored. Embed it by its path; it works on every host that serves the note.', 'mime_type' => $asset['mime']] + asset_summary($note, $asset));
    }
    if (feature(Feature::ShareLinks)) {
        switch ($tool) {
            case 'create_share_link':
                if ($slug === '') {
                    mcp_tool_result($id, ['error' => 'slug is required'], true);
                }
                $ttl = $args['ttl_hours'] ?? null;
                if ($ttl !== null && !is_int($ttl)) {
                    mcp_tool_result($id, ['error' => 'ttl_hours must be an integer'], true);
                }
                [$link, $error] = create_share_link($userId, $slug, $ttl, 'mcp');
                if ($error) {
                    mcp_tool_result($id, ['error' => $error], true);
                }
                mcp_tool_result($id, ['message' => 'Share link created. This is the only time its URL is shown; send it only to the person it is for.'] + $link);
            case 'list_share_links':
                $links = share_links_for($userId, $slug !== '' ? $slug : null);
                mcp_tool_result($id, ['count' => count($links), 'links' => $links]);
            case 'revoke_share_link':
                if (!is_int($args['id'] ?? null) || !revoke_share_link($userId, $args['id'])) {
                    mcp_tool_result($id, ['error' => 'no active share link with that id on your notes'], true);
                }
                mcp_tool_result($id, ['message' => 'Share link revoked. It stops working immediately.']);
        }
    }
    mcp_error($id, -32602, "Unknown tool: {$tool}");
}
