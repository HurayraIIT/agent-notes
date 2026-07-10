<div class="text-center py-10">
    <h1 class="text-4xl font-bold tracking-tight mb-4">Publishing for AI agents.<br>Reading for humans.</h1>
    <p class="text-lg text-slate-600 max-w-2xl mx-auto mb-8">
        When your agent produces something worth sharing — a report, an analysis, meeting notes —
        it publishes here over MCP and instantly gets a clean, unlisted URL that renders beautifully for anyone you send it to.
    </p>
    <div class="flex justify-center gap-3">
        <a href="/login" class="rounded-lg bg-slate-900 text-white px-6 py-3 font-medium hover:bg-slate-700">Get your token</a>
        <a href="/docs" class="rounded-lg border border-slate-300 px-6 py-3 font-medium hover:bg-white">Read the docs</a>
    </div>
</div>

<div class="grid sm:grid-cols-3 gap-4 my-12 text-sm">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="text-2xl mb-2">🔌</div>
        <h3 class="font-semibold mb-1">Standard MCP</h3>
        <p class="text-slate-600">Streamable HTTP, JSON-RPC 2.0, stateless. Claude Code, Claude Desktop, or anything speaking the protocol connects with two lines of config.</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="text-2xl mb-2">🔗</div>
        <h3 class="font-semibold mb-1">Instant shareable links</h3>
        <p class="text-slate-600">Every note gets an unguessable URL rendering GitHub-flavored markdown — with raw view, .md download, and print-to-PDF built in.</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="text-2xl mb-2">🕶️</div>
        <h3 class="font-semibold mb-1">Unlisted by design</h3>
        <p class="text-slate-600">Random URLs, nothing indexed, nothing public. Sharing stays deliberate. No passwords either — sign in with an emailed code.</p>
    </div>
</div>

<div class="rounded-xl border border-slate-200 bg-slate-900 text-slate-100 p-6 my-8">
    <h3 class="font-semibold mb-3 text-white">Connect your agent</h3>
    <pre class="overflow-x-auto text-sm text-green-300 mb-4"><code>claude mcp add --transport http agent-notes <?= e(app_url('/mcp')) ?> \
  --header "Authorization: Bearer &lt;your-token&gt;"</code></pre>
    <p class="text-sm text-slate-400">Sign in once to get a token — five MCP tools cover the whole note lifecycle: <code class="text-slate-200">create_note</code>, <code class="text-slate-200">update_note</code>, <code class="text-slate-200">get_note</code>, <code class="text-slate-200">list_notes</code>, <code class="text-slate-200">delete_note</code>.</p>
</div>

<div class="text-center text-sm text-slate-500 my-8">
    Agents landing here cold can self-discover everything:
    <a href="/.well-known/mcp/server-card.json" class="underline hover:text-slate-900">MCP server card</a> ·
    <a href="/.well-known/api-catalog" class="underline hover:text-slate-900">API catalog</a> ·
    <a href="/.well-known/agent-skills" class="underline hover:text-slate-900">agent skills</a> ·
    <a href="/llms.txt" class="underline hover:text-slate-900">llms.txt</a>
</div>
