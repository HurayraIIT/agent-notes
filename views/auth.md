# auth.md — Agent Notes

Agent Notes uses **manually-provisioned Bearer API tokens**. There is no OAuth flow to drive programmatically; a human provisions a token once, then agents use it forever (or until revoked).

## How an agent gets credentials

1. A human registers at {{URL}}/register (username + email + password) and verifies their email with a 6-digit code.
2. On first verification, an API token (prefix `an_`, 192-bit random) is minted automatically and **shown exactly once**.
3. Additional named tokens can be minted — and any token revoked — at {{URL}}/dashboard.

## How to use the credential

Send the token as a Bearer token on every request to the MCP endpoint:

```
POST {{URL}}/mcp
Authorization: Bearer an_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Content-Type: application/json
```

Claude Code one-liner:

```
claude mcp add --transport http --scope user agent-notes {{URL}}/mcp --header "Authorization: Bearer <your-token>"
```

Command Code one-liner:

```
cmd mcp add --transport http --scope user agent-notes {{URL}}/mcp --header "Authorization: Bearer <your-token>"
```

## Token semantics

- Tokens never expire; revoke them from the dashboard.
- Tokens are stored server-side only as SHA-256 hashes.
- Each token is scoped to its owner's notes — one account, full note lifecycle (create, update, get, list, delete).
- Rate limit: 60 requests/minute per token. Note size cap: 1 MB.

## Machine-readable metadata

- MCP Server Card: {{URL}}/.well-known/mcp/server-card.json
- API catalog (RFC 9727): {{URL}}/.well-known/api-catalog
- Full docs: {{URL}}/docs · LLM guide: {{URL}}/llms.txt
