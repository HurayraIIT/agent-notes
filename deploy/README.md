# Two hosts: full app and public host

With `FEATURE_VISIBILITY=true`, one Agent Notes install answers on two hostnames:

| Host | Example | Serves | Reached through |
|------|---------|--------|-----------------|
| Full app | `notes.example.internal` | Everything: MCP, dashboard, sign-in, every note | the tailnet listener (`web:8081` in `compose.yml`) |
| Public host | `share.example.com` | Public notes, their assets, share links. Read-only, no sessions | the public listener (`web:8080`) |

Set `APP_URL` to the full app's URL and `PUBLIC_URL` to the public host's.

## How a request's scope is decided

The web server decides, by **which port the request arrived on**. Each nginx listener sets the FastCGI parameter `AGENT_NOTES_SCOPE` (`docker/nginx.conf`): `public` on 8080, `tailnet` on 8081. PHP reads it from `$_SERVER`. Request headers reach PHP only as `HTTP_*` keys, so no client can set or override it, and the `Host` header plays no part. A missing or unexpected value is the public scope, so a misconfigured server fails closed (`tests/scope.php`).

This means the network decides who gets the full app:

- **Never publish port 8081 on a public address.** `compose.yml` binds it to `AGENT_NOTES_TAILNET_BIND` (default `127.0.0.1`). Put a reverse proxy that only listens on the private network (a tailnet IP, a VPN, an internal load balancer) in front of it.
- The public reverse proxy forwards only to port 8080.
- Both proxies must **overwrite** `X-Forwarded-For` with the address they saw (`proxy_set_header X-Forwarded-For $remote_addr;`), not append to it. The container trusts that header from any private source address (`set_real_ip_from` in `docker/nginx.conf`), because the Docker bridge gateway it arrives from isn't known in advance. So anything that can reach ports 8080/8081 directly can choose its own rate-limit identity. That is one more reason to keep both ports reachable only by the two proxies; narrow the ranges to your bridge subnet if you know it.
- Proxies should pass `X-Forwarded-Proto https`.

Without Docker, use two nginx `server` blocks on different listen addresses, each with `fastcgi_param AGENT_NOTES_SCOPE public;` or `tailnet;` next to the usual `fastcgi_pass`.

## Checks after deploying

On the public host every one of these must be the same `404 Not found.`:

```
curl -si https://share.example.com/            # home page
curl -si https://share.example.com/dashboard
curl -si https://share.example.com/login
curl -si -X POST https://share.example.com/mcp
curl -si https://share.example.com/n/<a tailnet note's slug>
```

A public note's URL returns 200 on both hosts. On the full app, `/mcp` without a token returns 401.

## Turning features on

1. `php bin/migrate.php` (the `migrate` service does this in Docker).
2. Set the flags: `FEATURE_HTML_NOTES`, `FEATURE_ASSETS`, `FEATURE_VISIBILITY` with `PUBLIC_URL`, then `FEATURE_SHARE_LINKS`.
3. With assets on, keep `ASSET_STORAGE_PATH` on persistent storage outside `public/` (the `assets` volume in Docker) and back it up with the database.
