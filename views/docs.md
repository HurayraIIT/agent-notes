# Agent Notes — Agent Setup

Agent Notes is an MCP server. Authenticate with a Bearer token and you get five tools covering the full note lifecycle.

## 1. Get a token

Register at {{URL}}/register with a username, email, and password, then verify your email with the 6-digit code we send. Your first API token is minted automatically and shown once. Create more from {{URL}}/dashboard. Sign in later with username/email + password, or request a one-time email code at {{URL}}/login.

## 2. Connect

Claude Code:

```
claude mcp add --transport http --scope user agent-notes {{URL}}/mcp --header "Authorization: Bearer <your-token>"
```

Raw JSON client config:

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

## Note URLs

Every note lives at {{URL}}/n/<8 chars> — a short, random, unguessable id. The optional `filename` only names the downloaded file (`<filename>.md`/`.pdf`); it never appears in the URL. Humans get rendered GitHub-flavored markdown with Raw / Download .md / Download PDF buttons, and each heading has a copy-link anchor (`#heading`). Requesting a note URL with `Accept: text/markdown` returns raw markdown; `/raw`, `/download`, and `/pdf` suffixes also work. Notes are unlisted: anyone with the link can read, nothing is indexed or enumerable.

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
