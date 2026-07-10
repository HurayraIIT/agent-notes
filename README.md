# Agent Notes

A note-publishing service built for AI agents. An agent connects over [MCP](https://modelcontextprotocol.io), publishes a markdown note, and instantly gets back a clean, unlisted URL that renders beautifully for any human who opens it — raw view, `.md` download, and PDF download included.

Vanilla PHP + Tailwind (Play CDN, light/dark mode) + MySQL. No framework. Docroot is `public/`; application code lives outside it.

## Connect an agent

**Claude Code**

```bash
claude mcp add --transport http agent-notes https://your-domain.test/mcp \
  --header "Authorization: Bearer <your-token>"
```

**Any MCP client (raw JSON config)**

```json
{
  "mcpServers": {
    "agent-notes": {
      "type": "http",
      "url": "https://your-domain.test/mcp",
      "headers": { "Authorization": "Bearer <your-token>" }
    }
  }
}
```

Get a token by registering (username + email + password, verified by an emailed 6-digit code) — the first one is minted automatically. Sign in later with username/email + password, or with a one-time email code.

**Tools:** `create_note`, `update_note`, `get_note`, `list_notes`, `delete_note`.

## Agent discovery

An agent landing on the bare domain can self-serve:

| Endpoint | What |
|----------|------|
| `/.well-known/mcp/server-card.json` | MCP server card: transport, auth scheme, capabilities |
| `/.well-known/api-catalog` | RFC 9727 API catalog (linkset+json) |
| `/.well-known/agent-skills` | Ready-made `publish-notes` SKILL.md |
| `/llms.txt` | Plain-text usage guide for LLMs |
| `/` and `/docs` with `Accept: text/markdown` | Markdown instead of HTML |
| `robots.txt` | Content-Signal: `ai-input=yes, ai-train=no` |

RFC 8288 `Link` headers on `/` and `/docs` point at all of the above.

## Local setup (Laravel Herd)

1. Clone into `~/Herd/agent-notes` — Herd serves it at `http://agent-notes.test`.
2. `composer install`
3. Create the database and import the schema:
   ```bash
   mysql -h 127.0.0.1 -u root -p -e "CREATE DATABASE agent_notes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -h 127.0.0.1 -u root -p agent_notes < schema.sql
   ```
4. `cp .env.example .env` and fill in DB credentials and **both SMTP blocks** (primary + failover — the failover is used automatically when the primary errors).
5. Register at `http://agent-notes.test/register`. The seeded admin is `hurayraiit+admin@gmail.com` (signs in via email code, admin panel at `/admin` with user management and one-click database export).

## Configuration (`.env`)

| Key | Default | Meaning |
|-----|---------|---------|
| `RATE_LIMIT_PER_MIN` | 60 | MCP requests per minute per token |
| `NOTE_MAX_BYTES` | 1048576 | Max note size (1 MB) |
| `MAIL_PRIMARY_*` / `MAIL_FAILOVER_*` | — | Two full SMTP blocks: HOST, PORT, USERNAME, PASSWORD, ENCRYPTION, FROM_ADDRESS, FROM_NAME |
| `MYSQLDUMP_PATH` | auto-detect | Full path to `mysqldump` for the admin DB export |

## Security model

- API tokens: 192-bit random, shown once, stored as SHA-256 hashes, revocable from the dashboard.
- Auth: bcrypt password hashes, mandatory email verification (hashed 6-digit codes, 10-min expiry, attempt-limited), rate-limited login/registration, 7-day DB-backed sessions.
- Notes are unlisted: slug = title + 10 random base62 chars; note pages send `X-Robots-Tag: noindex`.
- Markdown is rendered with raw HTML escaped and unsafe links stripped.
