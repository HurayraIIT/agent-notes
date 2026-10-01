# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Agent Notes: a note-publishing MCP server for AI agents. Agents authenticate with a Bearer token at `POST /mcp` (JSON-RPC 2.0, stateless Streamable HTTP) and publish markdown notes that render at unlisted `/n/<slug>` URLs. Vanilla PHP 8.2+ (no framework), Tailwind Play CDN (no build step), MySQL. Composer deps: league/commonmark, phpmailer, dompdf only.

## Commands

```bash
composer install

# Local DB (Herd MySQL: root/password on 127.0.0.1) — schema changes are applied by drop & re-import, there are no migrations
mysql -h 127.0.0.1 -u root -ppassword -e "DROP DATABASE IF EXISTS agent_notes; CREATE DATABASE agent_notes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -h 127.0.0.1 -u root -ppassword agent_notes < schema.sql

# Lint everything
for f in public/index.php src/*.php views/*.php views/admin/*.php tests/*.php; do php -l "$f"; done

# Run the standalone checks (plain assert scripts, no framework — see below)
for t in tests/*.php; do php "$t" || break; done

# Run helper code against the app (bootstrap gives you db(), create_api_token(), etc.)
php -r 'require "src/bootstrap.php"; echo create_api_token(1, "test");'
```

Served by Laravel Herd at `http://agent-notes.test` (docroot is `public/`, auto-detected via BasicWithPublicValetDriver — do NOT add an index.php at repo root or Herd switches drivers).

There is no test framework, and none should be added. `tests/` holds standalone `assert`-style scripts run directly with `php tests/<name>.php` (each exits non-zero on failure) — they exist only for logic that can silently rot without visibly breaking a page: `ua_label.php` (UA-token ordering) and `cmd_block_wraps.php` (guards the no-horizontal-scroll requirement below, which regressed once). Everything else is tested E2E: curl/python against the live local site. A comprehensive 54-check suite pattern exists in past session scratchpads (`v2_test.py`) — register → verify → login flows, full MCP tool lifecycle, rate limit (expect exactly 60×200 then 429), PDF magic bytes, admin export, discovery endpoints. OTP codes are stored hashed, so tests seed a known code directly:

```bash
php -r 'require "src/bootstrap.php"; db()->prepare("INSERT INTO login_codes (email, code_hash, purpose, expires_at) VALUES (?, ?, \"verify\", NOW() + INTERVAL 10 MINUTE)")->execute(["x@y.com", hash("sha256", "123456")]);'
```

Always clean up test rows afterward (users/notes/tokens/sessions/login_codes/rate_limits).

## Architecture

Single front controller `public/index.php` routes on `REQUEST_URI` via a flat if-chain; every handler ends in an `exit`-ing helper (`render()`, `json_response()`, `text_response()`, `redirect()`). `src/bootstrap.php` loads a hand-rolled `.env` parser, defines `db()` (PDO singleton), `e()`, `view()`/`render()`, `cmd_block()`, `fmt_dt()` (human date, e.g. "July 12, 2026 07:50:06 AM") / `fmt_bytes()` (B/KB/MB) for human-facing displays, and requires all other `src/*.php` files — they are plain function files, no classes/autoloading. Datetimes and sizes are rendered raw in MCP JSON output (machine-facing) but through `fmt_dt()`/`fmt_bytes()` in views and the PDF header (human-facing).

- `src/mcp.php` — the core product. `mcp_handle()` does bearer auth → rate limit → JSON-RPC dispatch (`initialize`, `ping`, `tools/list`, `tools/call`, notifications → 202). Tool results are JSON-encoded into MCP text content; tool failures use `isError: true`, protocol failures use JSON-RPC errors.
- `src/auth.php` — registration, OTP verify, password login, DB-backed sessions. **Invariant: sessions are only ever created for verified users**, so `require_login()` implies verified and no separate gating exists anywhere. OTP success implicitly verifies the email. CSRF tokens are `hash_hmac('sha256', 'csrf', session cookie)` — no PHP sessions anywhere.
- `src/notes.php` — CRUD shared by MCP and dashboard; slugs are `slugify(title)-<10 base62 chars>` and never change on update.
- `src/markdown.php` — GFM plus three renderers hung off an explicit `Environment`. `html_input => 'escape'` still holds for everything except `AllowlistedRawHtmlRenderer` (priority 100), which passes through an **attribute-free** allowlist — `details`, `summary` and inline formatting tags, plus `open` on `<details>` — so notes collapse long sections like a gist. Its invariant: strip every allowlisted tag, and a surviving `<` rejects the whole literal, so every `<` reaching the browser opens an allowlisted tag. Widening it is stored XSS for every reader; `tests/raw_html_allowlist.php` fails if the sweep finds an unexpected tag or attribute. `GithubAlertRenderer` turns `> [!NOTE]` into `.md-alert` divs, `MermaidRenderer` turns ```` ```mermaid ```` into `<pre class="mermaid">`, and `balance_details()` appends missing `</details>` purely so the fragment is well-formed for dompdf and `innerHTML` — an unclosed tag still wraps the trailing content, same as on GitHub. Note the CommonMark rule this inherits: **without a blank line after `</summary>`, the whole body stays one raw literal and markdown inside it renders literally** — same as GitHub.
- Everything secret is stored hashed (sha256 for API tokens/session tokens/OTP codes, bcrypt for passwords); plaintext tokens exist only in the response that mints them.
- Views (`views/*.php`) receive variables via `extract()` — IDE "undefined variable" diagnostics in view files are false positives. `views/layout.php` wraps pages; `views/note.php` is standalone (own `<html>`). Its header is non-sticky (scrolls away) and reuses layout.php's gradient brand link; its only site nav is one plain-text link, a sibling of the brand (so on mobile it stays on the brand row) with a divider before the note actions from `md` up — **Dashboard** for a Member, else **Sign in** carrying `?next=/n/<slug>`, which `/login` and `/login/verify` honour through `local_path()` (same-origin paths only; dropped on the first-login token screen). The view count is Owner-only (`$can_edit`) — see `GLOSSARY.md` for Reader/Member/Owner; the note card's toolbar has a **Copy Link** button (not a second Raw — Raw stays in the top nav) that copies `location.origin + location.pathname` so the shared URL never carries a heading `#fragment`; a fixed scroll-to-top FAB fades in past 600px scroll (only reachable on long notes). Dark mode is Tailwind `darkMode: 'class'` + localStorage toggle; both layout and note.php duplicate the theme script. In note.php the dark *reading* surfaces use GitHub canvas hexes rather than slate (card/editor `#0d1117`, `pre` blocks `#161b22`, inner strips `white/5`) to match the github-dark hljs theme and gist palette in its `<style>` block — the page and header stay slate.
- `views/home.md`, `views/docs.md`, `views/llms.txt`, `views/auth.md`, `skills/publish-notes/SKILL.md` are `{{URL}}`-templated markdown served by PHP routes (also via `Accept: text/markdown` content negotiation on `/` and `/docs`). Keep them in sync with their HTML counterparts when features change — plus `README.md`, which carries the same MCP connect commands but hardcodes the domain.
- Email (`src/mail.php`): PHPMailer tries `MAIL_PRIMARY_*` env block, falls back to `MAIL_FAILOVER_*`. Locally, Herd intercepts SMTP, so sends "succeed" with placeholder creds — check Herd's mail panel.
- Rate limiting (`src/ratelimit.php`): fixed-window counters in the `rate_limits` table, keyed string + window; used for MCP (60/min/token), OTP sends, login/registration attempts.

## Gotchas

- Herd's **global** nginx template (`~/Library/Application Support/Herd/config/nginx/herd.conf`) has a bare `location = /robots.txt` block: locally, `/robots.txt` always returns HTTP 404 with the correct body, no matter how it's served. Unfixable from app code; fine on live nginx. `robots.txt` and `llms.txt` are `{{URL}}`-templated in `views/` and PHP-served (robots gains a dynamic `Sitemap:` line) — do not put static copies in `public/`, they'd shadow the routes.
- Discovery surface (all PHP routes in public/index.php): sitemap.xml, auth.md, `.well-known/` mcp/server-card.json, api-catalog, agent-skills(+/index.json — sha256 digest computed at request time from the served SKILL.md bytes), oauth-protected-resource, oauth-authorization-server, openid-configuration, jwks.json. The OAuth metadata describes out-of-band bearer tokens, not a real OAuth server. WebMCP: layout.php exposes the 5 note tools via `navigator.modelContext` calling `POST /webmcp` (session + CSRF).
- `app_url()` prefers `APP_URL` env, else derives scheme+host from the request — never hardcode the domain in rendered output.
- Commands shown in the UI must fully wrap (`whitespace-pre-wrap break-all` via `cmd_block()`) — no horizontal scrolling, per product requirement. `cmd_block()` is the only command renderer (used by home/docs/token_created), so the rule holds by keeping its `<pre>` classes intact; `tests/cmd_block_wraps.php` fails if they drift. Tables are the deliberate exception — they get their own scroll box. Copy buttons use `copyText()` in layout.php, which has a `document.execCommand` fallback because `http://agent-notes.test` is not a secure context.
- The seeded admin (`hurayraiit+admin@gmail.com`, users.id=1) has no password/username; it signs in via email OTP.
- `.env` is git-ignored and holds real SMTP creds; `.env.example` is the template. Never commit `.env`.
