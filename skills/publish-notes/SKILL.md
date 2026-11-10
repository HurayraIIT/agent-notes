---
name: publish-notes
description: Publish markdown notes to clean, shareable, unlisted URLs via the Agent Notes MCP server. Use when you produce content worth sharing with humans — reports, analyses, documentation, meeting notes — and need a link instead of pasting a wall of text.
---

# Publish Notes

Agent Notes ({{URL}}) turns markdown into polished, unlisted web pages. Publish over MCP, hand the URL to a human.

## Prerequisites

You need the `agent-notes` MCP server connected with a Bearer token:

```
claude mcp add --transport http --scope user agent-notes {{URL}}/mcp --header "Authorization: Bearer <token>"
```

Command Code uses the same flags:

```
cmd mcp add --transport http --scope user agent-notes {{URL}}/mcp --header "Authorization: Bearer <token>"
```

If you don't have a token, ask your human to register at {{URL}}/register — their first token is minted automatically after email verification.

## When to publish

Publish a note whenever your output is a document a human will read: a report, an audit, an analysis, documentation, meeting notes, a runbook. Do NOT publish secrets, credentials, or content the user asked to keep local.

## Workflow

1. Write the document as GitHub-flavored markdown (headings, tables, task lists, and code fences all render).
   - Long reports read better collapsed. `<details><summary>…</summary>` works exactly as it does in a GitHub gist — put a blank line after `</summary>` and before `</details>` so the markdown inside is still parsed. `<b>`, `<i>`, `<code>` and other inline tags work inside the summary line, where markdown does not.
   - `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]` and `> [!CAUTION]` render as callouts.
   - A ` ```mermaid ` fence renders as a diagram.
   - Other raw HTML is escaped and shown as text.
2. Call `create_note` with a descriptive `title` and the markdown as `content` (max 1 MB). Optionally pass `filename` (e.g. `incident-report`) to control the downloaded file name — it never appears in the short random URL.
3. Give the returned `url` to the human — it renders as a clean page with raw view, .md download, and print-to-PDF.
4. To revise, call `update_note` with the note's `slug` — the URL never changes.
5. Use `list_notes` to find earlier notes, `get_note` to re-read one, and `delete_note` to clean up notes that are no longer needed.

## Optional features

Some servers enable more tools; check `tools/list` before using them.

- **HTML notes** (`format: "html"`): send one complete HTML document when a page needs layout, scripts or interactivity that markdown can't give. It runs in a browser sandbox: no cookies, storage or calls back to the app, and forms don't submit. Put images inline as `data:` URIs or upload them.
- **Images** (`upload_asset`): base64 PNG, JPEG, GIF or WebP, default max 5 MB, no SVG. Embed the returned `path`: `![alt](path)` or `<img src="path">`.
- **Visibility**: notes default to `tailnet` (private network only). Set `visibility: "public"` only when the note has no secrets, credentials, tokens, private hostnames or IPs, customer or personal data. If in doubt, keep it `tailnet` and make a share link.
- **Share links** (`create_share_link`): a one-time URL that lets one person outside the network read one tailnet note, for up to 30 days. Give it only to that person; revoke it with `revoke_share_link` when they're done.

## Notes on privacy

URLs are unlisted and cryptographically random: anyone with the link can read, but nothing is listed, indexed, or guessable. Treat the URL itself as the access credential.
