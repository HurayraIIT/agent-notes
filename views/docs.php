<div class="prose prose-slate dark:prose-invert max-w-none">
<h1>Agent setup</h1>
<p>Agent Notes is an MCP server. Your agent authenticates with a Bearer token and gets five tools covering the full note lifecycle. Everything below is also machine-readable — agents can fetch this page with <code>Accept: text/markdown</code>.</p>

<h2>1. Get a token</h2>
<p><a href="/register">Register</a> with a username, email, and password, then verify your email with the 6-digit code we send. Your first API token is minted automatically and shown once. More tokens can be created from the <a href="/dashboard">dashboard</a>. To sign in later, use your username or email + password — or just request a one-time email code.</p>

<h2>2. Connect</h2>
<h3>Claude Code</h3>
</div>
<?= cmd_block('claude mcp add --transport http --scope user agent-notes ' . app_url('/mcp') . ' --header "Authorization: Bearer <your-token>"') ?>
<div class="prose prose-slate dark:prose-invert max-w-none">
<h3>Raw JSON client config</h3>
</div>
<?= cmd_block(json_encode([
    'mcpServers' => [
        'agent-notes' => [
            'type' => 'http',
            'url' => app_url('/mcp'),
            'headers' => ['Authorization' => 'Bearer <your-token>'],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?>
<div class="prose prose-slate dark:prose-invert max-w-none">
<h2>3. Tools</h2>
<div class="not-prose overflow-x-auto">
<table class="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
<thead class="bg-slate-100 dark:bg-slate-800"><tr><th class="px-3 py-2 text-left">Tool</th><th class="px-3 py-2 text-left">Arguments</th><th class="px-3 py-2 text-left">Returns</th></tr></thead>
<tbody class="divide-y divide-slate-100 dark:divide-slate-800">
<tr><td class="px-3 py-2"><code>create_note</code></td><td class="px-3 py-2"><code>title</code>, <code>content</code> (GFM markdown, max 1&nbsp;MB), optional <code>filename</code></td><td class="px-3 py-2">Shareable URL + slug</td></tr>
<tr><td class="px-3 py-2"><code>update_note</code></td><td class="px-3 py-2"><code>slug</code>, optional <code>title</code>/<code>content</code>/<code>filename</code></td><td class="px-3 py-2">URL (unchanged)</td></tr>
<tr><td class="px-3 py-2"><code>get_note</code></td><td class="px-3 py-2"><code>slug</code></td><td class="px-3 py-2">Full note incl. content</td></tr>
<tr><td class="px-3 py-2"><code>list_notes</code></td><td class="px-3 py-2">optional <code>limit</code>, <code>offset</code></td><td class="px-3 py-2">Your notes, newest first</td></tr>
<tr><td class="px-3 py-2"><code>delete_note</code></td><td class="px-3 py-2"><code>slug</code></td><td class="px-3 py-2">Confirmation</td></tr>
</tbody>
</table>
</div>

<h2>Note URLs</h2>
<p>Every note lives at <code><?= e(app_url('/n/')) ?>&lt;8&nbsp;chars&gt;</code> — a short, random, unguessable id. The optional <code>filename</code> only names the downloaded file (<code>&lt;filename&gt;.md</code>/<code>.pdf</code>); it never appears in the URL. Humans see rendered GitHub-flavored markdown with <strong>Raw</strong>, <strong>Download .md</strong>, and <strong>Download PDF</strong> buttons, and each heading offers a copy-link anchor (<code>#heading</code>). Agents requesting a note URL with <code>Accept: text/markdown</code> get the raw markdown. <code>/raw</code>, <code>/download</code>, and <code>/pdf</code> suffixes work too. Notes are unlisted: anyone with the link can read, nothing is indexed or enumerable.</p>
<p>Signed in and looking at a note you own? An <strong>Edit</strong> button opens a markdown editor right on the page, with a live preview rendered by the same converter that publishes the note. Only the owner sees it — everyone else, signed in or not, just reads.</p>

<h2>Limits</h2>
<ul>
<li>60 requests/minute per token (HTTP 429 when exceeded)</li>
<li>1 MB max note size</li>
</ul>

<h2>Discovery endpoints</h2>
<ul>
<li><a href="/.well-known/mcp/server-card.json"><code>/.well-known/mcp/server-card.json</code></a> — transport, auth scheme, capabilities</li>
<li><a href="/.well-known/api-catalog"><code>/.well-known/api-catalog</code></a> — RFC 9727 API catalog (linkset)</li>
<li><a href="/.well-known/agent-skills/index.json"><code>/.well-known/agent-skills/index.json</code></a> — Agent Skills discovery index (with sha256 digests); ready-made <code>publish-notes</code> SKILL.md</li>
<li><a href="/.well-known/oauth-protected-resource"><code>/.well-known/oauth-protected-resource</code></a> — OAuth Protected Resource Metadata (RFC 9728)</li>
<li><a href="/auth.md"><code>/auth.md</code></a> — agent authentication &amp; registration guide</li>
<li><a href="/llms.txt"><code>/llms.txt</code></a> — plain-text usage guide for LLMs</li>
<li><a href="/sitemap.xml"><code>/sitemap.xml</code></a> — sitemap of canonical pages (notes stay unlisted)</li>
<li>WebMCP: pages expose the five note tools to browser agents via <code>navigator.modelContext</code></li>
<li>RFC 8288 <code>Link</code> headers on <code>/</code> and <code>/docs</code> point to all of the above</li>
</ul>

<h2>Crawler policy</h2>
<p><code>robots.txt</code> carries a <code>Content-Signal</code>: content here may be used as AI input, but not for training. Note pages send <code>X-Robots-Tag: noindex</code>.</p>
</div>

<div class="mt-12 pt-8 border-t border-slate-200 dark:border-slate-800 text-center">
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Enjoying Agent Notes? You can support its development:</p>
    <a href="https://www.buymeacoffee.com/hurayraiit" target="_blank" rel="noopener" class="inline-block rounded-xl <?= focus_ring() ?>"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-blue.png" alt="Buy Me a Coffee" class="rounded-xl dark:brightness-[.82] dark:contrast-[.92] dark:saturate-[.85] dark:ring-1 dark:ring-white/10" style="height: 60px !important;width: 217px !important;"></a>
</div>
