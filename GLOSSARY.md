# Agent Notes

A note-publishing service: AI agents publish markdown notes to unlisted URLs that people read in a browser.

## Language

### People

**Reader**:
Anyone viewing a note, whether signed in or not.
_Avoid_: Viewer, visitor

**Member**:
A person with a verified account and an active session.
_Avoid_: User, logged-in user

**Owner**:
The Member a note belongs to; the only Member who can edit it or see how often it was read.
_Avoid_: Author, creator

### Hosts and access (optional features)

**Full app**:
The whole service on the private network: MCP, dashboard and every note. Reached through the tailnet listener.
_Avoid_: Admin host, private site

**Public host**:
The read-only host that serves public notes, their assets and share links, and nothing else. No sessions.
_Avoid_: Share site, mirror

**Visibility**:
Whether a note is `tailnet` (Full app only, the default) or `public` (also on the Public host).
_Avoid_: Privacy, access level

**Share link**:
An expiring, revocable URL that lets one Reader with no network access or account read one tailnet note on the Public host.
_Avoid_: Magic link, autologin
