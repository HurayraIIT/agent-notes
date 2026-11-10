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
<h3>Command Code</h3>
</div>
<?= cmd_block('cmd mcp add --transport http --scope user agent-notes ' . app_url('/mcp') . ' --header "Authorization: Bearer <your-token>"') ?>
<div class="prose prose-slate dark:prose-invert max-w-none">
<h3>Raw JSON client config</h3>
<p>Command Code's config file names the key <code>transport</code> instead of <code>type</code>; everything else is identical.</p>
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

<h2>Optional features</h2>
<p>A server may turn these on; <code>tools/list</code> shows which are live. Each is off unless the operator enables it.</p>
<ul>
<li><strong>HTML notes</strong>: <code>create_note</code> / <code>update_note</code> take <code>format: "html"</code>. The content is one complete HTML document, served as its own page with scripts allowed, inside a browser sandbox (<code>Content-Security-Policy: sandbox</code>, no same-origin): the page cannot read cookies or storage, cannot call the app as the Reader, and its forms do not submit. Embed images inline as <code>data:</code> URIs or upload them. <code>/raw</code> returns the source as plain text, <code>/download</code> an <code>.html</code> file; PDF export is markdown-only.</li>
<li><strong>Images</strong>: <code>upload_asset(slug, data, mime_type?)</code> attaches a base64 PNG, JPEG, GIF or WebP (default max 5&nbsp;MB) to one of your notes and returns a <code>path</code> like <code>/n/&lt;slug&gt;/assets/&lt;name&gt;</code>. Use it as <code>![alt](path)</code> in markdown or <code>&lt;img src="path"&gt;</code> in HTML. The type is read from the bytes; SVG is refused. The same image twice gives the same path. <code>delete_asset(slug, name)</code> removes one; <code>get_note</code> lists them; they are deleted with the note.</li>
<li><strong>Visibility</strong>: <code>create_note</code> / <code>update_note</code> take <code>visibility</code>: <code>"tailnet"</code> (default, private network only) or <code>"public"</code> (also on the public host). Choose <code>"public"</code> only when the note holds no secrets, credentials, tokens, private hostnames or IPs, customer or personal data. A tailnet note is "not found" on the public host. Changing visibility is always an explicit <code>update_note</code>.</li>
<li><strong>Share links</strong>: <code>create_share_link(slug, ttl_hours?)</code> returns a one-time <code>https://&lt;public host&gt;/s/&lt;token&gt;</code> URL that lets one person read one tailnet note and its images, read-only (default 7 days, max 30). <code>list_share_links(slug?)</code> shows status and use counts (never the URL again); <code>revoke_share_link(id)</code> ends it at once. Deleting the note ends its links too. The dashboard has the same controls.</li>
</ul>

<h2>Note URLs</h2>
<p>Every note lives at <code><?= e(app_url('/n/')) ?>&lt;id&gt;</code> — a random, unguessable id that never contains the title. The optional <code>filename</code> only names the downloaded file (<code>&lt;filename&gt;.md</code>/<code>.pdf</code>); it never appears in the URL. Humans see rendered GitHub-flavored markdown with <strong>Raw</strong>, <strong>Download .md</strong>, and <strong>Download PDF</strong> buttons, and each heading offers a copy-link anchor (<code>#heading</code>). Agents requesting a note URL with <code>Accept: text/markdown</code> get the raw markdown. <code>/raw</code>, <code>/download</code>, and <code>/pdf</code> suffixes work too. Notes are unlisted: anyone with the link can read, nothing is indexed or enumerable.</p>
<p>Beyond stock GFM, notes render <code>&lt;details&gt;</code>/<code>&lt;summary&gt;</code> (collapsible sections, as on a gist — leave a blank line after <code>&lt;/summary&gt;</code>), GitHub alerts (<code>&gt; [!NOTE]</code>, <code>&gt; [!TIP]</code>, <code>&gt; [!IMPORTANT]</code>, <code>&gt; [!WARNING]</code>, <code>&gt; [!CAUTION]</code>), and <code>```mermaid</code> diagrams. Inline formatting tags work inside a <code>&lt;summary&gt;</code> line; all other raw HTML is escaped.</p>
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
