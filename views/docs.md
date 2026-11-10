# Agent Notes — Agent Setup

Agent Notes is an MCP server. Authenticate with a Bearer token and you get five tools covering the full note lifecycle.

## 1. Get a token

Register at {{URL}}/register with a username, email, and password, then verify your email with the 6-digit code we send. Your first API token is minted automatically and shown once. Create more from {{URL}}/dashboard. Sign in later with username/email + password, or request a one-time email code at {{URL}}/login.

## 2. Connect

Claude Code:

```
claude mcp add --transport http --scope user agent-notes {{URL}}/mcp --header "Authorization: Bearer <your-token>"
```

Command Code:

```
cmd mcp add --transport http --scope user agent-notes {{URL}}/mcp --header "Authorization: Bearer <your-token>"
```

Raw JSON client config (Command Code's config file names the key `transport` instead of `type`; everything else is identical):

```json
{
  "mcpServers": {
    "agent-notes": {
      "type": "http",
      "url": "{{URL}}/mcp",
      "headers": { "Authorization": "Bearer <your-token>" }
    }
  }
}
```

## 3. Tools

| Tool | Arguments | Returns |
|------|-----------|---------|
| `create_note` | `title`, `content` (GFM markdown, max 1 MB), optional `filename` | Shareable URL + slug |
| `update_note` | `slug`, optional `title`/`content`/`filename` | URL (unchanged) |
| `get_note` | `slug` | Full note incl. content |
| `list_notes` | optional `limit`, `offset` | Your notes, newest first |
| `delete_note` | `slug` | Confirmation |

## Optional features

A server may turn these on; `tools/list` shows which are live. Each is off unless the operator enables it.

- **HTML notes**: `create_note` / `update_note` take `format: "html"`. The content is one complete HTML document, served as its own page with scripts allowed, inside a browser sandbox (`Content-Security-Policy: sandbox`, no same-origin): the page cannot read cookies or storage, cannot call the app as the Reader, and its forms do not submit. Embed images inline as `data:` URIs or upload them. `/raw` returns the source as plain text, `/download` an `.html` file; PDF export is markdown-only.
- **Images**: `upload_asset(slug, data, mime_type?)` attaches a base64 PNG, JPEG, GIF or WebP (default max 5 MB) to one of your notes and returns a `path` like `/n/<slug>/assets/<name>`. Use it as `![alt](path)` in markdown or `<img src="path">` in HTML. The type is read from the bytes; SVG is refused. The same image twice gives the same path. `delete_asset(slug, name)` removes one; `get_note` lists them; they are deleted with the note.
- **Visibility**: `create_note` / `update_note` take `visibility`: `"tailnet"` (default, private network only) or `"public"` (also on the public host). Choose `"public"` only when the note holds no secrets, credentials, tokens, private hostnames or IPs, customer or personal data. A tailnet note is "not found" on the public host. Changing visibility is always an explicit `update_note`.
- **Share links**: `create_share_link(slug, ttl_hours?)` returns a one-time `https://<public host>/s/<token>` URL that lets one person read one tailnet note and its images, read-only (default 7 days, max 30). `list_share_links(slug?)` shows status and use counts (never the URL again); `revoke_share_link(id)` ends it at once. Deleting the note ends its links too. The dashboard has the same controls.

## Note URLs

Every note lives at {{URL}}/n/<8 chars> — a short, random, unguessable id. The optional `filename` only names the downloaded file (`<filename>.md`/`.pdf`); it never appears in the URL. Humans get rendered GitHub-flavored markdown with Raw / Download .md / Download PDF buttons, and each heading has a copy-link anchor (`#heading`). Requesting a note URL with `Accept: text/markdown` returns raw markdown; `/raw`, `/download`, and `/pdf` suffixes also work. Notes are unlisted: anyone with the link can read, nothing is indexed or enumerable.

Beyond stock GFM, notes render `<details>`/`<summary>` (collapsible sections, as on a gist — leave a blank line after `</summary>`), GitHub alerts (`> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]`, `> [!CAUTION]`), and ```` ```mermaid ```` diagrams. Inline formatting tags work inside a `<summary>` line; all other raw HTML is escaped.

Signed in and looking at a note you own? An **Edit** button opens a markdown editor right on the page, with a live preview rendered by the same converter that publishes the note. Only the owner sees it — everyone else, signed in or not, just reads.

## Limits

- 60 requests/minute per token (HTTP 429 when exceeded)
- 1 MB max note size

## Discovery

- {{URL}}/.well-known/mcp/server-card.json — MCP server card
- {{URL}}/.well-known/api-catalog — RFC 9727 API catalog (linkset)
- {{URL}}/.well-known/agent-skills/index.json — Agent Skills discovery index (sha256 digests); publish-notes SKILL.md
- {{URL}}/.well-known/oauth-protected-resource — OAuth Protected Resource Metadata (RFC 9728)
- {{URL}}/auth.md — agent authentication & registration guide
- {{URL}}/llms.txt — plain-text LLM guide
- {{URL}}/sitemap.xml — sitemap of canonical pages (notes stay unlisted)
- WebMCP: pages expose the five note tools to browser agents via navigator.modelContext
- RFC 8288 Link headers on / and /docs point to all of the above

## Crawler policy

robots.txt carries a Content-Signal: content may be used as AI input, not for training. Note pages send X-Robots-Tag: noindex.
