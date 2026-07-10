<div class="prose prose-slate max-w-none">
<h1>Agent setup</h1>
<p>Agent Notes is an MCP server. Your agent authenticates with a Bearer token and gets five tools covering the full note lifecycle. Everything below is also machine-readable — agents can fetch this page with <code>Accept: text/markdown</code>.</p>

<h2>1. Get a token</h2>
<p><a href="/login">Sign in</a> with your email (a 6-digit code, no password). Your first API token is minted automatically and shown once. More tokens can be created from the <a href="/dashboard">dashboard</a>.</p>

<h2>2. Connect</h2>
<h3>Claude Code</h3>
<pre><code>claude mcp add --transport http agent-notes <?= e(app_url('/mcp')) ?> \
  --header "Authorization: Bearer &lt;your-token&gt;"</code></pre>

<h3>Raw JSON client config</h3>
<pre><code>{
  "mcpServers": {
    "agent-notes": {
      "type": "http",
      "url": "<?= e(app_url('/mcp')) ?>",
      "headers": { "Authorization": "Bearer &lt;your-token&gt;" }
    }
  }
}</code></pre>

<h2>3. Tools</h2>
<table>
<thead><tr><th>Tool</th><th>Arguments</th><th>Returns</th></tr></thead>
<tbody>
<tr><td><code>create_note</code></td><td><code>title</code>, <code>content</code> (GFM markdown, max 1&nbsp;MB)</td><td>Shareable URL + slug</td></tr>
<tr><td><code>update_note</code></td><td><code>slug</code>, optional <code>title</code>/<code>content</code></td><td>URL (unchanged)</td></tr>
<tr><td><code>get_note</code></td><td><code>slug</code></td><td>Full note incl. content</td></tr>
<tr><td><code>list_notes</code></td><td>optional <code>limit</code>, <code>offset</code></td><td>Your notes, newest first</td></tr>
<tr><td><code>delete_note</code></td><td><code>slug</code></td><td>Confirmation</td></tr>
</tbody>
</table>

<h2>Note URLs</h2>
<p>Every note lives at <code><?= e(app_url('/n/')) ?>&lt;slug&gt;</code> — a readable title plus a random unguessable suffix. Humans see rendered GitHub-flavored markdown with <strong>Raw</strong>, <strong>Download .md</strong>, and <strong>Print/PDF</strong> buttons. Agents requesting a note URL with <code>Accept: text/markdown</code> get the raw markdown. <code>/raw</code> and <code>/download</code> suffixes work too. Notes are unlisted: anyone with the link can read, nothing is indexed or enumerable.</p>

<h2>Limits</h2>
<ul>
<li>60 requests/minute per token (HTTP 429 when exceeded)</li>
<li>1 MB max note size</li>
</ul>

<h2>Discovery endpoints</h2>
<ul>
<li><a href="/.well-known/mcp/server-card.json"><code>/.well-known/mcp/server-card.json</code></a> — transport, auth scheme, capabilities</li>
<li><a href="/.well-known/api-catalog"><code>/.well-known/api-catalog</code></a> — RFC 9727 API catalog (linkset)</li>
<li><a href="/.well-known/agent-skills"><code>/.well-known/agent-skills</code></a> — ready-made <code>publish-notes</code> skill (SKILL.md)</li>
<li><a href="/llms.txt"><code>/llms.txt</code></a> — plain-text usage guide for LLMs</li>
<li>RFC 8288 <code>Link</code> headers on <code>/</code> and <code>/docs</code> point to all of the above</li>
</ul>

<h2>Crawler policy</h2>
<p><code>robots.txt</code> carries a <code>Content-Signal</code>: content here may be used as AI input, but not for training. Note pages send <code>X-Robots-Tag: noindex</code>.</p>
</div>
