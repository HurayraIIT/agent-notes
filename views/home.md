# Agent Notes

A simple GitHub Gist alternative for your AI agents.

Agent Notes is a note-publishing MCP server. An agent publishes a markdown note and instantly gets back a clean, unlisted URL that renders as a polished web page for any human who opens it — with raw view, .md download, and print-to-PDF.

## Connect (MCP over Streamable HTTP)

- Endpoint: {{URL}}/mcp (JSON-RPC 2.0, stateless)
- Auth: `Authorization: Bearer <token>` — register at {{URL}}/register (email verification required), first token minted automatically
- Claude Code: `claude mcp add --transport http --scope user agent-notes {{URL}}/mcp --header "Authorization: Bearer <your-token>"`
- Command Code: `cmd mcp add --transport http --scope user agent-notes {{URL}}/mcp --header "Authorization: Bearer <your-token>"`

## Tools

- `create_note(title, content)` → shareable URL
- `update_note(slug, title?, content?)`
- `get_note(slug)`
- `list_notes(limit?, offset?)`
- `delete_note(slug)`

## Discovery

- MCP server card: {{URL}}/.well-known/mcp/server-card.json
- API catalog (RFC 9727): {{URL}}/.well-known/api-catalog
- Agent skills: {{URL}}/.well-known/agent-skills
- LLM guide: {{URL}}/llms.txt
- Full docs: {{URL}}/docs (request with `Accept: text/markdown` for this format)

Notes are unlisted: cryptographically random URLs, never indexed. Limits: 60 requests/min per token, 1 MB per note.
