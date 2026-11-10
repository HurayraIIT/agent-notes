<?php

// Upstream promise: with every FEATURE_* flag unset, an existing install sees exactly the MCP surface it
// had before HTML notes, assets, visibility and share links existed. Each flag adds its own tool or
// argument and nothing else. Run: php tests/features_off.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$failed = 0;
$fail = function (string $why) use (&$failed): void {
    $failed++;
    echo "FAIL: $why\n";
};
$flags = ['FEATURE_HTML_NOTES', 'FEATURE_ASSETS', 'FEATURE_VISIBILITY', 'FEATURE_SHARE_LINKS'];
$set = function (array $on) use ($flags): void {
    foreach ($flags as $f) {
        unset($_ENV[$f]);
    }
    foreach ($on as $f) {
        $_ENV[$f] = 'true';
    }
};
$tools = fn(): array => array_column(mcp_tools(), null, 'name');
$props = fn(array $t, string $name): array => array_keys($t[$name]['inputSchema']['properties']);

// --- 1. All flags off: the original five tools, with their original arguments -------------------
$set([]);
$t = $tools();
array_keys($t) === ['create_note', 'update_note', 'get_note', 'list_notes', 'delete_note']
    || $fail('flags off: tool list changed: ' . implode(',', array_keys($t)));
$props($t, 'create_note') === ['title', 'content', 'filename'] || $fail('flags off: create_note arguments changed');
$props($t, 'update_note') === ['slug', 'title', 'content', 'filename'] || $fail('flags off: update_note arguments changed');
request_scope() === Scope::Full || $fail('flags off: request is not full scope');
$_SERVER['AGENT_NOTES_SCOPE'] = 'public';
request_scope() === Scope::Full || $fail('flags off: AGENT_NOTES_SCOPE must be ignored without FEATURE_VISIBILITY');
unset($_SERVER['AGENT_NOTES_SCOPE']);
$summary = note_summary(['slug' => 'abc', 'title' => 'T', 'filename' => 'f', 'size_bytes' => 1,
    'created_at' => 'x', 'updated_at' => 'y']);
array_keys($summary) === ['slug', 'title', 'filename', 'url', 'size_bytes', 'created_at', 'updated_at']
    || $fail('flags off: note_summary keys changed: ' . implode(',', array_keys($summary)));
$summary['filename'] === 'f.md' || $fail('flags off: filename extension changed');
note_columns() === 'id, user_id, slug, title, filename, content, size_bytes, views, created_at, updated_at'
    || $fail('flags off: get_note selects different columns (an un-migrated install would break)');

// --- 2. Each flag adds only its own surface -------------------------------------------------------
$set(['FEATURE_HTML_NOTES']);
in_array('format', $props($tools(), 'create_note'), true) || $fail('html flag: create_note lacks format');
in_array('visibility', $props($tools(), 'create_note'), true) && $fail('html flag alone exposes visibility');
note_summary(['slug' => 'a', 'title' => 'T', 'filename' => 'f', 'size_bytes' => 1, 'created_at' => 'x',
    'updated_at' => 'y', 'format' => 'html'])['filename'] === 'f.html' || $fail('html note filename is not .html');

$set(['FEATURE_ASSETS']);
isset($tools()['upload_asset'], $tools()['delete_asset']) || $fail('assets flag: upload_asset/delete_asset missing');
isset($tools()['create_share_link']) && $fail('assets flag alone exposes share links');

$set(['FEATURE_VISIBILITY']);
in_array('visibility', $props($tools(), 'create_note'), true) || $fail('visibility flag: create_note lacks visibility');
in_array('visibility', $props($tools(), 'update_note'), true) || $fail('visibility flag: update_note lacks visibility');
$desc = $tools()['create_note']['inputSchema']['properties']['visibility']['description'] ?? '';
foreach (['secret', 'credential', 'hostname', 'personal'] as $word) {
    str_contains(strtolower($desc), $word) || $fail("visibility description does not warn about $word");
}
($tools()['create_note']['inputSchema']['properties']['visibility']['enum'] ?? null) === ['tailnet', 'public']
    || $fail('visibility enum is not [tailnet, public]');

$set(['FEATURE_SHARE_LINKS']);
isset($tools()['create_share_link']) && $fail('share links must stay off without FEATURE_VISIBILITY');
$set(['FEATURE_VISIBILITY', 'FEATURE_SHARE_LINKS']);
foreach (['create_share_link', 'list_share_links', 'revoke_share_link'] as $name) {
    isset($tools()[$name]) || $fail("share flag: $name missing");
}

echo $failed === 0 ? "features_off: flags off keep the original surface; each flag adds only its own\n" : "features_off: $failed failing\n";
exit($failed === 0 ? 0 : 1);
