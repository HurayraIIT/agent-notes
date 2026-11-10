# Agent Notes

A note-publishing service built for AI agents. An agent connects over [MCP](https://modelcontextprotocol.io), publishes a markdown note, and instantly gets back a clean, unlisted URL that renders beautifully for any human who opens it — raw view, `.md` download, PDF download, and a one-click copy-link button included.

Vanilla PHP + Tailwind (Play CDN, light/dark mode) + MySQL. No framework. Docroot is `public/`; application code lives outside it.

## Connect an agent

**Claude Code**

```bash
claude mcp add --transport http --scope user agent-notes https://your-domain.test/mcp \
  --header "Authorization: Bearer <your-token>"
```

**Command Code**

```bash
cmd mcp add --transport http --scope user agent-notes https://your-domain.test/mcp \
  --header "Authorization: Bearer <your-token>"
```

**Any MCP client (raw JSON config)** — Command Code's config file names the key `transport` instead of `type`; everything else is identical.

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

**Optional tools and arguments** (each behind a flag, off by default; see Configuration):

| Flag | Adds |
|------|------|
| `FEATURE_HTML_NOTES` | `format: "markdown" \| "html"` on `create_note`/`update_note`. An HTML note is one full page, served top-level with scripts allowed under `Content-Security-Policy: sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox allow-downloads` (no `allow-same-origin`). |
| `FEATURE_ASSETS` | `upload_asset(slug, data, mime_type?)` and `delete_asset(slug, name)`: base64 PNG/JPEG/GIF/WebP up to `ASSET_MAX_BYTES` (5 MB), typed by magic bytes, SVG refused. Returns `/n/<slug>/assets/<name>` for markdown or HTML. Stored on disk outside `public/`, deleted with the note. |
| `FEATURE_VISIBILITY` | `visibility: "tailnet" \| "public"` (default `tailnet`). Two listeners: the full app, and a read-only public host that serves only public notes and their assets. See [deploy/README.md](deploy/README.md). |
| `FEATURE_SHARE_LINKS` | `create_share_link(slug, ttl_hours?)`, `list_share_links(slug?)`, `revoke_share_link(id)`, plus dashboard controls: expiring read links to one tailnet note, opened on the public host. Needs `FEATURE_VISIBILITY`. |

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

## Production Deployment

1. **Server Requirements**: PHP >= 8.2 and MySQL/MariaDB.
2. **Code & Dependencies**: Clone or upload the repository to your server. Run `composer install --no-dev --optimize-autoloader`.
3. **Web Server Configuration**: Configure your web server (Nginx/Apache) to use the `public/` directory as the document root. The rest of the application files should remain outside the document root for security.
4. **Database Setup**: Create a MySQL database and import the schema:
   ```bash
   mysql -u your_db_user -p your_db_name < schema.sql
   ```
   Alternatively, open phpMyAdmin, Adminer, TablePlus, or any database client and run the contents of `schema.sql` directly as a SQL query.
5. **Environment**: Copy `.env.example` to `.env` and configure your live database credentials and SMTP details.

## Docker

`compose.yml` runs nginx, php-fpm and MariaDB; the one-shot `migrate` service applies `schema.sql` to an empty database and `migrations/*.sql` to an existing one (`php bin/migrate.php`, safe to rerun). Set `APP_URL`, `DB_PASSWORD` and `DB_ROOT_PASSWORD`, then `docker compose up -d --build`. Port 8081 is the full app (published on `127.0.0.1` by default); port 8080 is the public scope, which only differs from the full app when `FEATURE_VISIBILITY=true`. [deploy/README.md](deploy/README.md) covers the two-host setup.

## Configuration (`.env`)

| Key | Default | Meaning |
|-----|---------|---------|
| `RATE_LIMIT_PER_MIN` | 60 | MCP requests per minute per token |
| `NOTE_MAX_BYTES` | 1048576 | Max note size (1 MB) |
| `FEATURE_HTML_NOTES` | false | Allow `format: "html"` notes |
| `FEATURE_ASSETS` | false | Allow `upload_asset` / `delete_asset` |
| `ASSET_MAX_BYTES` / `ASSET_MAX_PER_NOTE` | 5242880 / 50 | Per-image size cap and images per note |
| `ASSET_STORAGE_PATH` | `storage/assets` | Where images live; keep it outside `public/` |
| `FEATURE_VISIBILITY` | false | Tailnet/public split; requires the two-listener setup |
| `PUBLIC_URL` | — | Base URL of the public host, e.g. `https://share.example.com` |
| `FEATURE_SHARE_LINKS` | false | Share links (needs `FEATURE_VISIBILITY`) |
| `SHARE_LINK_TTL_DEFAULT_HOURS` / `SHARE_LINK_TTL_MAX_HOURS` | 168 / 720 | Share link lifetime: default 7 days, max 30 |
| `SHARE_COOKIE_TTL_HOURS` | 12 | Lifetime of the cookie a share link swaps itself for |
| `SHARE_LINK_RATE_PER_MIN` | 30 | Share-token guesses per IP per minute |
| `MAIL_PRIMARY_*` / `MAIL_FAILOVER_*` | — | Two full SMTP blocks: HOST, PORT, USERNAME, PASSWORD, ENCRYPTION, FROM_ADDRESS, FROM_NAME |
| `MYSQLDUMP_PATH` | auto-detect | Full path to `mysqldump` for the admin DB export |

## Security model

- API tokens: 192-bit random, shown once, stored as SHA-256 hashes, revocable from the dashboard.
- Auth: bcrypt password hashes, mandatory email verification (hashed 6-digit codes, 10-min expiry, attempt-limited), rate-limited login/registration, 7-day DB-backed sessions.
- Notes are unlisted: slug = title + 10 random base62 chars; note pages send `X-Robots-Tag: noindex`.
- Optional features are off by default, and with every flag off the app reads exactly the columns it always did, so an install that hasn't run `bin/migrate.php` keeps working (`tests/features_off.php`). Enable a flag only after migrating.
- HTML notes are served top-level under a CSP sandbox without `allow-same-origin`: the page gets an opaque origin, so it cannot read cookies or storage, and its requests to the app are cross-site (no `SameSite=Lax` session cookie) and unreadable. Also `nosniff` and `Referrer-Policy: no-referrer`. `tests/html_note_headers.php` pins the header set. They still run arbitrary scripts at your app's hostname, so a page could imitate a sign-in form; only Members can publish them.
- Assets: types come from magic bytes plus PHP's image parser, never from the declared type; names are server-made content hashes (no client text reaches a path); served with a fixed `Content-Type`, `nosniff` and `Content-Security-Policy: default-src 'none'; sandbox`.
- Visibility: the scope of a request is a FastCGI parameter set per listener by the web server, never a request header, and anything but `tailnet` is the public scope (`tests/scope.php`). The public scope is an allowlist router with no sessions; tailnet notes, expired and revoked share links and unknown paths all return the same 404.
- Share links: 256-bit tokens, stored as SHA-256, compared with `hash_equals`, checked for expiry and revocation on every request. The link swaps itself for an `HttpOnly`, host-only cookie scoped to `/n/<slug>` and redirects to the clean URL. With an `https` `PUBLIC_URL` the cookie is `Secure; SameSite=None`, because an HTML note's opaque origin makes its own image requests cross-site; with `http` it falls back to `Lax`, and images in HTML notes opened through a share link won't load. Guesses are rate-limited per IP; creation, opens, views and revocation are audited in `share_link_events` (time and a coarse client label, no tokens or IPs).
- Markdown is rendered with unsafe links stripped and raw HTML escaped, except an attribute-free allowlist (`<details>`, `<summary>` and inline formatting tags) so notes can collapse long sections the way a gist does. `tests/raw_html_allowlist.php` guards it.
