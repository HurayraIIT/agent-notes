<?php
$mcpUrl = app_url('/mcp');
$claudeCmd = 'claude mcp add --transport http agent-notes ' . $mcpUrl . ' --header "Authorization: Bearer ' . $token . '"';
$jsonCfg = json_encode([
    'mcpServers' => [
        'agent-notes' => [
            'type' => 'http',
            'url' => $mcpUrl,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
<div class="max-w-2xl mx-auto mt-6 sm:mt-10">
    <h1 class="text-2xl font-bold mb-2"><?= $first ? '🎉 Welcome! Here is your API token' : 'Token created' ?></h1>
    <p class="text-slate-600 dark:text-slate-400 text-sm mb-6">
        This token is shown <strong class="text-slate-900 dark:text-white">only once</strong> — copy it now and store it somewhere safe.
        The commands below already include it, so they're ready to paste straight into your terminal.
    </p>

    <div data-cmd class="relative rounded-xl bg-slate-900 dark:bg-black/50 border border-slate-700/60 mb-6">
        <div class="px-4 pt-3 text-xs font-medium uppercase tracking-wider text-slate-400">Your API token</div>
        <pre class="whitespace-pre-wrap break-all px-4 py-3 pr-20 text-sm font-mono text-emerald-300"><?= e($token) ?></pre>
        <button onclick="copyCmd(this)" class="absolute top-2.5 right-2.5 rounded-md bg-slate-700/70 hover:bg-slate-600 text-slate-200 text-xs px-2.5 py-1.5">Copy</button>
    </div>

    <h2 class="font-semibold mb-1">Connect Claude Code</h2>
    <?= cmd_block($claudeCmd) ?>

    <h2 class="font-semibold mb-1 mt-6">Or any MCP client (JSON config)</h2>
    <?= cmd_block($jsonCfg) ?>

    <a href="/dashboard" class="inline-block mt-6 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 font-medium">Continue to dashboard →</a>
</div>
