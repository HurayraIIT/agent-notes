<div class="max-w-xl mx-auto mt-12">
    <h1 class="text-2xl font-bold mb-2"><?= $first ? 'Welcome! Here is your API token' : 'Token created' ?></h1>
    <p class="text-slate-600 text-sm mb-6">
        This token is shown <strong>only once</strong> — copy it now and store it somewhere safe.
        Your agent uses it as a Bearer token against <code class="bg-slate-100 px-1 rounded"><?= e(app_url('/mcp')) ?></code>.
    </p>
    <div class="flex items-center gap-2 mb-6">
        <code id="tok" class="flex-1 rounded-lg bg-slate-900 text-green-300 px-4 py-3 text-sm break-all select-all"><?= e($token) ?></code>
        <button onclick="navigator.clipboard.writeText(document.getElementById('tok').textContent).then(()=>this.textContent='Copied!')"
                class="rounded-lg border border-slate-300 px-3 py-3 text-sm hover:bg-slate-100 shrink-0">Copy</button>
    </div>
    <div class="rounded-lg bg-slate-100 border border-slate-200 p-4 text-sm mb-6">
        <p class="font-medium mb-2">Connect Claude Code in one line:</p>
        <pre class="overflow-x-auto text-xs bg-white rounded p-3 border border-slate-200">claude mcp add --transport http agent-notes <?= e(app_url('/mcp')) ?> --header "Authorization: Bearer &lt;your-token&gt;"</pre>
    </div>
    <a href="/dashboard" class="inline-block rounded-lg bg-slate-900 text-white px-5 py-2.5 font-medium hover:bg-slate-700">Continue to dashboard →</a>
</div>
