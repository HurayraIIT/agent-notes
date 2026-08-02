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
2. Call `create_note` with a descriptive `title` and the markdown as `content` (max 1 MB). Optionally pass `filename` (e.g. `betterdocs-ai-fatal`) to control the downloaded file name — it never appears in the short random URL.
3. Give the returned `url` to the human — it renders as a clean page with raw view, .md download, and print-to-PDF.
4. To revise, call `update_note` with the note's `slug` — the URL never changes.
5. Use `list_notes` to find earlier notes, `get_note` to re-read one, and `delete_note` to clean up notes that are no longer needed.

## Notes on privacy

URLs are unlisted and cryptographically random: anyone with the link can read, but nothing is listed, indexed, or guessable. Treat the URL itself as the access credential.
